<?php

namespace App\Services;

use App\Services\Concerns\ResolvesPublikasiDtpsRoster;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.b.7) Luaran Penelitian/PkM Lainnya -- Bagian IV: Buku ber-ISBN,
 * Book Chapter. Tabel LISTING: satu baris per judul, tanpa baris "Jumlah".
 *
 * Sumber utama: sister_list_publikasi (sama dengan Tabel 3.b.4), difilter
 * jenis_publikasi Monograf / Buku referensi / Buku lainnya / Book chapter
 * nasional / Book chapter internasional (kode 11-15 di referensi
 * jenis_publikasi). "Abstrak buku/pustaka" (28) bukan buku ber-ISBN -> tidak
 * dihitung.
 *
 * sister_detail_publikasi hanya PELENGKAP (LEFT JOIN per id) untuk penerbit,
 * ISBN, dan judul bab: terverifikasi baru ~60 dari ~1.340 buku 2023-2025 yang
 * tersinkron ke tabel detail, jadi tidak bisa jadi sumber utama. Buku tanpa
 * detail tetap tampil; keterangannya hanya berisi jenis publikasi (penerbit
 * & ISBN muncul otomatis setelah detailnya tersinkron).
 *
 * DTPS: roster ResolvesPublikasiDtpsRoster per tahun (TS-2/TS-1/TS), definisi
 * sama dengan Tabel 3.a.1 & 3.b.4. Tabel ini berlaku untuk semua prodi
 * (tidak dibagi akademik/vokasi), termasuk Profesi dan Spesialis.
 *
 * Deduplikasi per (tahun, judul ternormalisasi dari sister_list_publikasi):
 * buku/bab yang sama diinput ulang tiap penulis dengan id berbeda digabung
 * menjadi satu baris, id_sdm semua penulisnya dikumpulkan. Dedup hanya di
 * dalam tabel satu prodi -- buku bersama dosen prodi lain tetap tampil di
 * tabel prodi itu juga.
 */
class SaptoBukuDtpsRepository
{
    use ResolvesPublikasiDtpsRoster;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    /** Label jenis_publikasi di sister_list_publikasi yang termasuk Bagian IV. */
    private const BOOK_JENIS = [
        'Monograf',
        'Buku referensi',
        'Buku lainnya',
        'Book chapter nasional',
        'Book chapter internasional',
    ];

    private const CHAPTER_JENIS = ['Book chapter nasional', 'Book chapter internasional'];

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
                'Tabel 3.b.7 Bagian IV disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan daftar buku/book chapter.');
        }

        $years = [$ts - 2, $ts - 1, $ts];
        $jenjang = $this->jenjangFilter();

        $dtpsIdsByYear = [];
        foreach ($years as $year) {
            $dtpsIdsByYear[$year] = $scopeProdiIds === []
                ? []
                : array_flip($this->publikasiDtpsIds($year, $scopeProdiIds, $jenjang));
        }

        $bukuRows = $this->bukuRows($years);

        $items = [];
        foreach ($years as $year) {
            foreach ($bukuRows[$year] ?? [] as $row) {
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
            'table_name' => 'Luaran Penelitian/PkM Lainnya - Buku ber-ISBN, Book Chapter',
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
     * Buku/book chapter dari sister_list_publikasi (+ detail bila ada),
     * dikelompokkan per (tahun, judul ternormalisasi).
     *
     * @param  array<int, int>  $years
     * @return array<int, array<int, array{luaran: string, keterangan: string, id_sdm_list: array<int, string>}>>
     */
    private function bukuRows(array $years): array
    {
        $cacheKey = 'sapto:buku-dtps:buku:v3:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);
            $yearPlaceholders = implode(',', array_fill(0, count($yearStrings), '?'));

            $groups = [];

            $query = DB::table('sister_list_publikasi as l')
                ->leftJoin('sister_detail_publikasi as d', 'd.id', '=', 'l.id')
                ->select(
                    'l.id',
                    'l.id_sdm',
                    'l.judul',
                    'l.jenis_publikasi',
                    'l.tanggal',
                    'd.judul as judul_buku',
                    'd.judul_artikel',
                    'd.penerbit',
                    'd.isbn'
                )
                ->whereIn('l.jenis_publikasi', self::BOOK_JENIS)
                ->whereRaw("substr(l.tanggal,1,4) in ({$yearPlaceholders})", $yearStrings)
                ->orderBy('l.id');

            foreach ($query->cursor() as $row) {
                $year = (int) substr((string) $row->tanggal, 0, 4);
                $key = $year.'|'.$this->judulKey($row->judul, (string) $row->id);

                $groups[$key] ??= [
                    'year' => $year,
                    'judul' => trim((string) $row->judul),
                    'jenis' => [],
                    'judul_buku' => '',
                    'judul_artikel' => '',
                    'penerbit' => '',
                    'isbn' => '',
                    'id_sdm_list' => [],
                ];

                $group = &$groups[$key];

                $jenis = trim((string) ($row->jenis_publikasi ?? ''));
                if ($jenis !== '') {
                    $group['jenis'][$jenis] = $jenis;
                }

                // Lengkapi dari record mana pun yang punya detail.
                foreach (['judul_buku', 'judul_artikel', 'penerbit', 'isbn'] as $field) {
                    $value = trim((string) ($row->{$field} ?? ''));
                    if ($group[$field] === '' && $value !== '') {
                        $group[$field] = $value;
                    }
                }

                $idSdm = trim((string) ($row->id_sdm ?? ''));
                if ($idSdm !== '') {
                    $group['id_sdm_list'][$idSdm] = $idSdm;
                }

                unset($group);
            }

            $byYear = array_fill_keys($years, []);

            foreach ($groups as $group) {
                if (! isset($byYear[$group['year']])) {
                    continue;
                }

                $byYear[$group['year']][] = [
                    'luaran' => $this->luaranTitle($group),
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
     * Book chapter dengan detail lengkap: "judul bab (dalam buku: judul buku)".
     * Selain itu judul apa adanya dari sister_list_publikasi.
     *
     * @param  array<string, mixed>  $group
     */
    private function luaranTitle(array $group): string
    {
        $isChapter = array_intersect($group['jenis'], self::CHAPTER_JENIS) !== [];

        if ($isChapter && $group['judul_artikel'] !== '' && $group['judul_buku'] !== '') {
            return "{$group['judul_artikel']} (dalam buku: {$group['judul_buku']})";
        }

        return $group['judul'];
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function keterangan(array $group): string
    {
        $parts = [implode(' / ', $group['jenis']) ?: 'Lainnya'];

        if ($group['penerbit'] !== '') {
            $parts[] = $group['penerbit'];
        }

        if ($group['isbn'] !== '') {
            $parts[] = "ISBN {$group['isbn']}";
        }

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
            'table_name' => 'Luaran Penelitian/PkM Lainnya - Buku ber-ISBN, Book Chapter',
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
        return Cache::remember('sapto:buku-dtps:years:v1', $this->cacheTtl(), function (): array {
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
            'name' => 'sister_list_publikasi (+ sister_detail_publikasi bila ada) & list_penugasan_dosen (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}