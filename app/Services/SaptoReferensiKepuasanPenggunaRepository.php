<?php

namespace App\Services;

use App\Services\Concerns\ComputesGraduationYear;
use App\Services\Concerns\CountsLulusanPerProdi;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;

/**
 * Tabel Referensi 8.e.2) -- denominator untuk Tabel 8.e.2 Kepuasan Pengguna
 * Lulusan (Sangat Baik/Baik/Cukup/Kurang per 7 aspek, belum diimplementasi).
 * Per program studi, satu baris per tahun lulus TS-4/TS-3/TS-2: Jumlah
 * Lulusan, lalu Jumlah Tanggapan Kepuasan Pengguna yang Terlacak.
 *
 * Sumber "Jumlah Lulusan": sama seperti Tabel 8.d.1/8.d.2/8.e.1
 * (list_mahasiswa_lulus_do lewat CountsLulusanPerProdi/ComputesGraduationYear,
 * supaya semuanya menghitung lulusan yang sama). "Jumlah Tanggapan ...
 * Terlacak" butuh data tracer study alumni (respons survei pengguna
 * lulusan/atasan) yang TIDAK ADA sumbernya di database ini -- lihat
 * SaptoWaktuTungguLulusanRepository. Selalu 0 sampai ada sumber tracer
 * study terpisah; isi manual.
 */
class SaptoReferensiKepuasanPenggunaRepository
{
    use ComputesGraduationYear;
    use CountsLulusanPerProdi;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    private const CACHE_NAMESPACE = 'referensi-kepuasan-pengguna';

    /** TS-4, TS-3, TS-2 -- baris tabel. */
    private const BACK_YEARS = [4, 3, 2];

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'tahun_lulus', 'label' => 'Tahun Lulus', 'align' => 'left'],
        ['key' => 'jumlah_lulusan', 'label' => 'Jumlah Lulusan', 'align' => 'num'],
        ['key' => 'jumlah_tanggapan_terlacak', 'label' => 'Jumlah Tanggapan Kepuasan Pengguna yang Terlacak', 'align' => 'num'],
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();

        if ($programStudi === null) {
            return $this->noticeEnvelope($ts, $fakultas, $programStudi,
                'Tabel Referensi 8.e.2 disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkannya.');
        }

        $years = array_map(fn (int $back): int => $ts - $back, self::BACK_YEARS);
        $lulusanByYear = $this->lulusanCountsByYear(self::CACHE_NAMESPACE, (string) $programStudi['id'], $years);

        $numericKeys = collect(self::COLUMNS)
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
            'table_code' => '8.e.2',
            'table_name' => 'Referensi Kepuasan Pengguna',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'metrics' => $this->metrics($rows, $ts),
            'notice' => null,
            'source' => $this->sourcePayload(),
        ];
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
            'table_code' => '8.e.2',
            'table_name' => 'Referensi Kepuasan Pengguna',
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

    private function sourcePayload(): array
    {
        return [
            'name' => 'list_mahasiswa_lulus_do (sinkronisasi lokal) -- kolom tanggapan kepuasan pengguna belum ada sumbernya',
            'partial' => true,
        ];
    }
}
