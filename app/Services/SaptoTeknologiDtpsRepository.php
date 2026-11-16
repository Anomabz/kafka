<?php

namespace App\Services;

use App\Services\Concerns\ResolvesPublikasiDtpsRoster;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.b.7) Luaran Penelitian/PkM Lainnya -- Bagian III: Teknologi Tepat
 * Guna, Produk (Terstandarisasi/Tersertifikasi), Karya Seni, Rekayasa
 * Sosial. Tabel LISTING: satu baris per judul, tanpa baris "Jumlah".
 *
 * Sumber: sister_detail_kekayaan_intelektual. Baris yang masuk:
 * - id_jenis_publikasi 29 (Penemuan teknologi), 51-54 (Rancangan dan karya
 *   seni monumental/rupa/kriya/pertunjukan), 55 (Karya desain), 56 (Karya
 *   sastra) -- kategori capaian luaran apa pun; ATAU
 * - id_kategori_capaian_luaran = '1' (Produk Teknologi Tepat Guna) dengan
 *   jenis lain, mis. 71 "Hasil penelitian yang tidak dipublikasikan".
 * Paten/hak cipta (41-44) SELALU dikecualikan apa pun kategorinya -- sudah
 * masuk Bagian I/II.
 *
 * Catatan: kategori_capaian_luaran dipilih bebas oleh dosen (ada paten
 * berkategori "Publikasi", hak cipta berkategori "Jenis Luaran Lainnya"),
 * jadi id_jenis_publikasi tetap acuan utama; kategori 1 hanya pelengkap.
 * "Rekayasa Sosial" tidak punya kode jenis maupun kategori di SISTER --
 * isi manual.
 *
 * DTPS: roster ResolvesPublikasiDtpsRoster per tahun (TS-2/TS-1/TS), definisi
 * sama dengan Tabel 3.a.1, 3.b.4 & 3.b.7 Bagian I/II/IV. Berlaku untuk semua
 * prodi termasuk Profesi dan Spesialis.
 *
 * Penulis: gabungan id_sdm dari sister_list_kekayaan_intelektual (lewat
 * detail.id_list, sama dengan Bagian I/II) DAN dosen di JSON `penulis` --
 * keduanya terverifikasi lengkap untuk data Bagian III.
 *
 * Deduplikasi per (tahun, judul ternormalisasi), sama dengan Bagian II:
 * karya yang diinput ulang tiap pencipta dengan id berbeda digabung jadi satu
 * baris. Dedup hanya di dalam tabel satu prodi.
 */
class SaptoTeknologiDtpsRepository
{
    use ResolvesPublikasiDtpsRoster;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    /** Kode id_jenis_publikasi teknologi & karya seni Bagian III. */
    private const KARYA_CODES = ['29', '51', '52', '53', '54', '55', '56'];

    /** Paten & hak cipta -- milik Bagian I/II, selalu dikecualikan. */
    private const HKI_CODES = ['41', '42', '43', '44'];

