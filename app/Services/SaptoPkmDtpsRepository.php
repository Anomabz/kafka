<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tabel 3.b.3) PkM DTPS. Kloning persis dari Tabel 3.b.2 (Penelitian DTPS) --
 * sumber sinkronisasi SISTER untuk PkM (sister_detail_pengabdian) punya
 * struktur identik dengan sister_detail_penelitian: anggota tersimpan
 * sebagai JSON di kolom `anggota`, dana di dana_dikti/dana_perguruan_tinggi/
 * dana_institusi_lain, klasifikasi luar negeri lewat jenis_skim. "DTPS" =
 * dosen tetap program studi: judul dihitung bila salah satu anggota bertipe
 * "Dosen" cocok dengan roster homebase (list_penugasan_dosen,
 * a_sp_homebase = 1) pada TAHUN judul itu dilaksanakan -- roster dihitung
 * terpisah untuk TS-2/TS-1/TS, sama seperti 3.b.2.
 *
 * (sister_detail_penugasan/sister_sdm/sister_list_pengabdian TIDAK dipakai --
 * sister_list_pengabdian kosong di sumber, dan id_ikatan_kerja bukan cara
 * yang dipakai tabel DTPS lain untuk menentukan homebase per prodi.)
 *
 * Baris sumber dari sinkronisasi mengandung duplikat identik per id (fan-out
 * di proses sync) -> dideduplikasi per id sebelum dihitung.
 *
 * Klasifikasi "Sumber Pembiayaan" tidak saling eksklusif -- satu judul yang
 * didanai lebih dari satu sumber dihitung di tiap baris kategori yang
 * berlaku (konsisten dengan makna "jumlah judul per sumber dana"):
 * - "a) Perguruan Tinggi b) Mandiri": dana_perguruan_tinggi > 0, atau tidak
 *   ada dana tercatat sama sekali di ketiga kolom (dianggap mandiri -- tidak
 *   ada kolom "mandiri" eksplisit di sumber).
 * - "Lembaga dalam negeri (di luar PT)": dana_dikti > 0 atau
 *   dana_institusi_lain > 0 (Kemendikbud/DIKTI, institusi lain domestik).
 * - "Lembaga luar negeri": jenis_skim mengandung teks "luar negeri"
 *   (heuristik teks -- satu-satunya penanda eksplisit luar negeri di sumber;
 *   perlu diverifikasi manual). Menang atas kategori dalam negeri bila
 *   keduanya cocok pada judul yang sama.
 */
class SaptoPkmDtpsRepository
{
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
        $years = ['ts_2' => $ts - 2, 'ts_1' => $ts - 1, 'ts' => $ts];

        $dtpsIdsByYear = [];
        foreach ($years as $key => $year) {
            $dtpsIdsByYear[$key] = $scopeProdiIds === [] ? [] : $this->dtpsIds($year, $scopeProdiIds);
        }

        $pkmByYear = $this->pkmRows(array_values($years));

        $categories = [
            'pt_mandiri' => 'a) Perguruan Tinggi b) Mandiri',
            'dalam_negeri' => 'Lembaga dalam negeri (di luar PT)',
            'luar_negeri' => 'Lembaga luar negeri',
        ];

        $counts = array_fill_keys(array_keys($categories), array_fill_keys(array_keys($years), 0));
        $distinctJudul = array_fill_keys(array_keys($years), 0);

        foreach ($years as $key => $year) {
            $dtpsIds = array_flip($dtpsIdsByYear[$key]);

            foreach ($pkmByYear[$year] ?? [] as $row) {
                if (! $this->hasDtpsMember($row['anggota_ids'], $dtpsIds)) {
                    continue;
                }

                $distinctJudul[$key]++;

                $isForeign = $row['jenis_skim'] !== null && Str::contains(Str::lower($row['jenis_skim']), 'luar negeri');
                $hasDikti = $row['dana_dikti'] > 0;
                $hasPt = $row['dana_perguruan_tinggi'] > 0;
                $hasLain = $row['dana_institusi_lain'] > 0;
                $hasNoFunding = ! $hasDikti && ! $hasPt && ! $hasLain;

                if ($hasPt || $hasNoFunding) {
                    $counts['pt_mandiri'][$key]++;
                }

                if (($hasDikti || $hasLain) && ! $isForeign) {
                    $counts['dalam_negeri'][$key]++;
                }

                if ($isForeign) {
                    $counts['luar_negeri'][$key]++;
                }
            }
        }

        $rows = collect($categories)
            ->map(fn (string $label, string $key): array => [
                'sumber_pembiayaan' => $label,
                'ts_2' => $counts[$key]['ts_2'],
                'ts_1' => $counts[$key]['ts_1'],
                'ts' => $counts[$key]['ts'],
                'jumlah' => array_sum($counts[$key]),
            ])
            ->values()
            ->map(fn (array $row, int $index): array => ['no' => $index + 1] + $row)
            ->push([
                'no' => null,
                'sumber_pembiayaan' => 'Jumlah',
                'ts_2' => array_sum(array_column($counts, 'ts_2')),
                'ts_1' => array_sum(array_column($counts, 'ts_1')),
                'ts' => array_sum(array_column($counts, 'ts')),
                'jumlah' => array_sum(array_map('array_sum', $counts)),
            ])
            ->values()
            ->all();

