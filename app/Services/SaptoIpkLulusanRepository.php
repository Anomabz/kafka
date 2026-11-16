<?php

namespace App\Services;

use App\Services\Concerns\ComputesGraduationYear;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 8.a) IPK Lulusan. Sumber: list_mahasiswa_lulus_do. Mengikuti format
 * BAN-PT: satu baris per tahun lulus (TS-2, TS-1, TS) dengan jumlah lulusan dan
 * IPK minimum/rata-rata/maksimum. Hanya baris keluar "Lulus" yang dihitung; IPK
 * di luar rentang (0,4] dianggap kosong dan tidak masuk statistik. Cakupan
 * mengikuti scope role (prodi/fakultas/universitas).
 */
class SaptoIpkLulusanRepository
{
    use ComputesGraduationYear;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();
        $years = [$ts - 2, $ts - 1, $ts];
        $labels = ['TS-2', 'TS-1', 'TS'];

        $aggregate = $scopeProdiIds === []
            ? []
            : collect($this->aggregate($years, $scopeProdiIds))->keyBy('thn');

        $rows = collect($years)
            ->map(function (int $year, int $index) use ($aggregate, $labels): array {
                $row = $aggregate === [] ? null : $aggregate->get((string) $year);
                $ipkN = (int) ($row['ipk_n'] ?? 0);

                return [
                    'no' => $index + 1,
                    'tahun_lulus' => $year.' ('.$labels[$index].')',
                    'tahun_lulus_tahun' => $year,
                    'jumlah_lulusan' => (int) ($row['jumlah'] ?? 0),
                    'ipk_min' => $ipkN > 0 ? round((float) $row['ipk_min'], 2) : null,
                    'ipk_rata' => $ipkN > 0 ? round((float) $row['ipk_avg'], 2) : null,
                    'ipk_maks' => $ipkN > 0 ? round((float) $row['ipk_max'], 2) : null,
                ];
            })
            ->values();

        return [
            'table_code' => '8.a',
            'table_name' => 'IPK Lulusan',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'left'],
                ['key' => 'tahun_lulus', 'label' => 'Tahun Lulus', 'align' => 'left'],
                ['key' => 'jumlah_lulusan', 'label' => 'Jumlah Lulusan', 'align' => 'num'],
                ['key' => 'ipk_min', 'label' => 'IPK Min', 'align' => 'num'],
                ['key' => 'ipk_rata', 'label' => 'IPK Rata-rata', 'align' => 'num'],
                ['key' => 'ipk_maks', 'label' => 'IPK Maks', 'align' => 'num'],
            ],
            'rows' => $rows,
            'metrics' => $this->metrics($rows->all(), $ts),
            'source' => $this->sourcePayload(),
        ];
    }

    /** Daftar lulusan yang membentuk angka pada satu baris Tabel 8.a. */
    public function detail(int $year, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        if ($scopeProdiIds === []) {
            return [];
        }

        $yearExpr = $this->graduationYearSql();
        $query = DB::table('list_mahasiswa_lulus_do')
            ->select([
                'nim',
                'nama_mahasiswa as nama',
                'nama_program_studi as program_studi',
                'angkatan',
                'ipk',
                'tanggal_keluar',
            ])
            ->selectRaw("{$yearExpr} as tahun_lulus")
            ->where('nama_jenis_keluar', 'Lulus')

            ->where('nama_jenis_keluar', 'Lulus')
            ->whereRaw("{$yearExpr} = ?", [(string) $year]);

        if ($scopeProdiIds !== null) {
            $query->whereIn('id_prodi', $scopeProdiIds);
        }

        return $query
            ->orderBy('nama_mahasiswa')
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    /**
     * Agregasi per tahun lulus di dalam scope.
     *
     * @param  array<int, int>  $years
     * @param  array<int, string>|null  $scopeProdiIds
     * @return array<int, array<string, mixed>>
     */
    private function aggregate(array $years, ?array $scopeProdiIds): array
    {
        $cacheKey = 'sapto:ipk-lulusan:v2:'.md5(json_encode([$years, $scopeProdiIds]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years, $scopeProdiIds): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);
            $yearPlaceholders = implode(',', array_fill(0, count($yearStrings), '?'));
            $bindings = $yearStrings;

            $scopeSql = '';
            if ($scopeProdiIds !== null) {
                $scopePlaceholders = implode(',', array_fill(0, count($scopeProdiIds), '?'));
                $scopeSql = "and id_prodi in ({$scopePlaceholders})";
                array_push($bindings, ...$scopeProdiIds);
            }

            // ipk+0: koersi teks->angka konsisten di MySQL & SQLite. Untuk
            // statistik Tabel 8.a, gunakan semua IPK valid dalam rentang (0,4].
            // Nilai 0/kosong dan nilai di atas 4 dianggap data tidak valid;
            // IPK di bawah 2 tetap dihitung jika tercatat pada lulusan.
            $yearExpr = $this->graduationYearSql();
            $sql = "
                select
                    {$yearExpr} as thn,
                    count(*) as jumlah,
                    sum(case when ipk+0 > 0 and ipk+0 <= 4 then 1 else 0 end) as ipk_n,
                    min(case when ipk+0 > 0 and ipk+0 <= 4 then ipk+0 end) as ipk_min,
                    max(case when ipk+0 > 0 and ipk+0 <= 4 then ipk+0 end) as ipk_max,
                    avg(case when ipk+0 > 0 and ipk+0 <= 4 then ipk+0 end) as ipk_avg
                from list_mahasiswa_lulus_do
                where nama_jenis_keluar = 'Lulus'
                  and {$yearExpr} in ({$yearPlaceholders})
                  {$scopeSql}
                group by {$yearExpr}
            ";

            return array_map(fn (object $row): array => (array) $row, DB::select($sql, $bindings));
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, int $ts): array
    {
        $totalLulusan = array_sum(array_map(fn (array $r): int => (int) $r['jumlah_lulusan'], $rows));
        $tsRow = $rows[count($rows) - 1] ?? null; // baris terakhir = TS

        return [
            ['label' => 'Total lulusan (TS-2..TS)', 'value' => $totalLulusan],
            ['label' => 'Jumlah lulusan (TS)', 'value' => (int) ($tsRow['jumlah_lulusan'] ?? 0)],
            ['label' => 'IPK rata-rata (TS)', 'value' => $tsRow['ipk_rata'] ?? 0],
            ['label' => 'Tahun lulus (TS)', 'value' => $ts],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:ipk-lulusan:years:v1', $this->cacheTtl(), function (): array {
            $yearExpr = $this->graduationYearSql();

            $rows = DB::select("
                select distinct {$yearExpr} as thn
                from list_mahasiswa_lulus_do
                where nama_jenis_keluar = 'Lulus'
                  and {$yearExpr} is not null
                order by thn desc
            ");

            return array_values(array_filter(
                array_map(fn (object $row): int => (int) $row->thn, $rows),
                fn (int $year): bool => $year >= 2000 && $year <= 2100
            ));
        });
    }

    private function sourcePayload(): array
    {
        return [
            'name' => 'Pangkalan Data UNSRI (sinkronisasi lokal)',
            'partial' => false,
        ];
    }
}
