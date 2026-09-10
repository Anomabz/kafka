<?php

namespace App\Services;

use App\Services\Concerns\ResolvesPublikasiDtpsRoster;
use App\Services\Concerns\ResolvesSaptoScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Basis dua varian Tabel 3.b.4 BAN-PT yang menghitung publikasi DTPS dengan
 * cara persis sama tapi berbeda daftar barisnya:
 *
 * - {@see SaptoPublikasiIlmiahDtpsRepository} "Publikasi Ilmiah DTPS", untuk
 *   program akademik (Sarjana/Magister/Doktor). Baris 8-10 = tulisan di media
 *   massa.
 * - {@see SaptoPagelaranDtpsRepository} "Pagelaran/Pameran/Presentasi/
 *   Publikasi Ilmiah DTPS", untuk program vokasi (Diploma Tiga/Sarjana
 *   Terapan/Magister Terapan/Doktor Terapan). Baris 8-10 = pagelaran/pameran/
 *   presentasi dalam forum.
 *
 * Baris 1-7 (jurnal + seminar) identik di kedua varian.
 *
 * Sumber: sister_list_publikasi (hasil sinkronisasi SISTER, klaim BKD per
 * dosen) -- satu publikasi bisa muncul sebagai beberapa baris dengan `id`
 * sama (satu baris per penulis/id_sdm), jadi dikelompokkan per id sebelum
 * dihitung.
 *
 * "DTPS" ditentukan dari sister_list_penugasan: penugasan homebase terakhir
 * tiap dosen (ROW_NUMBER) yang masih aktif pada tahun pelaporan (tanggal_mulai
 * <= tahun AND (tanggal_keluar IS NULL OR tanggal_keluar >= tahun)), disaring
 * jenjang_pendidikan sesuai varian. Filter scope prodi dilakukan lewat kolom
 * unit_kerja. Roster dihitung terpisah untuk TS-2/TS-1/TS.
 *
 * jenis_publikasi yang tidak ada di peta varian (buku, monograf, dst) di luar
 * cakupan tabel dan tidak dihitung.
 *
 * Cache data mentah (roster + daftar judul) sengaja memakai kunci bersama
 * lintas varian: isinya sama persis, jadi memanaskan satu varian sekaligus
 * mempercepat varian lainnya.
 */
abstract class SaptoPublikasiDtpsRepository
{
    use ResolvesPublikasiDtpsRoster;
    use ResolvesSaptoScope;

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    abstract protected function tableName(): string;

    /**
     * Label 10 baris tabel, berurutan.
     *
     * @return array<string, string> kategori => label baris
     */
    abstract protected function categories(): array;

    /**
     * @return array<string, string> jenis_publikasi (sumber SISTER) => kategori
     */
    abstract protected function jenisMap(): array;

    /**
     * Nilai jenjang_pendidikan yang dianggap DTPS untuk varian ini.
     *
     * @return array<int, string>
     */
    abstract protected function jenjangFilter(): array;

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->latestYear();
        $years = ['ts_2' => $ts - 2, 'ts_1' => $ts - 1, 'ts' => $ts];

        $categories = $this->categories();
        $jenisMap = $this->jenisMap();

        $dtpsIdsByYear = [];
        foreach ($years as $key => $year) {
            $dtpsIdsByYear[$key] = $scopeProdiIds === [] ? [] : $this->publikasiDtpsIds($year, $scopeProdiIds, $this->jenjangFilter());
        }

        $publikasiByYear = $this->publikasiRows(array_values($years));

        $counts = array_fill_keys(array_keys($categories), array_fill_keys(array_keys($years), 0));
        $distinctJudul = array_fill_keys(array_keys($years), 0);

        foreach ($years as $key => $year) {
            $dtpsIds = array_flip($dtpsIdsByYear[$key]);

            foreach ($publikasiByYear[$year] ?? [] as $row) {
                if (! $this->hasDtpsAuthor($row['id_sdm_list'], $dtpsIds)) {
                    continue;
                }

                $category = $jenisMap[$row['jenis_publikasi']] ?? null;

                if ($category === null) {
                    continue;
                }

                $counts[$category][$key]++;
                $distinctJudul[$key]++;
            }
        }

