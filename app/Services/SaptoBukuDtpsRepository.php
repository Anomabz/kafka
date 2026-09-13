<?php

namespace App\Services;

use App\Services\Concerns\ResolvesPublikasiDtpsRoster;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.b.7) Luaran Penelitian/PkM Lainnya -- Bagian IV: Buku ber-ISBN,
 * Book Chapter. Beda bentuk dari 3.b.2/3.b.3/3.b.4 (matriks hitung per
 * kategori/tahun): ini tabel LISTING, satu baris per judul, tanpa baris
 * "Jumlah".
 *
 * Sumber: sister_detail_publikasi (mandiri, tidak perlu join ke
 * sister_list_publikasi -- setiap baris sudah punya kolom `penulis` berisi
 * JSON daftar penulis + id_sdm, sama seperti `anggota` di
 * sister_detail_penelitian/sister_detail_pengabdian). Baris difilter
 * id_jenis_publikasi 11/12/13/14/15 (Monograf, Buku referensi, Buku lainnya,
 * Book chapter nasional/internasional -- kode Bagian IV, lihat BOOK_CODES)
 * dan tahun TS-2..TS. Label buat kolom "Keterangan" diambil dari tabel
 * referensi jenis_publikasi (id -> nama), bukan di-hardcode.
 *
 * "DTPS" pakai roster {@see ResolvesPublikasiDtpsRoster} yang sama dengan
 * Tabel 3.b.4 (sister_list_penugasan, bukan list_penugasan_dosen yang
 * dipakai 3.b.2/3.b.3 -- keluarga publikasi SISTER beda pipeline sync dari
 * keluarga penelitian/PkM). Roster dihitung per tahun (TS-2/TS-1/TS) dan
 * dicocokkan terhadap tahun publikasi baris itu sendiri. Tabel ini tidak
 * dibagi akademik/vokasi seperti 3.b.4 -- jenjangFilter gabungan kedua
 * varian supaya benar untuk prodi jenjang apapun.
 */
class SaptoBukuDtpsRepository
{
    use ResolvesPublikasiDtpsRoster;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    /**
     * Kode id_jenis_publikasi yang termasuk Bagian IV (Buku ber-ISBN, Book
     * Chapter) -- aturan tabel ini, bukan berasal dari tabel jenis_publikasi
     * (yang cuma daftar semua kode+nama, tidak mengelompokkan per bagian
     * SPS). Label buat kolom "Keterangan" diambil dari tabel jenis_publikasi
     * lewat {@see jenisLabels()}, bukan di-hardcode di sini.
     */
    private const BOOK_CODES = ['11', '12', '13', '14', '15'];

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
            $dtpsIdsByYear[$year] = $scopeProdiIds === [] ? [] : $this->publikasiDtpsIds($year, $scopeProdiIds, $jenjang);
        }

        $bukuRows = $this->bukuRows($years);

        $items = [];
        foreach ($years as $year) {
            $dtpsIds = array_flip($dtpsIdsByYear[$year]);

            foreach ($bukuRows[$year] ?? [] as $row) {
                if (! $this->hasDtpsAuthor($row['id_sdm_list'], $dtpsIds)) {
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
     * Jenjang gabungan varian akademik (SaptoPublikasiIlmiahDtpsRepository)
     * + vokasi (SaptoPagelaranDtpsRepository) -- tabel ini tidak dibagi
     * seperti 3.b.4, jadi harus cocok untuk prodi jenjang apapun.
     *
     * @return array<int, string>
     */
    private function jenjangFilter(): array
    {
        return ['S1', 'S2', 'S3', 'D3', 'D4', 'Sarjana Terapan', 'Magister Terapan', 'Doktor Terapan'];
    }

    /**
     * Judul buku/book chapter dari sister_detail_publikasi, dedup per id,
     * dikelompokkan per tahun.
     *
     * @param  array<int, int>  $years
     * @return array<int, array<int, array{luaran: string, keterangan: string, id_sdm_list: array<int, string>}>>
     */
    private function bukuRows(array $years): array
    {
        $cacheKey = 'sapto:buku-dtps:buku:v1:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);
            $yearPlaceholders = implode(',', array_fill(0, count($yearStrings), '?'));
            $labels = $this->jenisLabels();

            $byId = [];

            foreach (DB::table('sister_detail_publikasi')
                ->select('id', 'id_jenis_publikasi', 'judul', 'judul_artikel', 'penerbit', 'isbn', 'tanggal', 'penulis')
                ->whereIn('id_jenis_publikasi', self::BOOK_CODES)
                ->whereRaw("substr(tanggal,1,4) in ({$yearPlaceholders})", $yearStrings)
                ->cursor() as $row) {
                $byId[(string) $row->id] ??= $row;
            }

            $byYear = array_fill_keys($years, []);

            foreach ($byId as $row) {
                $year = (int) substr((string) $row->tanggal, 0, 4);

                if (! isset($byYear[$year])) {
                    continue;
                }

                $byYear[$year][] = [
                    'luaran' => $this->luaranTitle($row),
                    'keterangan' => $this->keterangan($row, $labels),
                    'id_sdm_list' => $this->dosenIdsFromPenulis($row->penulis),
                ];
            }

            return $byYear;
        });
    }

    /**
     * Label id_jenis_publikasi -> nama, dari tabel referensi jenis_publikasi
     * (sinkronisasi lokal, 32 kode). Dicache terpisah dari daftar buku itu
     * sendiri karena jarang berubah.
     *
     * @return array<string, string> id (string) => nama
     */
    private function jenisLabels(): array
    {
        return Cache::remember('sapto:jenis-publikasi:v1', $this->cacheTtl(), function (): array {
            return DB::table('jenis_publikasi')
                ->pluck('nama', 'id')
                ->mapWithKeys(fn (string $nama, int|string $id): array => [(string) $id => $nama])
                ->all();
        });
    }

    private function luaranTitle(object $row): string
    {
        $jenis = (string) $row->id_jenis_publikasi;
        $judulArtikel = trim((string) ($row->judul_artikel ?? ''));

        if (in_array($jenis, ['14', '15'], true) && $judulArtikel !== '') {
            return "{$judulArtikel} (dalam buku: {$row->judul})";
        }

        return (string) $row->judul;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function keterangan(object $row, array $labels): string
    {
        $parts = [$labels[(string) $row->id_jenis_publikasi] ?? 'Lainnya'];

        $penerbit = trim((string) ($row->penerbit ?? ''));
        if ($penerbit !== '') {
            $parts[] = $penerbit;
        }

        $isbn = trim((string) ($row->isbn ?? ''));
        if ($isbn !== '') {
            $parts[] = "ISBN {$isbn}";
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
            'name' => 'sister_detail_publikasi, jenis_publikasi & sister_list_penugasan (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}
