<?php

namespace App\Services;

use App\Services\Concerns\ComputesGraduationYear;
use App\Services\Concerns\CountsLulusanPerProdi;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;

/**
 * Tabel 8.e.1) Tempat Kerja Lulusan. Per program studi (BAN-PT: "Diisi oleh
 * pengusul dari Program Studi"), satu baris per tahun lulus TS-4/TS-3/TS-2 --
 * satu format tabel untuk semua jenjang (sama seperti Tabel 8.d.2): Jumlah
 * Lulusan, Jumlah Lulusan Terlacak, lalu tingkat/ukuran tempat kerja
 * Lokal/Nasional/Multinasional (termasuk wirausaha tidak/berbadan hukum).
 *
 * Sumber "Jumlah Lulusan": sama seperti Tabel 8.d.1/8.d.2
 * (list_mahasiswa_lulus_do lewat CountsLulusanPerProdi/ComputesGraduationYear,
 * supaya ketiganya menghitung lulusan yang sama). Kolom sisanya (terlacak,
 * tingkat tempat kerja) butuh data tracer study alumni yang TIDAK ADA
 * sumbernya di database ini -- lihat SaptoWaktuTungguLulusanRepository.
 * Selalu 0 sampai ada sumber tracer study terpisah; isi manual.
 */
class SaptoTempatKerjaRepository
{
    use ComputesGraduationYear;
    use CountsLulusanPerProdi;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    private const CACHE_NAMESPACE = 'tempat-kerja';

    /** TS-4, TS-3, TS-2 -- baris tabel. */
    private const BACK_YEARS = [4, 3, 2];

    private const TEMPAT_KERJA_GROUP = 'Jumlah Lulusan Terlacak yang Bekerja Berdasarkan Tingkat/Ukuran Tempat Kerja/Berwirausaha';

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'tahun_lulus', 'label' => 'Tahun Lulus', 'align' => 'left'],
        ['key' => 'jumlah_lulusan', 'label' => 'Jumlah Lulusan', 'align' => 'num'],
        ['key' => 'jumlah_terlacak', 'label' => 'Jumlah Lulusan yang Terlacak', 'align' => 'num'],
        ['key' => 'lokal', 'label' => 'Lokal/Wilayah/Berwirausaha tidak Berbadan Hukum', 'align' => 'num', 'group' => self::TEMPAT_KERJA_GROUP],
        ['key' => 'nasional', 'label' => 'Nasional/Berwirausaha Berbadan Hukum', 'align' => 'num', 'group' => self::TEMPAT_KERJA_GROUP],
        ['key' => 'multinasional', 'label' => 'Multinasional/Internasional', 'align' => 'num', 'group' => self::TEMPAT_KERJA_GROUP],
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
                'Tabel 8.e.1 disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan tabel tempat kerja lulusan.');
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
            'table_code' => '8.e.1',
            'table_name' => 'Tempat Kerja Lulusan',
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
            'table_code' => '8.e.1',
            'table_name' => 'Tempat Kerja Lulusan',
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
            'name' => 'list_mahasiswa_lulus_do (sinkronisasi lokal) -- kolom tracer study (terlacak/tingkat tempat kerja) belum ada sumbernya',
            'partial' => true,
        ];
    }
}