        $rows = collect($categories)
            ->map(fn (string $label, string $key): array => [
                'jenis_publikasi' => $label,
                'ts_2' => $counts[$key]['ts_2'],
                'ts_1' => $counts[$key]['ts_1'],
                'ts' => $counts[$key]['ts'],
                'jumlah' => array_sum($counts[$key]),
            ])
            ->values()
            ->map(fn (array $row, int $index): array => ['no' => $index + 1] + $row)
            ->push([
                'no' => null,
                'jenis_publikasi' => 'Jumlah',
                'ts_2' => array_sum(array_column($counts, 'ts_2')),
                'ts_1' => array_sum(array_column($counts, 'ts_1')),
                'ts' => array_sum(array_column($counts, 'ts')),
                'jumlah' => array_sum(array_map('array_sum', $counts)),
            ])
            ->values()
            ->all();

        return [
            'table_code' => '3.b.4',
            'table_name' => $this->tableName(),
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'left'],
                ['key' => 'jenis_publikasi', 'label' => 'Jenis Publikasi', 'align' => 'left'],
                ['key' => 'ts_2', 'label' => "Jumlah Judul TS-2 ({$years['ts_2']})", 'align' => 'num'],
                ['key' => 'ts_1', 'label' => "Jumlah Judul TS-1 ({$years['ts_1']})", 'align' => 'num'],
                ['key' => 'ts', 'label' => "Jumlah Judul TS ({$years['ts']})", 'align' => 'num'],
                ['key' => 'jumlah', 'label' => 'Jumlah', 'align' => 'num'],
            ],
            'rows' => $rows,
            'metrics' => $this->metrics($distinctJudul, $ts),
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * Judul publikasi dari sister_list_publikasi, dikelompokkan per id (satu
     * baris per penulis) lalu per tahun publikasi (lihat docblock kelas).
     *
     * @param  array<int, int>  $years
     * @return array<int, array<int, array{jenis_publikasi: ?string, id_sdm_list: array<int, string>}>>
     */
    private function publikasiRows(array $years): array
    {
        $cacheKey = 'sapto:publikasi-dtps:publikasi:v1:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);
            $placeholders = implode(',', array_fill(0, count($yearStrings), '?'));

            $byId = [];

            foreach (DB::table('sister_list_publikasi')
                ->selectRaw('id, id_sdm, jenis_publikasi, substr(tanggal,1,4) as tahun')
                ->whereRaw("substr(tanggal,1,4) in ({$placeholders})", $yearStrings)
                ->cursor() as $row) {
                $id = (string) $row->id;

                $byId[$id] ??= [
                    'tahun' => (int) $row->tahun,
                    'jenis_publikasi' => $row->jenis_publikasi,
                    'id_sdm_list' => [],
                ];
                $byId[$id]['id_sdm_list'][] = (string) $row->id_sdm;
            }

            $byYear = array_fill_keys($years, []);

            foreach ($byId as $row) {
                if (! isset($byYear[$row['tahun']])) {
                    continue;
                }

                $byYear[$row['tahun']][] = [
                    'jenis_publikasi' => $row['jenis_publikasi'],
                    'id_sdm_list' => $row['id_sdm_list'],
                ];
            }

            return $byYear;
        });
    }

    /**
     * @param  array<int, string>  $idSdmList
     * @param  array<string, int>  $dtpsIdsFlipped
     */
    private function hasDtpsAuthor(array $idSdmList, array $dtpsIdsFlipped): bool
    {
        foreach ($idSdmList as $id) {
            if (isset($dtpsIdsFlipped[$id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $distinctJudul
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $distinctJudul, int $ts): array
    {
        return [
            ['label' => 'Total judul publikasi DTPS (TS-2..TS)', 'value' => array_sum($distinctJudul)],
            ['label' => 'Judul publikasi DTPS (TS)', 'value' => $distinctJudul['ts']],
            ['label' => 'Judul publikasi DTPS (TS-1)', 'value' => $distinctJudul['ts_1']],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:publikasi-dtps:years:v1', $this->cacheTtl(), function (): array {
            $rows = DB::select("
                select distinct substr(nama_tahun_ajaran,1,4) as thn
                from list_penugasan_dosen
                where a_sp_homebase = '1'
                  and nama_tahun_ajaran is not null
                  and nama_tahun_ajaran <> ''
                order by thn desc
            ");

            return array_values(array_filter(
                array_map(fn (object $row): int => (int) $row->thn, $rows),
                fn (int $year): bool => $year >= 2000 && $year <= 2100
            ));
        });
    }

    private function latestYear(): int
    {
        return $this->availableYears()[0] ?? (int) now()->year;
    }

    private function sourcePayload(): array
    {
        return [
            'name' => 'sister_list_publikasi & list_penugasan_dosen (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}