        return [
            'table_code' => '3.b.3',
            'table_name' => 'PkM DTPS',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'left'],
                ['key' => 'sumber_pembiayaan', 'label' => 'Sumber Pembiayaan', 'align' => 'left'],
                ['key' => 'ts_2', 'label' => "Jumlah Judul PkM TS-2 ({$years['ts_2']})", 'align' => 'num'],
                ['key' => 'ts_1', 'label' => "Jumlah Judul PkM TS-1 ({$years['ts_1']})", 'align' => 'num'],
                ['key' => 'ts', 'label' => "Jumlah Judul PkM TS ({$years['ts']})", 'align' => 'num'],
                ['key' => 'jumlah', 'label' => 'Jumlah', 'align' => 'num'],
            ],
            'rows' => $rows,
            'metrics' => $this->metrics($distinctJudul, $ts),
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * Roster id_dosen homebase (DTPS) pada satu tahun ajaran + scope prodi.
     *
     * @param  array<int, string>|null  $scopeProdiIds
     * @return array<int, string>
     */
    private function dtpsIds(int $year, ?array $scopeProdiIds): array
    {
        $cacheKey = 'sapto:pkm-dtps:roster:v1:'.md5(json_encode([$year, $scopeProdiIds]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($year, $scopeProdiIds): array {
            $query = DB::table('list_penugasan_dosen')
                ->select('id_dosen')
                ->distinct()
                ->where('a_sp_homebase', '1')
                ->whereRaw('substr(nama_tahun_ajaran,1,4) = ?', [(string) $year]);

            if ($scopeProdiIds !== null) {
                $query->whereIn('id_prodi', $scopeProdiIds);
            }

            return $query->pluck('id_dosen')
                ->map(fn (mixed $id): string => (string) $id)
                ->filter(fn (string $id): bool => $id !== '')
                ->values()
                ->all();
        });
    }

    /**
     * Judul PkM dari sister_detail_pengabdian, dikelompokkan per
     * tahun_pelaksanaan dan dideduplikasi per id (lihat docblock kelas).
     *
     * @param  array<int, int>  $years
     * @return array<int, array<int, array{dana_dikti: float, dana_perguruan_tinggi: float, dana_institusi_lain: float, jenis_skim: ?string, anggota_ids: array<int, string>}>>
     */
    private function pkmRows(array $years): array
    {
        $cacheKey = 'sapto:pkm-dtps:pkm:v1:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);

            $byId = [];

            foreach (DB::table('sister_detail_pengabdian')
                ->select('id', 'tahun_pelaksanaan', 'dana_dikti', 'dana_perguruan_tinggi', 'dana_institusi_lain', 'jenis_skim', 'anggota')
                ->whereIn('tahun_pelaksanaan', $yearStrings)
                ->cursor() as $row) {
                $byId[(string) $row->id] ??= $row;
            }

            $byYear = array_fill_keys($years, []);

            foreach ($byId as $row) {
                $year = (int) $row->tahun_pelaksanaan;

                if (! isset($byYear[$year])) {
                    continue;
                }

                $byYear[$year][] = [
                    'dana_dikti' => (float) $row->dana_dikti,
                    'dana_perguruan_tinggi' => (float) $row->dana_perguruan_tinggi,
                    'dana_institusi_lain' => (float) $row->dana_institusi_lain,
                    'jenis_skim' => $row->jenis_skim,
                    'anggota_ids' => $this->dosenIdsFromAnggota($row->anggota),
                ];
            }

            return $byYear;
        });
    }

    /**
     * @return array<int, string>
     */
    private function dosenIdsFromAnggota(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn (mixed $member): bool => is_array($member) && ($member['jenis'] ?? null) === 'Dosen' && ! empty($member['id_sdm']))
            ->map(fn (array $member): string => (string) $member['id_sdm'])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $anggotaIds
     * @param  array<string, int>  $dtpsIdsFlipped
     */
    private function hasDtpsMember(array $anggotaIds, array $dtpsIdsFlipped): bool
    {
        foreach ($anggotaIds as $id) {
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
            ['label' => 'Total judul PkM DTPS (TS-2..TS)', 'value' => array_sum($distinctJudul)],
            ['label' => 'Judul PkM DTPS (TS)', 'value' => $distinctJudul['ts']],
            ['label' => 'Judul PkM DTPS (TS-1)', 'value' => $distinctJudul['ts_1']],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:pkm-dtps:years:v1', $this->cacheTtl(), function (): array {
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

    private function sourcePayload(): array
    {
        return [
            'name' => 'sister_detail_pengabdian & list_penugasan_dosen (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}
