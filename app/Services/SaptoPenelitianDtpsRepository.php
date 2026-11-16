<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tabel 3.b.2) Penelitian DTPS. Sumber: sister_detail_penelitian (hasil
 * sinkronisasi SISTER). Anggota peneliti tersimpan sebagai JSON di kolom
 * `anggota` (bukan lewat sister_list_penelitian, yang kosong di sumber).
 * Judul dihitung bila salah satu anggota bertipe "Dosen" cocok dengan roster
 * prodi pada TAHUN judul itu dilaksanakan. Roster = dosen homebase
 * (list_penugasan_dosen, a_sp_homebase = 1) DITAMBAH dosen yang mengajar
 * kelas prodi itu pada tahun yang sama (keputusan pengguna) -- roster dihitung
 * terpisah untuk TS-2/TS-1/TS, bukan cuma TS, karena homebase dosen bisa
 * berubah antar tahun ajaran.
 *
 * Baris sumber dari sinkronisasi mengandung duplikat identik per id (fan-out
 * di proses sync) -> dideduplikasi per id sebelum dihitung.
 *
 * Judul dengan dana 0 di ketiga kolom (dana_dikti, dana_perguruan_tinggi,
 * dana_institusi_lain) TIDAK dihitung, baik di tabel, kartu metrik, maupun
 * drill-down (aturan ada di satu tempat: klasifikasi()).
 *
 * Klasifikasi "Sumber Pembiayaan" TIDAK saling eksklusif -- judul berdana
 * ganda masuk SEMUA kategori sumber dananya (satu judul bisa tampil di lebih
 * dari satu baris). Baris "Jumlah" dan kartu metrik menghitung judul unik,
 * jadi bisa lebih kecil dari penjumlahan baris:
 * - "a) Perguruan Tinggi b) Mandiri": dana_perguruan_tinggi > 0.
 * - "Lembaga dalam negeri (di luar PT)": dana_dikti > 0 atau
 *   dana_institusi_lain > 0 (Kemendikbud/DIKTI, institusi lain domestik).
 * - "Lembaga luar negeri": jenis_skim mengandung teks "luar negeri"
 *   (heuristik teks -- satu-satunya penanda eksplisit luar negeri di sumber;
 *   perlu diverifikasi manual), dan judul harus punya dana > 0.
 */
class SaptoPenelitianDtpsRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    private const LABEL_DETAIL = [
        'pt_mandiri' => 'Perguruan Tinggi / Mandiri',
        'dalam_negeri' => 'Lembaga dalam negeri',
        'luar_negeri' => 'Lembaga luar negeri',
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function detail(int $year, string $category, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);
        $dtpsIds = array_flip($scopeProdiIds === [] ? [] : $this->dtpsIds($year, $scopeProdiIds));
        $items = [];
        $seen = [];

        foreach (DB::table('sister_detail_penelitian')
            ->select('id', 'judul', 'tahun_pelaksanaan', 'jenis_skim', 'dana_dikti', 'dana_perguruan_tinggi', 'dana_institusi_lain', 'anggota')
            ->where('tahun_pelaksanaan', (string) $year)
            ->orderBy('judul')
            ->cursor() as $row) {
            if (isset($seen[(string) $row->id])) {
                continue;
            }
            $seen[(string) $row->id] = true;

            if (! $this->hasDtpsMember($this->dosenIdsFromAnggota($row->anggota), $dtpsIds)) {
                continue;
            }

            $kategori = $this->klasifikasi(
                (float) $row->dana_dikti,
                (float) $row->dana_perguruan_tinggi,
                (float) $row->dana_institusi_lain,
                $row->jenis_skim,
            );

            // Dana 0 di ketiga kolom -> tidak dihitung.
            if ($kategori === []) {
                continue;
            }

            // Satu judul bisa masuk beberapa kategori (dana ganda).
            if ($category !== 'all' && ! in_array($category, $kategori, true)) {
                continue;
            }

            $items[] = [
                'judul' => $row->judul ?: '(Judul tidak tersedia)',
                'jenis' => $row->jenis_skim ?: '-',
                'kategori' => implode(', ', array_map(fn (string $k): string => self::LABEL_DETAIL[$k], $kategori)),
                'tahun' => $row->tahun_pelaksanaan,
                'dana_dikti' => $row->dana_dikti,
                'dana_pt' => $row->dana_perguruan_tinggi,
                'dana_lain' => $row->dana_institusi_lain,
            ];
        }

        return $items;
    }

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

        $penelitianByYear = $this->penelitianRows(array_values($years));

        $categories = [
            'pt_mandiri' => 'a) Perguruan Tinggi b) Mandiri',
            'dalam_negeri' => 'Lembaga dalam negeri (di luar PT)',
            'luar_negeri' => 'Lembaga luar negeri',
        ];

        $counts = array_fill_keys(array_keys($categories), array_fill_keys(array_keys($years), 0));
        $distinctJudul = array_fill_keys(array_keys($years), 0);

        foreach ($years as $key => $year) {
            $dtpsIds = array_flip($dtpsIdsByYear[$key]);

            foreach ($penelitianByYear[$year] ?? [] as $row) {
                if (! $this->hasDtpsMember($row['anggota_ids'], $dtpsIds)) {
                    continue;
                }

                $kategori = $this->klasifikasi(
                    $row['dana_dikti'],
                    $row['dana_perguruan_tinggi'],
                    $row['dana_institusi_lain'],
                    $row['jenis_skim'],
                );

                // Dana 0 di ketiga kolom -> tidak dihitung.
                if ($kategori === []) {
                    continue;
                }

                // Judul dihitung SEKALI di total, tetapi masuk SEMUA kategori
                // sumber dananya (dana ganda -> beberapa baris).
                $distinctJudul[$key]++;

                foreach ($kategori as $kategoriKey) {
                    $counts[$kategoriKey][$key]++;
                }
            }
        }

        $rows = collect($categories)
            ->map(fn (string $label, string $key): array => [
                'detail_key' => $key,
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
                'detail_key' => 'all',
                'sumber_pembiayaan' => 'Jumlah',
                // Jumlah = judul unik (bukan penjumlahan baris, karena satu
                // judul berdana ganda tampil di beberapa baris).
                'ts_2' => $distinctJudul['ts_2'],
                'ts_1' => $distinctJudul['ts_1'],
                'ts' => $distinctJudul['ts'],
                'jumlah' => array_sum($distinctJudul),
            ])
            ->values()
            ->all();

        return [
            'table_code' => '3.b.2',
            'table_name' => 'Penelitian DTPS',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'left'],
                ['key' => 'sumber_pembiayaan', 'label' => 'Sumber Pembiayaan', 'align' => 'left'],
                ['key' => 'ts_2', 'label' => "Jumlah Judul TS-2 ({$years['ts_2']})", 'align' => 'num'],
                ['key' => 'ts_1', 'label' => "Jumlah Judul TS-1 ({$years['ts_1']})", 'align' => 'num'],
                ['key' => 'ts', 'label' => "Jumlah Judul TS ({$years['ts']})", 'align' => 'num'],
                ['key' => 'jumlah', 'label' => 'Jumlah', 'align' => 'num'],
            ],
            'rows' => $rows,
            'metrics' => $this->metrics($distinctJudul, $ts),
            // Semua jumlah 0 = belum ada data untuk lingkup ini, walau barisnya
            // (jenis/sumber) selalu tampil; SaptoKesiapanService membacanya.
            'source' => $this->sourcePayload() + ['template' => (int) (end($rows)['jumlah'] ?? 0) === 0],
        ];
    }

    /**
     * Satu-satunya tempat aturan klasifikasi sumber pembiayaan, dipakai
     * bersama oleh summary() dan detail() supaya angkanya selalu konsisten.
     * Kategori TIDAK saling eksklusif: judul berdana ganda masuk semua
     * kategori sumber dananya.
     *
     * @return array<int, 'pt_mandiri'|'dalam_negeri'|'luar_negeri'> kosong =
     *         dana 0 di ketiga kolom (judul tidak dihitung)
     */
    private function klasifikasi(float $danaDikti, float $danaPt, float $danaLain, ?string $jenisSkim): array
    {
        $hasDikti = $danaDikti > 0;
        $hasPt = $danaPt > 0;
        $hasLain = $danaLain > 0;

        if (! $hasDikti && ! $hasPt && ! $hasLain) {
            return [];
        }

        $kategori = [];

        if ($hasPt) {
            $kategori[] = 'pt_mandiri';
        }

        if ($hasDikti || $hasLain) {
            $kategori[] = 'dalam_negeri';
        }

        if ($jenisSkim !== null && Str::contains(Str::lower($jenisSkim), 'luar negeri')) {
            $kategori[] = 'luar_negeri';
        }

        return $kategori;
    }

    /**
     * Roster dosen pada satu tahun ajaran + scope prodi:
     *  (a) homebase (DTPS) di scope pada tahun itu, DITAMBAH
     *  (b) dosen yang MENGAJAR kelas prodi di scope pada tahun itu
     *      (dosen_pengajar_kelas_kuliah, semester ganjil/genap/pendek)
     *      -- keputusan pengguna, pola sama dengan 3.a.1 / EWMP / 3.b.1.
     * id_dosen feeder = id_sdm SISTER (terverifikasi >99%), jadi bisa
     * dicocokkan langsung dengan anggota penelitian.
     *
     * @param  array<int, string>|null  $scopeProdiIds
     * @return array<int, string>
     */
    private function dtpsIds(int $year, ?array $scopeProdiIds): array
    {
        $cacheKey = 'sapto:penelitian-dtps:roster:v2:'.md5(json_encode([$year, $scopeProdiIds]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($year, $scopeProdiIds): array {
            $homebase = DB::table('list_penugasan_dosen')
                ->select('id_dosen')
                ->distinct()
                ->where('a_sp_homebase', '1')
                ->whereRaw('substr(nama_tahun_ajaran,1,4) = ?', [(string) $year]);

            $pengajar = DB::table('dosen_pengajar_kelas_kuliah')
                ->select('id_dosen')
                ->distinct()
                ->whereRaw('left(id_semester,4) = ?', [(string) $year]);

            if ($scopeProdiIds !== null) {
                $homebase->whereIn('id_prodi', $scopeProdiIds);
                $pengajar->whereIn('id_prodi', $scopeProdiIds);
            }

            return $homebase->pluck('id_dosen')
                ->merge($pengajar->pluck('id_dosen'))
                ->map(fn (mixed $id): string => (string) $id)
                ->filter(fn (string $id): bool => $id !== '')
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * Judul penelitian dari sister_detail_penelitian, dikelompokkan per
     * tahun_pelaksanaan dan dideduplikasi per id (lihat docblock kelas).
     *
     * @param  array<int, int>  $years
     * @return array<int, array<int, array{dana_dikti: float, dana_perguruan_tinggi: float, dana_institusi_lain: float, jenis_skim: ?string, anggota_ids: array<int, string>}>>
     */
    private function penelitianRows(array $years): array
    {
        $cacheKey = 'sapto:penelitian-dtps:penelitian:v1:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);

            $byId = [];

            foreach (DB::table('sister_detail_penelitian')
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
            ['label' => 'Total judul penelitian DTPS (TS-2..TS)', 'value' => array_sum($distinctJudul)],
            ['label' => 'Judul penelitian DTPS (TS)', 'value' => $distinctJudul['ts']],
            ['label' => 'Judul penelitian DTPS (TS-1)', 'value' => $distinctJudul['ts_1']],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:penelitian-dtps:years:v1', $this->cacheTtl(), function (): array {
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
            'name' => 'sister_detail_penelitian & list_penugasan_dosen (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}