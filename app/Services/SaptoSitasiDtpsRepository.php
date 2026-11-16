<?php

namespace App\Services;

use App\Services\Concerns\ResolvesPublikasiDtpsRoster;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.b.5) Karya Ilmiah DTPS yang Disitasi.
 *
 * Sumber: sister_detail_publikasi (detail artikel jurnal/prosiding). Baris
 * = artikel dengan DOI atau ISSN (artikel teridentifikasi). Satu artikel
 * bisa muncul berulang kali di sumber karena tiap penulis punya baris sendiri
 * -- dideduplikasi per id sebelum ditampilkan. "DTPS" ditentukan lewat
 * sister_list_penugasan (unit_kerja homebase per id_sdm).
 *
 * Kolom "Jumlah Sitasi" belum tersedia di database SISTER/PD UNSRI --
 * data sitasi memerlukan integrasi tambahan (CrossRef / Scopus API).
 * Kolom tetap ditampilkan; nilainya 0 sampai data tersedia.
 *
 * Filter scope prodi dilakukan lewat unit_kerja di sister_list_penugasan
 * (bukan id_prodi), konsisten dengan keluarga publikasi DTPS lainnya.
 */
class SaptoSitasiDtpsRepository
{
    use ResolvesPublikasiDtpsRoster;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    /**
     * id_jenis_publikasi dari sister_detail_publikasi yang menunjukkan
     * artikel jurnal atau prosiding -- hanya artikel teridentifikasi yang
     * layak tampil di tabel sitasi.
     */
    private const ARTIKEL_JENIS = [
        // Jurnal
        '21', // Jurnal nasional
        '22', // Jurnal nasional terakreditasi
        '23', // Jurnal internasional
        '24', // Jurnal internasional bereputasi
        '25', // Artikel ilmiah
        '26', // Makalah ilmiah
        '27', // Tulisan ilmiah
        // Prosiding
        '31', // Prosiding seminar nasional
        '32', // Prosiding seminar internasional
        '33', // Poster seminar nasional
        '34', // Poster seminar internasional
    ];

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'nama_dosen', 'label' => 'Nama Dosen', 'align' => 'left'],
        ['key' => 'judul_artikel', 'label' => 'Judul Artikel yang Disitasi', 'align' => 'left'],
        ['key' => 'nama_jurnal', 'label' => 'Nama Jurnal / Prosiding', 'align' => 'left'],
        ['key' => 'tahun', 'label' => 'Tahun', 'align' => 'left'],
        ['key' => 'jumlah_sitasi', 'label' => 'Jumlah Sitasi', 'align' => 'num'],
        ['key' => 'doi', 'label' => 'DOI', 'align' => 'left'],
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakult] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();
        $years = [$ts - 2, $ts - 1, $ts];
        $yearStrings = array_map(fn (int $y): string => (string) $y, $years);

        if ($programStudi === null) {
            return $this->noticeEnvelope($ts, $fakult,
                'Tabel 3.b.5 disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan daftar artikel yang disitasi.');
        }

        $dtpsIdsByYear = [];
        foreach ($years as $year) {
            $dtpsIdsByYear[$year] = $scopeProdiIds === [] ? [] : $this->publikasiDtpsIds($year, $scopeProdiIds, ['S1', 'S2', 'S3', 'D3', 'D4', 'Sarjana Terapan', 'Magister Terapan', 'Doktor Terapan']);
        }

        $rows = $this->loadArticles($yearStrings, $dtpsIdsByYear);

        return [
            'table_code' => '3.b.5',
            'table_name' => 'Karya Ilmiah DTPS yang Disitasi',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakult,
            'program_studi' => $programStudi,
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'metrics' => $this->metrics($rows, $ts),
            'notice' => 'Jumlah sitasi tidak tersedia di SISTER/PD UNSRI (perlu Scopus/Google Scholar). Daftar ini hanya artikel DTPS yang rinciannya ada di PD UNSRI, sebagai bahan: isi jumlah sitasinya dan hapus artikel yang tidak disitasi.',
            'source' => [
                'name' => 'sister_detail_publikasi & sister_list_penugasan (sinkronisasi lokal)',
                'partial' => true,
                // Kolom inti tabel (jumlah sitasi) kosong: bagi kesiapan, tabel
                // ini belum terisi walau ada baris kandidat.
                'template' => true,
            ],
        ];
    }

    /**
     * @param  list<string>  $yearStrings
     * @param  array<int, array<int, string>>  $dtpsIdsByYear
     * @return array<int, array<string, mixed>>
     */
    private function loadArticles(array $yearStrings, array $dtpsIdsByYear): array
    {
        // Kunci WAJIB memuat roster DTPS: sebelum 2026-09-30 hanya tahun, jadi
        // prodi pertama yang dibuka "mengunci" hasilnya untuk semua prodi lain.
        $cacheKey = 'sapto:sitasi-dtps:artikel:v2:'.md5(json_encode([$yearStrings, array_map(function (array $ids): array {
            sort($ids);

            return $ids;
        }, $dtpsIdsByYear)]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($yearStrings, $dtpsIdsByYear): array {
            $placeholders = implode(',', array_fill(0, count($yearStrings), '?'));

            // Deduplicate per id -- satu artikel punya beberapa baris (tiap penulis).
            // Ambil baris dengan urutan penulis pertama (urutan=1) sebagai representasi.
            $byId = [];

            foreach (DB::table('sister_detail_publikasi')
                ->select('id', 'id_jenis_publikasi', 'judul', 'judul_artikel', 'nama_jurnal',
                         'tanggal', 'doi', 'issn', 'e_issn', 'penulis')
                ->whereIn('id_jenis_publikasi', self::ARTIKEL_JENIS)
                ->whereRaw("substr(COALESCE(tanggal,''),1,4) IN ({$placeholders})", $yearStrings)
                ->cursor() as $row) {
                $id = (string) $row->id;

                if (isset($byId[$id])) {
                    continue;
                }

                $byId[$id] = $row;
            }

            $allDtpsIds = [];
            foreach ($dtpsIdsByYear as $ids) {
                foreach ($ids as $id) {
                    $allDtpsIds[(string) $id] = true;
                }
            }

            $rows = [];
            $no = 1;

            foreach ($byId as $row) {
                $authors = $this->authorsFromJson($row->penulis);
                $year = (int) substr((string) ($row->tanggal ?? ''), 0, 4);

                $dtpsAuthors = array_filter($authors, fn (array $a): bool => isset($allDtpsIds[(string) ($a['id_sdm'] ?? '')]));

                if ($dtpsAuthors === []) {
                    continue;
                }

                $namaDosen = implode(', ', array_unique(array_filter(array_column($dtpsAuthors, 'nama'))));
                $doi = trim((string) ($row->doi ?? ''));
                $issn = trim((string) (($row->issn ?? '') ?: ($row->{'e_issn'} ?? '')));

                $rows[] = [
                    'no' => $no++,
                    'nama_dosen' => $namaDosen ?: '-',
                    'judul_artikel' => trim((string) ($row->judul_artikel ?? '')) ?: (trim((string) ($row->judul ?? '')) ?: '-'),
                    'nama_jurnal' => trim((string) ($row->nama_jurnal ?? '')) ?: '-',
                    'tahun' => $year ?: '-',
                    // Tidak ada di sumber: null ("-"), bukan 0 yang terbaca "tidak disitasi".
                    'jumlah_sitasi' => null,
                    'doi' => $doi ?: ($issn ? "ISSN: {$issn}" : '-'),
                ];
            }

            return $rows;
        });
    }

    /**
     * @return array<int, array{nama: string, id_sdm: ?string, urutan: int}>
     */
    private function authorsFromJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        $authors = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $nama = trim((string) ($item['nama'] ?? ''));

            if ($nama === '') {
                continue;
            }

            $authors[] = [
                'nama' => $nama,
                'id_sdm' => isset($item['id_sdm']) ? (string) $item['id_sdm'] : null,
                'urutan' => (int) ($item['urutan'] ?? 999),
            ];
        }

        usort($authors, fn (array $a, array $b): int => $a['urutan'] <=> $b['urutan']);

        return $authors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, int $ts): array
    {
        return [
            ['label' => 'Artikel DTPS (kandidat, TS-2..TS)', 'value' => count($rows)],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    private function noticeEnvelope(int $ts, ?array $fakult, string $notice): array
    {
        return [
            'table_code' => '3.b.5',
            'table_name' => 'Karya Ilmiah DTPS yang Disitasi',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakult,
            'program_studi' => null,
            'columns' => [],
            'rows' => [],
            'metrics' => [],
            'notice' => $notice,
            'source' => ['name' => 'sister_detail_publikasi (sinkronisasi lokal)', 'partial' => true],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        return Cache::remember('sapto:sitasi-dtps:years:v1', $this->cacheTtl(), function (): array {
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
