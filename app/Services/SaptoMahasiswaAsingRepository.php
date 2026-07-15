<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 2.b) Mahasiswa Asing (format BAN-PT LKPS). Extracted from
 * MahasiswaController::data_mahasiswa_asing() (2026-07-09) so
 * SaptoDashboardRepository can reuse the same query + faculty mapping
 * instead of duplicating it.
 *
 * Pemetaan fakultas sekarang diambil dari SaptoProgramStudiRepository
 * (cached) daripada parsing CSV di sini setiap kali dipanggil.
 *
 * "Aktif pada tahun X" = sudah terdaftar (id_periode_masuk <= X) dan belum
 * keluar sebelum tahun X berakhir (id_periode_keluar kosong, atau keluarnya
 * di tahun setelah X).
 */
class SaptoMahasiswaAsingRepository
{
    public function __construct(private readonly SaptoProgramStudiRepository $client) {}

    /**
     * @return array{ts: int, available_years: array<int, int>, tahun_akademik: array{ts_2: string, ts_1: string, ts: string}, total_prodi: int, data: array<int, object>}
     */
    public function summary(?string $fakultasId = null, ?string $programStudiId = null, ?int $ts = null): array
    {
        $ts ??= $this->latestYear();

        // Query mentahnya (join 3 tabel ratusan ribu baris) tidak berubah per
        // fakultas/prodi — hasilnya sama untuk semua akun, filter scope-nya
        // baru diterapkan di PHP setelah ini. Jadi di-cache satu kali per TS
        // (bukan per-scope) supaya akun manapun yang pertama membuka
        // dashboard/tabel ini menanggung biaya query-nya, bukan tiap akun.
        $unscoped = Cache::remember("sapto:mahasiswa-asing:v2:{$ts}", $this->cacheTtl(), fn (): array => $this->queryUnscoped($ts));

        $rows = array_values(array_filter($unscoped['rows'], function (array $row) use ($fakultasId, $programStudiId): bool {
            if ($fakultasId !== null && $row['fakultas_id'] !== $fakultasId) {
                return false;
            }

            if ($programStudiId !== null && $row['id_prodi'] !== $programStudiId) {
                return false;
            }

            return true;
        }));

        return [
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'tahun_akademik' => $unscoped['tahun_akademik'],
            'total_prodi' => count($rows),
            'data' => array_map(fn (array $row): object => (object) $row, $rows),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:mahasiswa-asing:years:v1', $this->cacheTtl(), function (): array {
            $earliest = (int) (DB::table('list_riwayat_pendidikan_mahasiswa')
                ->whereNotNull('id_periode_masuk')
                ->where('id_periode_masuk', '!=', '')
                ->min(DB::raw('SUBSTRING(id_periode_masuk, 1, 4)')) ?: $this->latestYear());

            $latest = $this->latestYear();

            return range($latest, max($earliest, $latest - 30), -1);
        });
    }

    private function latestYear(): int
    {
        // ponytail: sinkronisasi tabel lain (dosen, mahasiswa) belum sampai
        // tahun ajaran 2026 — batasi ke 2025 dulu supaya gak "lebih maju"
        // dari tabel lain (lihat SaptoKerjasamaRepository::latestYear() untuk
        // pola yang sama). Naikkan angka ini begitu data 2026 benar-benar ada.
        return min((int) now()->year, 2025);
    }

    /**
     * @return array{tahun_akademik: array{ts_2: string, ts_1: string, ts: string}, rows: array<int, array<string, mixed>>}
     */
    private function queryUnscoped(int $latestYear): array
    {
        $ts = (string) $latestYear;
        $ts1 = (string) ($latestYear - 1);
        $ts2 = (string) ($latestYear - 2);

        $aktifTs2 = "SUBSTRING(lrpm.id_periode_masuk, 1, 4) <= '$ts2' AND (lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' OR SUBSTRING(lrpm.id_periode_keluar, 1, 4) > '$ts2')";
        $aktifTs1 = "SUBSTRING(lrpm.id_periode_masuk, 1, 4) <= '$ts1' AND (lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' OR SUBSTRING(lrpm.id_periode_keluar, 1, 4) > '$ts1')";
        $aktifTs = "SUBSTRING(lrpm.id_periode_masuk, 1, 4) <= '$ts'  AND (lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' OR SUBSTRING(lrpm.id_periode_keluar, 1, 4) > '$ts')";

        // Tabel 2.b (format resmi BAN-PT LKPS): "Jumlah Mahasiswa Aktif" adalah
        // TOTAL mahasiswa aktif SEMUA kewarganegaraan (basis/pembagi), BUKAN
        // hanya mahasiswa asing. Hanya kolom "Mahasiswa Asing Penuh Waktu/Paruh
        // Waktu" di bawah yang dibatasi ke kewarganegaraan asing.
        $asing = "n.id_negara IS NOT NULL AND n.id_negara <> 'ID'";

        $data = DB::select("
            SELECT
                ps.id_prodi,
                ps.kode_program_studi,
                CONCAT(ps.nama_jenjang_pendidikan, ' ', ps.nama_program_studi) AS program_studi,
                COALESCE(SUM(CASE WHEN $aktifTs2 THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts2,
                COALESCE(SUM(CASE WHEN $aktifTs1 THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts1,
                COALESCE(SUM(CASE WHEN $aktifTs  THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts,
                COALESCE(SUM(CASE WHEN $aktifTs2 AND $asing THEN 1 ELSE 0 END), 0) AS asing_ft_ts2,
                COALESCE(SUM(CASE WHEN $aktifTs1 AND $asing THEN 1 ELSE 0 END), 0) AS asing_ft_ts1,
                COALESCE(SUM(CASE WHEN $aktifTs  AND $asing THEN 1 ELSE 0 END), 0) AS asing_ft_ts,
                0 AS asing_pt_ts2,
                0 AS asing_pt_ts1,
                0 AS asing_pt_ts
            FROM program_studi ps
            LEFT JOIN list_riwayat_pendidikan_mahasiswa lrpm
                ON lrpm.id_prodi = ps.id_prodi
            LEFT JOIN biodata_mahasiswa bm
                ON bm.id_mahasiswa = lrpm.id_mahasiswa
            LEFT JOIN negara n
                ON n.id_negara = bm.id_negara
            WHERE ps.nama_program_studi IS NOT NULL
            GROUP BY
                ps.id_prodi,
                ps.kode_program_studi,
                ps.nama_program_studi,
                ps.nama_jenjang_pendidikan
            -- Tabel 2.b BAN-PT hanya memuat prodi yang punya mahasiswa asing
            -- AKTIF di periode penilaian (TS-2/TS-1/TS). Prodi yang mahasiswa
            -- asingnya sudah keluar sebelum TS-2 -> semua kolom asing 0, tak
            -- perlu ditampilkan (lihat S2 Teknik Informatika / S2 Ilmu Komputer
            -- yang asingnya lulus 2020, di luar window 2023-2025).
            HAVING (asing_ft_ts2 + asing_ft_ts1 + asing_ft_ts) > 0
            ORDER BY
                ps.nama_program_studi ASC
        ");

        $programIndex = $this->client->programStudis()->keyBy('id');

        $rows = array_map(function (array $row) use ($programIndex): array {
            $program = $programIndex->get((string) $row['id_prodi']);
            $row['fakultas'] = $program['fakultas'] ?? 'Belum Dipetakan';
            $row['fakultas_id'] = $program['fakultas_id'] ?? 'belum-dipetakan';
            // Pakai label prodi yang sudah bersih (tanpa "Profesi Profesi ..."
            // / jenjang placeholder) alih-alih CONCAT SQL mentah.
            $row['program_studi'] = $program['label'] ?? $row['program_studi'];

            return $row;
        }, array_map(fn (object $row): array => (array) $row, $data));

        return [
            'tahun_akademik' => ['ts_2' => $ts2, 'ts_1' => $ts1, 'ts' => $ts],
            'rows' => $rows,
        ];
    }

    private function cacheTtl(): int
    {
        return max(1, (int) config('services.pangkalan_data.cache_ttl', 3600));
    }
}