    private const KATEGORI_TEKNOLOGI_TEPAT_GUNA = '1';

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'luaran', 'label' => 'Luaran Penelitian dan PkM', 'align' => 'left'],
        ['key' => 'tahun', 'label' => 'Tahun (YYYY)', 'align' => 'left'],
        ['key' => 'keterangan', 'label' => 'Keterangan', 'align' => 'left'],
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();

        if ($programStudi === null) {
            return $this->noticeEnvelope($ts, $fakultas, $programStudi,
                'Tabel 3.b.7 Bagian III disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan daftar teknologi tepat guna/produk/karya seni/rekayasa sosial.');
        }

        $years = [$ts - 2, $ts - 1, $ts];
        $jenjang = $this->jenjangFilter();

        $dtpsIdsByYear = [];
        foreach ($years as $year) {
            $dtpsIdsByYear[$year] = $scopeProdiIds === []
                ? []
                : array_flip($this->publikasiDtpsIds($year, $scopeProdiIds, $jenjang));
        }

        $luaranRows = $this->luaranRows($years);

        $items = [];
        foreach ($years as $year) {
            foreach ($luaranRows[$year] ?? [] as $row) {
                if (! $this->hasDtpsAuthor($row['id_sdm_list'], $dtpsIdsByYear[$year])) {
                    continue;
                }

                $items[] = [
                    'luaran' => $row['luaran'],
                    'tahun' => $year,
                    'keterangan' => $row['keterangan'],
                ];
            }
        }

        usort($items, fn (array $a, array $b): int => $b['tahun'] <=> $a['tahun'] ?: $a['luaran'] <=> $b['luaran']);

        $rows = collect($items)
            ->values()
            ->map(fn (array $row, int $index): array => ['no' => $index + 1] + $row)
            ->all();

        return [
            'table_code' => '3.b.7',
            'table_name' => 'Luaran Penelitian/PkM Lainnya - Teknologi Tepat Guna, Produk, Karya Seni, Rekayasa Sosial',
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
     * Jenjang prodi yang tercatat di program_studi.nama_jenjang_pendidikan --
     * tabel ini tidak dibagi akademik/vokasi, berlaku untuk semua prodi
     * termasuk Profesi dan Spesialis.
     *
     * @return array<int, string>
     */
    private function jenjangFilter(): array
    {
        return ['D3', 'S1', 'S2', 'S3', 'Profesi', 'Sp-1', 'Sp-2'];
    }

    /**
     * Karya Bagian III dari sister_detail_kekayaan_intelektual, dikelompokkan
     * per (tahun, judul ternormalisasi). id_sdm dari list + JSON penulis
     * semua record digabung.
     *
     * @param  array<int, int>  $years
     * @return array<int, array<int, array{luaran: string, keterangan: string, id_sdm_list: array<int, string>}>>
     */
    private function luaranRows(array $years): array
    {
        $cacheKey = 'sapto:teknologi-dtps:luaran:v3:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);
            $yearPlaceholders = implode(',', array_fill(0, count($yearStrings), '?'));

            $groups = [];
            $parsedPenulis = [];

            $query = DB::table('sister_detail_kekayaan_intelektual as d')
                ->leftJoin('sister_list_kekayaan_intelektual as l', 'l.id', '=', 'd.id_list')
                ->select(
                    'd.id',
                    'd.judul',
                    'd.tanggal',
                    'd.id_kategori_capaian_luaran',
                    'd.jenis_publikasi',
                    'd.penulis',
                    'l.id_sdm'
                )
                ->where(function ($query): void {
                    $query->whereIn('d.id_jenis_publikasi', self::KARYA_CODES)
                        ->orWhere('d.id_kategori_capaian_luaran', self::KATEGORI_TEKNOLOGI_TEPAT_GUNA);
                })
                ->whereNotIn('d.id_jenis_publikasi', self::HKI_CODES)
                ->whereRaw("substr(d.tanggal,1,4) in ({$yearPlaceholders})", $yearStrings)
                ->orderBy('d.id');

            foreach ($query->cursor() as $row) {
                $year = (int) substr((string) $row->tanggal, 0, 4);
                $key = $year.'|'.$this->judulKey($row->judul, (string) $row->id);

                $groups[$key] ??= [
                    'year' => $year,
                    'judul' => trim((string) $row->judul),
                    'jenis' => [],
                    'ttg' => false,
                    'id_sdm_list' => [],
                ];

                $jenis = trim((string) ($row->jenis_publikasi ?? ''));
                if ($jenis !== '') {
                    $groups[$key]['jenis'][$jenis] = $jenis;
                }

                if ((string) $row->id_kategori_capaian_luaran === self::KATEGORI_TEKNOLOGI_TEPAT_GUNA) {
                    $groups[$key]['ttg'] = true;
                }

                $idSdm = trim((string) ($row->id_sdm ?? ''));
                if ($idSdm !== '') {
                    $groups[$key]['id_sdm_list'][$idSdm] = $idSdm;
                }

                // JSON penulis cukup diurai sekali per id detail.
                $detailId = (string) $row->id;
                if (! isset($parsedPenulis[$detailId])) {
                    $parsedPenulis[$detailId] = true;
                    foreach ($this->dosenIdsFromPenulis($row->penulis) as $id) {
                        $groups[$key]['id_sdm_list'][$id] = $id;
                    }
                }
            }

            $byYear = array_fill_keys($years, []);

            foreach ($groups as $group) {
                if (! isset($byYear[$group['year']])) {
                    continue;
                }

                $byYear[$group['year']][] = [
                    'luaran' => $group['judul'],
                    'keterangan' => $this->keterangan($group),
                    'id_sdm_list' => array_values($group['id_sdm_list']),
                ];
            }

            return $byYear;
        });
    }

    /**
     * Kunci deduplikasi judul: lowercase + spasi dirapikan (setara
     * LOWER(TRIM(REGEXP_REPLACE(judul,'[[:space:]]+',' '))) di MySQL).
     * Judul kosong jatuh kembali ke id.
     */
    private function judulKey(?string $judul, string $id): string
    {
        $normal = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $judul)));

        return $normal === '' ? 'id:'.$id : md5($normal);
    }

    /**
     * Jenis publikasi, ditambah "Produk Teknologi Tepat Guna" bila salah satu
     * record berkategori TTG (kategori lain tidak informatif, lihat docblock).
     *
     * @param  array{jenis: array<string, string>, ttg: bool}  $group
     */
    private function keterangan(array $group): string
    {
        $parts = [];

        if ($group['ttg']) {
            $parts[] = 'Produk Teknologi Tepat Guna';
        }

        if ($group['jenis'] !== []) {
            $parts[] = implode(' / ', $group['jenis']);
        }

        return implode(', ', $parts);
    }

    /**
     * @return array<int, string>
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
            ->filter(fn (mixed $penulis): bool => is_array($penulis) && ($penulis['jenis'] ?? null) === 'Dosen' && ! empty($penulis['id_sdm']))
            ->map(fn (array $penulis): string => (string) $penulis['id_sdm'])
            ->values()
            ->all();
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
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, int $ts): array
    {
        $tsCount = collect($rows)->where('tahun', $ts)->count();

        return [
            ['label' => 'Total judul (TS-2..TS)', 'value' => count($rows)],
            ['label' => 'Judul (TS)', 'value' => $tsCount],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    private function noticeEnvelope(int $ts, ?array $fakultas, ?array $programStudi, string $notice): array
    {
        return [
            'table_code' => '3.b.7',
            'table_name' => 'Luaran Penelitian/PkM Lainnya - Teknologi Tepat Guna, Produk, Karya Seni, Rekayasa Sosial',
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
        return Cache::remember('sapto:teknologi-dtps:years:v1', $this->cacheTtl(), function (): array {
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
            'name' => 'sister_detail_kekayaan_intelektual, sister_list_kekayaan_intelektual & list_penugasan_dosen (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}