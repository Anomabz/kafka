<?php

namespace App\Services;

use App\Services\Concerns\ResolvesPublikasiDtpsRoster;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.b.6) Produk/Jasa DTPS yang Diadopsi oleh Industri/Masyarakat.
 *
 * Sumber: sister_detail_kekayaan_intelektual. Baris = HKI non-paten
 * (hak cipta, desain produk, karya seni, dll.) yang merupakan luaran
 * penelitian/PkM DTPS. id_jenis_publikasi 41/42 (Paten nasional/internasional)
 * tidak masuk di sini karena paten masuk Tabel 3.b.7 Bagian I.
 *
 * "DTPS" ditentukan lewat sister_list_penugasan (unit_kerja homebase per id_sdm),
 * konsisten dengan keluarga publikasi DTPS lainnya.
 *
 * Filter scope prodi dilakukan lewat unit_kerja di sister_list_penugasan
 * (bukan id_prodi), konsisten dengan keluarga publikasi DTPS lainnya.
 */
class SaptoProdukJasaDtpsRepository
{
    use ResolvesPublikasiDtpsRoster;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    /** id_jenis_publikasi yang dikategorikan sebagai HKI non-paten. */
    private const NON_PATEN_JENIS = [
        '43', // Hak cipta nasional
        '44', // Hak cipta internasional
        '51', // Rancangan dan karya seni monumental
        '52', // Rancangan dan karya seni rupa
        '54', // Rancangan dan karya seni pertunjukan
        '55', // Karya desain
        '56', // Karya sastra
        '71', // Hasil penelitian/pemikiran yang tidak dipublikasikan
        '72', // Hasil kerjasama industri yang tidak dipublikasikan
        '29', // Penemuan teknologi
    ];

