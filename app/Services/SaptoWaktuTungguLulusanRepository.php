<?php

namespace App\Services;

use App\Services\Concerns\ComputesGraduationYear;
use App\Services\Concerns\CountsLulusanPerProdi;
use App\Services\Concerns\ResolvesSaptoScope;

/**
 * Tabel 8.d.1) Waktu Tunggu Lulusan. Per program studi (BAN-PT: "Diisi oleh
 * pengusul dari Program Studi"), satu baris per tahun lulus TS-4/TS-3/TS-2.
 * Sama seperti Tabel 8.c (Masa Studi), tabel ini butuh satu prodi terpilih
 * dan formatnya bercabang menurut jenjang prodi itu -- bukan dua tabel
 * terpisah seperti 3.b.4, tapi satu tabel yang kolomnya berubah:
 * - Diploma Tiga: Jumlah Lulusan, Jumlah Lulusan Terlacak, Jumlah Lulusan
 *   yang Dipesan Sebelum Lulus, lalu WT < 3 bulan / 3-6 bulan / > 6 bulan.
 * - Sarjana (S1, S2, S3): Jumlah Lulusan, Jumlah Lulusan Terlacak, lalu
 *   WT < 6 bulan / 6-18 bulan / > 18 bulan (tanpa kolom "dipesan").
 * Jenjang lain (Sp-1/Sp-2/Profesi) tidak punya varian tabel ini di form
 * BAN-PT -- notice, bukan tabel kosong.
 *
 * Sumber "Jumlah Lulusan": list_mahasiswa_lulus_do, tahun lulus lewat
 * ComputesGraduationYear (sama seperti Tabel 8.a & 8.c, supaya ketiganya
 * menghitung lulusan yang sama). Kolom sisanya (terlacak, dipesan sebelum
 * lulus, waktu tunggu) butuh data tracer study alumni yang TIDAK ADA di
 * sinkronisasi manapun di database ini -- sudah dicek: pekerjaan,
 * jenis_pekerjaan, ikatan_kerja, sister_list_riwayat_pekerjaan semuanya 0
 * baris. Selalu 0 sampai ada sumber tracer study terpisah; isi manual.
 */
class SaptoWaktuTungguLulusanRepository
{
    use ComputesGraduationYear;
    use CountsLulusanPerProdi;
    use ResolvesSaptoScope;

    private const CACHE_NAMESPACE = 'waktu-tunggu';

    /** TS-4, TS-3, TS-2 -- baris tabel, tetap sama untuk kedua varian jenjang. */
    private const BACK_YEARS = [4, 3, 2];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->latestYear();

        if ($programStudi === null) {
            return $this->noticeEnvelope($ts, $fakultas, $programStudi,
                'Tabel 8.d.1 disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan tabel waktu tunggu lulusan.');
        }

        $variant = $this->variantForJenjang($programStudi['jenjang'] ?? null);

        if ($variant === null) {
            $jenjang = $programStudi['jenjang'] !== '' ? $programStudi['jenjang'] : '(tidak diketahui)';

            return $this->noticeEnvelope($ts, $fakultas, $programStudi,
                "Tabel 8.d.1 hanya berlaku untuk program studi jenjang Diploma Tiga atau Sarjana. Program studi ini berjenjang {$jenjang}.");
        }

        $years = array_map(fn (int $back): int => $ts - $back, self::BACK_YEARS);
        $lulusanByYear = $this->lulusanCountsByYear(self::CACHE_NAMESPACE, (string) $programStudi['id'], $years);

        $columns = $this->columnsForVariant($variant);
        $numericKeys = collect($columns)
            ->pluck('key')
            ->reject(fn (string $key): bool => in_array($key, ['no', 'tahun_lulus'], true))
            ->all();

        $rows = [];
        foreach (self::BACK_YEARS as $index => $back) {
            $year = $ts - $back;
            $row = array_fill_keys($numericKeys, 0);
            $row['no'] = $index + 1;
            $row['tahun_lulus'] = $year.' (TS-'.$back.')';
            $row['jumlah_lulusan'] = $lulusanByYear[$year] ?? 0;
            $rows[] = $row;
        }

