<?php

namespace App\Services;

use App\Services\Concerns\ResolvesPublikasiDtpsRoster;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
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
 * Roster dari ResolvesPublikasiDtpsRoster = DTPS (definisi sama dengan Tabel
 * 3.a.1: list_penugasan_dosen homebase, ikatan kerja tetap, jenjang prodi
 * sesuai varian) DITAMBAH dosen yang mengajar kelas prodi itu pada tahun yang
 * sama (keputusan pengguna). Roster dihitung PER TAHUN kolom (TS-2/TS-1/TS):
 * publikasi dihitung di prodi tempat dosen ber-homebase atau mengajar pada
 * tahun tersebut. Kartu "Jumlah DTPS" tetap DTPS murni. Contoh:
 * DTPS S3 Ilmu Komputer baru ber-homebase di sana sejak 2024 (sebelumnya S1
 * Sistem Komputer / S2 Ilmu Komputer), jadi kolom 2023 prodi itu memang 0.
 *
 * Sumber publikasi: sister_list_publikasi (klaim BKD per dosen). Satu artikel
 * bisa tercatat berkali-kali: satu baris per penulis dengan `id` sama, ATAU
 * diinput ulang tiap penulis dengan `id` berbeda. Karena itu deduplikasi
 * memakai JUDUL yang dinormalisasi (lowercase, spasi dirapikan), bukan `id`:
 * satu judul dihitung sekali per kategori per tahun. Judul kosong jatuh
 * kembali ke `id`.
 *
 * Klasifikasi mengikuti jenis_publikasi apa adanya: judul yang tercatat di
 * dua jenis berbeda kategori (mis. prosiding + jurnal bereputasi) dihitung
 * di kedua baris -- disengaja, sesuai keputusan pengguna.
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
    use ResolvesSaptoTs;

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
     * Nilai jenjang prodi (program_studi.nama_jenjang_pendidikan) yang
     * dianggap DTPS untuk varian ini.
     *
     * @return array<int, string>
     */
    abstract protected function jenjangFilter(): array;

    public function detail(int $year, string $category, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);
        $roster = array_flip($scopeProdiIds === [] ? [] : $this->publikasiDtpsIds($year, $scopeProdiIds, $this->jenjangFilter()));
        $jenisMap = $this->jenisMap();
        $seen = [];
        $items = [];

        foreach (DB::table('sister_list_publikasi')
            ->select('id', 'id_sdm', 'jenis_publikasi', 'judul', 'tanggal', 'quartile')
            ->whereRaw('substr(tanggal,1,4) = ?', [(string) $year])->orderBy('judul')->cursor() as $row) {
            $rowCategory = $jenisMap[$row->jenis_publikasi] ?? null;
            if ($rowCategory === null || ($category !== 'all' && $rowCategory !== $category) || ! isset($roster[(string) $row->id_sdm])) continue;

            $title = trim((string) preg_replace('/\\s+/u', ' ', (string) $row->judul));
            $dedupeKey = $rowCategory.'|'.($title !== '' ? mb_strtolower($title) : 'id:'.(string) $row->id);
            if (isset($seen[$dedupeKey])) continue;
            $seen[$dedupeKey] = true;
            $items[] = [
                'judul' => $title !== '' ? $title : '(Judul tidak tersedia)',
                'jenis' => $row->jenis_publikasi ?: '-', 'tahun' => substr((string) $row->tanggal, 0, 4),
                'kategori' => $this->categories()[$rowCategory] ?? $rowCategory,
                'quartile' => $row->quartile ?: '-',
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

        $categories = $this->categories();
        $jenisMap = $this->jenisMap();

        // Roster DTPS per tahun kolom: publikasi dihitung di prodi homebase
        // dosen pada tahun tersebut (dosen yang pindah homebase, mis. dari
        // S1 Sistem Komputer ke S3 Ilmu Komputer pada 2024, publikasi 2023-nya
        // tetap milik prodi lama).
        $dtpsIdsByYear = [];
        foreach ($years as $key => $year) {
            $dtpsIdsByYear[$key] = $scopeProdiIds === []
                ? []
                : array_flip($this->publikasiDtpsIds($year, $scopeProdiIds, $this->jenjangFilter()));
        }

        $publikasiByYear = $this->publikasiRows(array_values($years));

        $counts = array_fill_keys(array_keys($categories), array_fill_keys(array_keys($years), 0));
        $distinctJudul = array_fill_keys(array_keys($years), 0);

        foreach ($years as $key => $year) {
            $dtpsIds = $dtpsIdsByYear[$key];
            $seenPerCategory = [];

            foreach ($publikasiByYear[$year] ?? [] as $row) {
                $category = $jenisMap[$row['jenis_publikasi']] ?? null;

                if ($category === null || ! $this->hasDtpsAuthor($row['id_sdm_list'], $dtpsIds)) {
                    continue;
                }

                $judulKey = $row['judul_key'];

                // Satu judul dihitung sekali per kategori (mis. prosiding +
                // poster dengan judul sama di "Seminar nasional" = 1).
                if (isset($seenPerCategory[$category][$judulKey])) {
                    continue;
                }

                $seenPerCategory[$category][$judulKey] = true;
                $counts[$category][$key]++;
                // Kartu metrik = jumlah tabel: judul yang tercatat di dua
                // jenis_publikasi berbeda kategori dihitung di keduanya.
                $distinctJudul[$key]++;
            }
        }

        $rows = collect($categories)
            ->map(fn (string $label, string $key): array => [
                'detail_key' => $key,
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
                'detail_key' => 'all',
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
            // Kartu DTPS = DTPS murni (tanpa pengajar); kartu kedua = seluruh
            // dosen yang publikasinya dihitung (DTPS + pengajar) pada TS.
            'metrics' => $this->metrics(
                $distinctJudul,
                $scopeProdiIds === [] ? 0 : count($this->publikasiDtpsIds($ts, $scopeProdiIds, $this->jenjangFilter(), false)),
                count($dtpsIdsByYear['ts']),
                $ts
            ),
            // Semua jumlah 0 = belum ada data untuk lingkup ini, walau barisnya
            // (jenis/sumber) selalu tampil; SaptoKesiapanService membacanya.
            'source' => $this->sourcePayload() + ['template' => (int) (end($rows)['jumlah'] ?? 0) === 0],
        ];
    }

    /**
     * Publikasi dari sister_list_publikasi, dikelompokkan per
     * (tahun, jenis_publikasi, judul ternormalisasi) lalu per tahun. Semua
     * penulis (id_sdm) dari record yang judulnya sama digabung, apa pun `id`-nya.
     *
     * @param  array<int, int>  $years
     * @return array<int, array<int, array{jenis_publikasi: ?string, judul_key: string, id_sdm_list: array<int, string>}>>
     */
    private function publikasiRows(array $years): array
    {
        $cacheKey = 'sapto:publikasi-dtps:publikasi:v2:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);
            $placeholders = implode(',', array_fill(0, count($yearStrings), '?'));

            $groups = [];

            foreach (DB::table('sister_list_publikasi')
                ->selectRaw('id, id_sdm, jenis_publikasi, judul, substr(tanggal,1,4) as tahun')
                ->whereRaw("substr(tanggal,1,4) in ({$placeholders})", $yearStrings)
                ->cursor() as $row) {
                $tahun = (int) $row->tahun;
                $judulKey = $this->judulKey($row->judul, (string) $row->id);
                $groupKey = $tahun.'|'.($row->jenis_publikasi ?? '').'|'.$judulKey;

                $groups[$groupKey] ??= [
                    'tahun' => $tahun,
                    'jenis_publikasi' => $row->jenis_publikasi,
                    'judul_key' => $judulKey,
                    'id_sdm_list' => [],
                ];
                $groups[$groupKey]['id_sdm_list'][(string) $row->id_sdm] = true;
            }

            $byYear = array_fill_keys($years, []);

            foreach ($groups as $row) {
                if (! isset($byYear[$row['tahun']])) {
                    continue;
                }

                $byYear[$row['tahun']][] = [
                    'jenis_publikasi' => $row['jenis_publikasi'],
                    'judul_key' => $row['judul_key'],
                    'id_sdm_list' => array_keys($row['id_sdm_list']),
                ];
            }

            return $byYear;
        });
    }

    /**
     * Kunci deduplikasi judul: lowercase + spasi dirapikan (setara
     * LOWER(TRIM(REGEXP_REPLACE(judul,'[[:space:]]+',' '))) di MySQL), di-md5
     * supaya cache ringkas. Judul kosong/tidak valid jatuh kembali ke `id`.
     */
    private function judulKey(?string $judul, string $id): string
    {
        $normal = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $judul)));

        return $normal === '' ? 'id:'.$id : md5($normal);
    }

    /**
     * @param  array<int|string, string>  $idSdmList
     * @param  array<string, int>  $dtpsIdsFlipped
     */
    private function hasDtpsAuthor(array $idSdmList, array $dtpsIdsFlipped): bool
    {
        foreach ($idSdmList as $id) {
            if (isset($dtpsIdsFlipped[(string) $id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $distinctJudul  jumlah judul per tahun (= baris Jumlah tabel)
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $distinctJudul, int $jumlahDtps, int $jumlahDosen, int $ts): array
    {
        return [
            ['label' => 'Jumlah DTPS (TS)', 'value' => $jumlahDtps],
            ['label' => 'Dosen dihitung: DTPS + pengajar (TS)', 'value' => $jumlahDosen],
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

    private function sourcePayload(): array
    {
        return [
            'name' => 'sister_list_publikasi & list_penugasan_dosen (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}
