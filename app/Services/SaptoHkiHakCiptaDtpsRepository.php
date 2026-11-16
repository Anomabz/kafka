<?php

namespace App\Services;

use App\Services\Concerns\ResolvesPublikasiDtpsRoster;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.b.7) Luaran Penelitian/PkM Lainnya -- Bagian II: HKI (Hak Cipta,
 * Desain Produk Industri, Perlindungan Varietas Tanaman, Desain Tata Letak
 * Sirkuit Terpadu, dll.).
 *
 * Alur:
 *  1. Pilih prodi.
 *  2. DTPS per tahun TS-2..TS lewat ResolvesPublikasiDtpsRoster -- definisi
 *     sama dengan Tabel 3.a.1, 3.b.4 & 3.b.7 Bagian I (homebase, ikatan kerja
 *     tetap, id_prodi terdaftar di program_studi). Semua jenjang.
 *  3. HKI dari sister_detail_kekayaan_intelektual: id_jenis_publikasi 43 (Hak
 *     cipta nasional) & 44 (Hak cipta internasional), SEMUA kategori capaian
 *     luaran. Hak cipta atas aplikasi/produk (kategori 1, TTG) tetap HKI, jadi
 *     masuk di sini, bukan Bagian III.
 *     SISTER tidak punya kode untuk Desain Produk Industri, Perlindungan
 *     Varietas Tanaman, maupun Desain Tata Letak Sirkuit Terpadu -- butir
 *     itu tidak ada sumbernya. "Karya desain" (55) bukan pendaftaran HKI ->
 *     Bagian III.
 *  4. detail.id_list -> sister_list_kekayaan_intelektual.id -> id_sdm.
 *  5. Cocokkan id_sdm dengan DTPS prodi (id_dosen = id_sdm), tampilkan HKI.
 *
 * Deduplikasi per (tahun, judul ternormalisasi), bukan per detail.id:
 * terverifikasi 816 id hanya 786 judul -- ciptaan yang sama diinput ulang
 * tiap pencipta dengan id berbeda. id_sdm semua record digabung. Judul sama
 * di tahun berbeda tetap dipisah (judul generik, mis. "Modul Praktikum").
 * nomor_paten (nomor pencatatan) hampir selalu kosong, jadi tidak dipakai
 * sebagai kunci.
 */
class SaptoHkiHakCiptaDtpsRepository
{
    use ResolvesPublikasiDtpsRoster;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    /** Kode id_jenis_publikasi Hak Cipta (nasional/internasional). */
    private const HAK_CIPTA_CODES = ['43', '44'];

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
                'Tabel 3.b.7 Bagian II disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan daftar HKI (Hak Cipta, Desain Produk Industri, dll.).');
        }

        $years = [$ts - 2, $ts - 1, $ts];

        // 1) DTPS per tahun (definisi bersama 3.a.1/3.b.4/3.b.7-I), semua jenjang.
        $dtpsIdsByYear = [];
        foreach ($years as $year) {
            $dtpsIdsByYear[$year] = empty($scopeProdiIds)
                ? []
                : array_flip($this->publikasiDtpsIds($year, $scopeProdiIds, []));
        }

        // 2) HKI per (tahun, judul) + seluruh id_sdm penciptanya.
        $luaranRows = $this->luaranRows($years);

        // 3) Cocokkan id_sdm dengan DTPS prodi.
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
            'table_name' => 'Luaran Penelitian/PkM Lainnya - HKI (Hak Cipta, Desain Produk Industri, dll.)',
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
     * Hak cipta dari sister_detail_kekayaan_intelektual, dikelompokkan per
     * (tahun, judul ternormalisasi). id_sdm dari semua record + baris list
     * yang terhubung digabung.
     *
     * @param  array<int, int>  $years
     * @return array<int, array<int, array{luaran: string, keterangan: string, id_sdm_list: array<int, string>}>>
     */
    private function luaranRows(array $years): array
    {
        $cacheKey = 'sapto:hki-hak-cipta-dtps:luaran:v3:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);
            $yearPlaceholders = implode(',', array_fill(0, count($yearStrings), '?'));

            $groups = [];

            $query = DB::table('sister_detail_kekayaan_intelektual as d')
                ->join('sister_list_kekayaan_intelektual as l', 'l.id', '=', 'd.id_list')
                ->select(
                    'd.id',
                    'd.judul',
                    'd.tanggal',
                    'd.jenis_publikasi',
                    'd.nomor_paten',
                    'd.pemberi_paten',
                    'l.id_sdm'
                )
                ->whereIn('d.id_jenis_publikasi', self::HAK_CIPTA_CODES)
                ->whereRaw("substr(d.tanggal,1,4) in ({$yearPlaceholders})", $yearStrings)
                ->orderBy('d.id');

            foreach ($query->cursor() as $row) {
                $year = (int) substr((string) $row->tanggal, 0, 4);
                $key = $year.'|'.$this->judulKey($row->judul, (string) $row->id);

                $groups[$key] ??= [
                    'year' => $year,
                    'judul' => trim((string) $row->judul),
                    'jenis' => [],
                    'nomor' => '',
                    'pemberi' => '',
                    'id_sdm_list' => [],
                ];

                $jenis = trim((string) ($row->jenis_publikasi ?? ''));
                if ($jenis !== '') {
                    $groups[$key]['jenis'][$jenis] = $jenis;
                }

                // Ambil nomor/pemberi dari record mana pun yang mengisinya.
                $nomor = trim((string) ($row->nomor_paten ?? ''));
                if ($groups[$key]['nomor'] === '' && $nomor !== '') {
                    $groups[$key]['nomor'] = $nomor;
                }

                $pemberi = trim((string) ($row->pemberi_paten ?? ''));
                if ($groups[$key]['pemberi'] === '' && $pemberi !== '') {
                    $groups[$key]['pemberi'] = $pemberi;
                }

                $idSdm = trim((string) ($row->id_sdm ?? ''));
                if ($idSdm !== '') {
                    $groups[$key]['id_sdm_list'][$idSdm] = $idSdm;
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
     * @param  array{jenis: array<string, string>, nomor: string, pemberi: string}  $group
     */
    private function keterangan(array $group): string
    {
        $parts = array_filter([
            implode(' / ', $group['jenis']),
            $group['nomor'] !== '' ? "No. {$group['nomor']}" : '',
            $group['pemberi'],
        ], fn (string $part): bool => $part !== '');

        return implode(', ', $parts);
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
            'table_name' => 'Luaran Penelitian/PkM Lainnya - HKI (Hak Cipta, Desain Produk Industri, dll.)',
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
        return Cache::remember('sapto:hki-hak-cipta-dtps:years:v1', $this->cacheTtl(), function (): array {
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