        $rows[] = ['no' => null, 'tahun_lulus' => 'Jumlah'] + collect($numericKeys)
            ->mapWithKeys(fn (string $key): array => [$key => array_sum(array_column($rows, $key))])
            ->all();

        return [
            'table_code' => '8.d.1',
            'table_name' => 'Waktu Tunggu Lulusan',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => $columns,
            'rows' => $rows,
            'metrics' => $this->metrics($rows, $ts),
            'notice' => null,
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, align: string}>
     */
    private function columnsForVariant(string $variant): array
    {
        $base = [
            ['key' => 'no', 'label' => 'No', 'align' => 'left'],
            ['key' => 'tahun_lulus', 'label' => 'Tahun Lulus', 'align' => 'left'],
            ['key' => 'jumlah_lulusan', 'label' => 'Jumlah Lulusan', 'align' => 'num'],
            ['key' => 'jumlah_terlacak', 'label' => 'Jumlah Lulusan yang Terlacak', 'align' => 'num'],
        ];

        $wtGroup = 'Jumlah Lulusan Terlacak dengan Waktu Tunggu Mendapatkan Pekerjaan';

        if ($variant === 'd3') {
            return array_merge($base, [
                ['key' => 'jumlah_dipesan', 'label' => 'Jumlah Lulusan yang Dipesan Sebelum Lulus', 'align' => 'num'],
                ['key' => 'wt_1', 'label' => 'WT < 3 Bulan', 'align' => 'num', 'group' => $wtGroup],
                ['key' => 'wt_2', 'label' => '3 ≤ WT ≤ 6 Bulan', 'align' => 'num', 'group' => $wtGroup],
                ['key' => 'wt_3', 'label' => 'WT > 6 Bulan', 'align' => 'num', 'group' => $wtGroup],
            ]);
        }

        return array_merge($base, [
            ['key' => 'wt_1', 'label' => 'WT < 6 Bulan', 'align' => 'num', 'group' => $wtGroup],
            ['key' => 'wt_2', 'label' => '6 ≤ WT ≤ 18 Bulan', 'align' => 'num', 'group' => $wtGroup],
            ['key' => 'wt_3', 'label' => 'WT > 18 Bulan', 'align' => 'num', 'group' => $wtGroup],
        ]);
    }

    /**
     * Nilai jenjang di database sudah bersih (D3, Profesi, S1, S2, S3, Sp-1,
     * Sp-2 -- tidak ada D4/Sarjana Terapan saat ini), jadi cocok persis
     * dengan cakupan tabel 8.d.1 (hanya D3 & S1) sudah cukup, tanpa heuristik
     * substring seperti di SaptoMasaStudiRepository::jenjangConfig().
     */
    private function variantForJenjang(?string $jenjang): ?string
    {
        return match (strtoupper(trim((string) $jenjang))) {
            'D3' => 'd3',
            'S1', 'S2', 'S3' => 'sarjana',
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, int $ts): array
    {
        $jumlahRow = $rows[count($rows) - 1];
        $ts2Row = $rows[count($rows) - 2] ?? null; // baris TS-2 = tahun lulus terbaru dalam cakupan tabel ini

        return [
            ['label' => 'Total lulusan (TS-4..TS-2)', 'value' => (int) $jumlahRow['jumlah_lulusan']],
            ['label' => 'Lulusan TS-2', 'value' => (int) ($ts2Row['jumlah_lulusan'] ?? 0)],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    private function noticeEnvelope(int $ts, ?array $fakultas, ?array $programStudi, string $notice): array
    {
        return [
            'table_code' => '8.d.1',
            'table_name' => 'Waktu Tunggu Lulusan',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [],
            'rows' => [],
            'metrics' => [],
            'notice' => $notice,
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return $this->lulusanAvailableYears(self::CACHE_NAMESPACE);
    }

    private function latestYear(): int
    {
        return $this->availableYears()[0] ?? (int) now()->year;
    }

    private function sourcePayload(): array
    {
        return [
            'name' => 'list_mahasiswa_lulus_do (sinkronisasi lokal) -- kolom tracer study (terlacak/dipesan/waktu tunggu) belum ada sumbernya',
            'partial' => true,
        ];
    }
}
