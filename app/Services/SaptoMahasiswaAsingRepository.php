<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tabel 2.b) Mahasiswa Asing (format BAN-PT LKPS). Extracted from
 * MahasiswaController::data_mahasiswa_asing() (2026-07-09) so
 * SaptoDashboardRepository can reuse the same query + faculty mapping
 * instead of duplicating it.
 *
 * Beda dari repository tabel lain: TS TIDAK dibaca dari request — window
 * TS-2/TS-1/TS masih hardcode 2023/2024/2025, sudah keterbatasan yang ada
 * sebelum ekstraksi ini (lihat komentar di data_mahasiswa_asing() lama).
 *
 * "Aktif pada tahun X" = sudah terdaftar (id_periode_masuk <= X) dan belum
 * keluar sebelum tahun X berakhir (id_periode_keluar kosong, atau keluarnya
 * di tahun setelah X).
 */
class SaptoMahasiswaAsingRepository
{
    /**
     * @return array{tahun_akademik: array{ts_2: string, ts_1: string, ts: string}, total_prodi: int, data: array<int, object>}
     */
    public function summary(?string $fakultasId = null, ?string $programStudiId = null): array
    {
        $ts = '2025';
        $ts1 = '2024';
        $ts2 = '2023';

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
            HAVING (asing_ft_ts2 + asing_ft_ts1 + asing_ft_ts) > 0
            ORDER BY
                ps.nama_program_studi ASC
        ");

        $facultyMap = $this->facultyByProdiCode();

        $rows = array_map(function (object $row) use ($facultyMap): object {
            $faculty = $facultyMap[$row->kode_program_studi] ?? 'Belum Dipetakan';
            $row->fakultas = $faculty;
            $row->fakultas_id = Str::slug($faculty) ?: 'belum-dipetakan';

            return $row;
        }, $data);

        // Scope fakultas/prodi: sama seperti maVisibleRows() di klien
        // (resources/views/sapto.blade.php), dipindah ke sini supaya
        // SaptoDashboardRepository bisa discope tanpa fetch-semua-lalu-
        // filter-di-JS. Endpoint /sapto/mahasiswa-asing yang sudah ada tetap
        // memanggil summary() tanpa argumen (null, null = tidak difilter),
        // jadi perilakunya tidak berubah.
        $rows = array_values(array_filter($rows, function (object $row) use ($fakultasId, $programStudiId): bool {
            if ($fakultasId !== null && $row->fakultas_id !== $fakultasId) {
                return false;
            }

            if ($programStudiId !== null && (string) $row->id_prodi !== $programStudiId) {
                return false;
            }

            return true;
        }));

        return [
            'tahun_akademik' => ['ts_2' => $ts2, 'ts_1' => $ts1, 'ts' => $ts],
            'total_prodi' => count($rows),
            'data' => $rows,
        ];
    }

    /**
     * Peta kode_program_studi -> nama fakultas, dibaca dari
     * database/data/program_studi.csv (satu-satunya sumber pemetaan
     * fakultas yang tersedia lokal; tabel program_studi tidak menyimpannya).
     *
     * @return array<string, string>
     */
    private function facultyByProdiCode(): array
    {
        $path = database_path('data/program_studi.csv');

        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '') ?: [];
        $map = [];

        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($headers) !== count($values)) {
                continue;
            }

            $record = array_combine($headers, $values);
            $code = trim((string) ($record['kode'] ?? ''));

            if ($code === '') {
                continue;
            }

            $map[$code] = $this->normalizeFaculty((string) ($record['fakultas'] ?? ''));
        }

        fclose($handle);

        return $map;
    }

    /**
     * Rapikan nama fakultas mentah dari CSV menjadi label yang konsisten,
     * mis. "KEDOKTERANM" -> "Fakultas Kedokteran", "PASCASARJANA" ->
     * "Pascasarjana". Selaras dengan PangkalanDataApiClient::normalizeFaculty().
     */
    private function normalizeFaculty(string $faculty): string
    {
        $faculty = trim((string) preg_replace('/\s+/', ' ', Str::upper(Str::ascii($faculty))));

        if ($faculty === 'KEDOKTERANM') {
            $faculty = 'KEDOKTERAN';
        }

        if ($faculty === '') {
            return 'Belum Dipetakan';
        }

        if ($faculty === 'PASCASARJANA') {
            return 'Pascasarjana';
        }

        if (str_starts_with($faculty, 'FAKULTAS ')) {
            $faculty = trim(substr($faculty, 9));
        }

        return 'Fakultas '.Str::title(Str::lower($faculty));
    }
}