    /** Mapping id_jenis_publikasi -> label singkat untuk kolom "Keterangan". */
    private const JENIS_LABELS = [
        '43' => 'Hak Cipta Nasional',
        '44' => 'Hak Cipta Internasional',
        '51' => 'Rancangan & Karya Seni Monumental',
        '52' => 'Rancangan & Karya Seni Rupa',
        '54' => 'Rancangan & Karya Seni Pertunjukan',
        '55' => 'Karya Desain',
        '56' => 'Karya Sastra',
        '71' => 'Hasil Penelitian/Pemikiran (tdk dipublikasikan)',
        '72' => 'Hasil Kerjasama Industri (tdk dipublikasikan)',
        '29' => 'Penemuan Teknologi',
    ];

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'nama_dosen', 'label' => 'Nama Dosen', 'align' => 'left'],
        ['key' => 'nama_produk', 'label' => 'Nama Produk/Jasa', 'align' => 'left'],
        ['key' => 'deskripsi', 'label' => 'Deskripsi', 'align' => 'left'],
        ['key' => 'bukti', 'label' => 'Bukti', 'align' => 'left'],
        ['key' => 'tahun', 'label' => 'Tahun', 'align' => 'left'],
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakuttasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakult] = $this->validateScope($programStudis, $fakuttasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakuttasId, $programStudiId);

        $ts ??= $this->ts();

        if ($programStudi === null) {
            return $this->noticeEnvelope($ts, $fakult,
                'Tabel 3.b.6 disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan daftar produk/jasa DTPS.');
        }

        $years = [$ts - 2, $ts - 1, $ts];
        $jenjang = ['S1', 'S2', 'S3', 'D3', 'D4', 'Sarjana Terapan', 'Magister Terapan', 'Doktor Terapan'];

        $dtpsIdsByYear = [];
        foreach ($years as $year) {
            $dtpsIdsByYear[$year] = $scopeProdiIds === [] ? [] : $this->publikasiDtpsIds($year, $scopeProdiIds, $jenjang);
        }

        $luaranRows = $this->loadLuaran($years);
        $items = $this->filterAndMap($luaranRows, $dtpsIdsByYear);

        usort($items, fn (array $a, array $b): int => $b['tahun'] <=> $a['tahun'] ?: $a['nama_produk'] <=> $b['nama_produk']);

        $rows = [];
        foreach ($items as $index => $item) {
            $rows[] = ['no' => $index + 1] + $item;
        }

        return [
            'table_code' => '3.b.6',
            'table_name' => 'Produk/Jasa DTPS yang Diadopsi oleh Industri/Masyarakat',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakult' => $fakult,
            'program_studi' => $programStudi,
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'metrics' => $this->metrics($rows, $ts),
            'notice' => null,
            'source' => [
                'name' => 'sister_detail_kekayaan_intelektual & sister_list_penugasan (sinkronisasi lokal)',
                'partial' => true,
            ],
        ];
    }

    /**
     * @param  list<int>  $years
     * @return array<int, array<int, array{luaran: string, keterangan: string, bukti: string, id_sdm_list: list<string>}>>
     */
    private function loadLuaran(array $years): array
    {
        $cacheKey = 'sapto:produk-jasa-dtps:luaran:v1:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);
            $yearPlaceholders = implode(',', array_fill(0, count($yearStrings), '?'));

            $byId = [];

            foreach (DB::table('sister_detail_kekayaan_intelektual')
                ->select('id', 'judul', 'tanggal', 'jenis_publikasi', 'keterangan')
                ->whereIn('id_jenis_publikasi', self::NON_PATEN_JENIS)
                ->whereRaw("substr(tanggal,1,4) IN ({$yearPlaceholders})", $yearStrings)
                ->cursor() as $row) {
                $byId[(string) $row->id] ??= $row;
            }

            $byYear = array_fill_keys($years, []);

            foreach ($byId as $row) {
                $year = (int) substr((string) ($row->tanggal ?? ''), 0, 4);

                if ($year === 0) {
                    continue;
                }

                if (! isset($byYear[$year])) {
                    continue;
                }

                $byYear[$year][] = [
                    'luaran' => (string) ($row->judul ?? ''),
                    'keterangan' => $this->buildDeskripsi($row),
                    'bukti' => $this->buildBukti($row),
                    'id_sdm_list' => [],
                    'raw_penulis' => null,
                ];
            }

            // Load penulis JSON
            $ids = array_keys($byId);
            if ($ids === []) {
                return $byYear;
            }

            $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
            $penulisById = [];

            foreach (DB::table('sister_detail_kekayaan_intelektual')
                ->select('id', 'penulis')
                ->whereRaw("id IN ({$idPlaceholders})", $ids)
                ->cursor() as $row) {
                $penulisById[(string) $row->id] = $row->penulis;
            }

            foreach ($byYear as $year => $items) {
                foreach ($items as &$item) {
                    $item['id_sdm_list'] = $this->dosenIdsFromPenulis($penulisById[$item['luaran']] ?? null);
                }
            }

            return $byYear;
        });
    }

    private function buildDeskripsi(object $row): string
    {
        $jenis = self::JENIS_LABELS[(string) ($row->jenis_publikasi ?? '')] ?? trim((string) ($row->jenis_publikasi ?? ''));
        $ket = trim((string) ($row->keterangan ?? ''));

        if ($ket !== '' && $ket !== 'NULL') {
            return "{$jenis}: {$ket}";
        }

        return $jenis ?: '-';
    }

    private function buildBukti(object $row): string
    {
        $ket = trim((string) ($row->keterangan ?? ''));

        if ($ket === '' || $ket === 'NULL') {
            return '-';
        }

        // If keterangan contains a URL, use it as bukti
        if (filter_var($ket, FILTER_VALIDATE_URL)) {
            return $ket;
        }

        return $ket;
    }

    /**
     * @return list<string>
     */
    private function dosenIdsFromPenulis(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn (mixed $p): bool => is_array($p) && ($p['jenis'] ?? null) === 'Dosen' && ! empty($p['id_sdm']))
            ->map(fn (array $p): string => (string) $p['id_sdm'])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<int, array{luaran: string, keterangan: string, bukti: string, id_sdm_list: list<string>}>>  $luaranRows
     * @param  array<int, array<int, string>>  $dtpsIdsByYear
     * @return array<int, array{nama_dosen: string, nama_produk: string, deskripsi: string, bukti: string, tahun: int}>
     */
    private function filterAndMap(array $luaranRows, array $dtpsIdsByYear): array
    {
        $allDtpsIds = [];

        foreach ($dtpsIdsByYear as $ids) {
            foreach ($ids as $id) {
                $allDtpsIds[(string) $id] = true;
            }
        }

        $items = [];
        $dosenNames = []; // Cache id_sdm -> nama

        foreach ($luaranRows as $year => $records) {
            $dtpsIds = array_flip($dtpsIdsByYear[$year] ?? []);

            foreach ($records as $row) {
                $idSdmList = $row['id_sdm_list'] ?? [];
                $hasDtps = false;

                foreach ($idSdmList as $idSdm) {
                    if (isset($allDtpsIds[(string) $idSdm])) {
                        $hasDtps = true;
                        break;
                    }
                }

                if (! $hasDtps) {
                    continue;
                }

                $items[] = [
                    'nama_produk' => $row['luaran'] ?: '-',
                    'deskripsi' => $row['keterangan'] ?: '-',
                    'bukti' => $row['bukti'] ?: '-',
                    'tahun' => $year,
                    'id_sdm_list' => $idSdmList,
                ];
            }
        }

        // Deduplicate by luaran (same HKI can appear in multiple years)
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $key = $item['nama_produk'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, int $ts): array
    {
        $tsCount = collect($rows)->where('tahun', $ts)->count();

        return [
            ['label' => 'Total produk/jasa (TS-2..TS)', 'value' => count($rows)],
            ['label' => 'Produk/jasa (TS)', 'value' => $tsCount],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    private function noticeEnvelope(int $ts, ?array $fakult, string $notice): array
    {
        return [
            'table_code' => '3.b.6',
            'table_name' => 'Produk/Jasa DTPS yang Diadopsi oleh Industri/Masyarakat',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakult' => $fakult,
            'program_studi' => null,
            'columns' => [],
            'rows' => [],
            'metrics' => [],
            'notice' => $notice,
            'source' => ['name' => 'sister_detail_kekayaan_intelektual (sinkronisasi lokal)', 'partial' => true],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        return Cache::remember('sapto:produk-jasa-dtps:years:v1', $this->cacheTtl(), function (): array {
            $rows = DB::select("
                SELECT DISTINCT CAST(SUBSTR(nama_tahun_ajaran, 1, 4) AS UNSIGNED) AS thn
                FROM list_penugasan_dosen
                WHERE nama_tahun_ajaran IS NOT NULL
                  AND nama_tahun_ajaran <> ''
                ORDER BY thn DESC
                LIMIT 10
            ");

            return array_values(array_filter(
                array_map(fn (object $row): int => (int) $row->thn, $rows),
                fn (int $year): bool => $year >= 2000 && $year <= 2100
            ));
        });
    }
}
