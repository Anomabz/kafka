<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.a.3) Ekuivalen Waktu Mengajar Penuh (EWMP) Dosen Tetap
 * Perguruan Tinggi. Per program studi.
 *
 * Baris = SEMUA dosen yang terkait prodi ini pada TS (keputusan pengguna):
 *  - DTPS (kolom DTPS ✓): penugasan berkode tetap A/B/F/H/I/N di prodi ini,
 *    homebase 1 maupun 0 (definisi sama dengan Tabel 3.a.1); dan
 *  - dosen lain yang mengajar kelas prodi ini pada semester TS, baik dosen
 *    tetap homebase PS lain maupun dosen tidak tetap (kolom DTPS kosong).
 * Satu baris per id_dosen (nama kembar seperti "FIRDAUS" tidak tergabung).
 * DTPS diurutkan lebih dulu. Kartu rata-rata EWMP hanya menghitung DTPS.
 * Sebelumnya roster = homebase=1 apa pun ikatan kerjanya, sehingga dosen G
 * ber-homebase (mis. ERWEDI) ikut bertanda DTPS.
 *
 * EWMP Pendidikan dihitung dari beban mengajar nyata di
 * dosen_pengajar_kelas_kuliah (sks_substansi_total per kelas) pada semester
 * TS (<TS>1, <TS>2, dan semester pendek <TS>3):
 * - "PS yang Diakreditasi" = kelas milik prodi ini,
 * - "PS Lain di dalam PT"  = kelas milik prodi lain di UNSRI.
 *
 * EWMP Penelitian, PkM, dan Penunjang diambil dari BKD SISTER:
 * - Penelitian = jumlah kolom `nilai` di sister_list_bkd_penelitian,
 * - PkM        = jumlah kolom `nilai` di sister_list_bkd_pengabdian,
 * - Penunjang  = jumlah kolom `nilai` di sister_list_bkd_penunjang
 *                (kolom "Tugas Tambahan dan/atau Penunjang"),
 * dicocokkan lewat id_sdm = id_dosen, untuk baris yang 4 digit pertama
 * id_smt-nya = TS (mis. id_smt 20202 -> tahun 2020). Nilai dibulatkan ke
 * 4 desimal tanpa nol di belakang (1.7500000 -> 1.75, 0.3230 -> 0.323).
 * Dosen tanpa catatan BKD pada TS bernilai 0.
 *
 * Kolom yang tidak ada sumbernya -- mengajar di luar PT -- bernilai null
 * ("-"), bukan 0. Bimbingan tugas akhir tidak tercatat dalam sks di sumber,
 * jadi belum ikut.
 *
 * Jumlah (sks) = PS diakreditasi + PS lain di PT + Penelitian + PkM + Penunjang.
 * Rata-rata per semester = jumlah / 2 (dua semester reguler dalam satu TS,
 * sesuai format LKPS). Notice menyebut bila data mengajar TS baru satu
 * semester, karena rata-ratanya bisa lebih kecil dari yang sebenarnya.
 *
 * Sebelum 2026-09-30 semua kolom EWMP diisi 0 di kode dan daftar dosennya
 * diambil dari sumber lain (sister_list_penugasan), sehingga tidak cocok
 * dengan Tabel 3.a.1.
 */
class SaptoEwmpDosenRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    /** Ikatan kerja tetap -- sama dengan Tabel 3.a.1 / 3.a.4. */
    private const IKATAN_TETAP = ['A', 'B', 'F', 'H', 'I', 'N'];

    /** Tabel BKD SISTER sumber EWMP Penelitian, PkM, dan Penunjang. */
    private const TABLE_BKD_PENELITIAN = 'sister_list_bkd_penelitian';
    private const TABLE_BKD_PENGABDIAN = 'sister_list_bkd_pengabdian';
    private const TABLE_BKD_PENUNJANG = 'sister_list_bkd_penunjang';

    /** Presisi nilai BKD; float PHP/JSON otomatis membuang nol di belakang. */
    private const BKD_DESIMAL = 4;

    /** Pembagi rata-rata per semester: satu TS = 2 semester reguler (LKPS). */
    private const SEMESTER_PER_TS = 2;

    private const GROUP_PENDIDIKAN = 'Ekuivalen Waktu Mengajar Penuh (EWMP) pada saat TS dalam satuan kredit semester (sks)';

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'nama_dosen', 'label' => 'Nama Dosen', 'align' => 'left'],
        ['key' => 'dtps', 'label' => 'DTPS', 'align' => 'left'],
        ['key' => 'ewmp_ps_diakreditasi', 'label' => 'PS yang Diakreditasi', 'align' => 'num', 'group' => self::GROUP_PENDIDIKAN],
        ['key' => 'ewmp_ps_lain_pt', 'label' => 'PS Lain di dalam PT', 'align' => 'num', 'group' => self::GROUP_PENDIDIKAN],
        ['key' => 'ewmp_ps_lain_luar', 'label' => 'PS Lain di luar PT', 'align' => 'num', 'group' => self::GROUP_PENDIDIKAN],
        ['key' => 'ewmp_penelitian', 'label' => 'Penelitian', 'align' => 'num'],
        ['key' => 'ewmp_pkm', 'label' => 'PkM', 'align' => 'num'],
        ['key' => 'ewmp_tugas_tambahan', 'label' => 'Tugas Tambahan dan/atau Penunjang', 'align' => 'num'],
        ['key' => 'jumlah_sks', 'label' => 'Jumlah (sks)', 'align' => 'num'],
        ['key' => 'rata_rata_per_semester', 'label' => 'Rata-rata per Semester (sks)', 'align' => 'num'],
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();

        if ($programStudi === null) {
            return $this->noticeEnvelope($ts, $fakultas, $programStudi,
                'Tabel 3.a.3 disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan daftar EWMP dosen.');
        }

        [$dosenRows, $semesters] = $this->loadDosen($ts, (string) $programStudi['id']);

        $rows = [];
        foreach ($dosenRows as $index => $row) {
            $rows[] = ['no' => $index + 1] + $row;
        }

        $regular = array_values(array_filter($semesters, fn (string $s): bool => in_array(substr($s, 4), ['1', '2'], true)));
        $notice = 'EWMP Pendidikan dihitung dari beban mengajar (sks kelas) di PD UNSRI. '
            .'Penelitian, PkM, dan Tugas Tambahan/Penunjang diambil dari nilai BKD SISTER pada tahun '.$ts.'. '
            .'Mengajar di luar PT dan bimbingan tugas akhir tidak tersedia di sumber (ditampilkan "-") dan perlu diisi dari BKD.';

        if (count($regular) < 2) {
            $notice .= ' Data mengajar TS '.$ts.'/'.($ts + 1).' di sumber baru mencakup '
                .($regular === [] ? 'nol semester' : 'semester '.implode(', ', $regular))
                .'; rata-rata per semester tetap dibagi '.self::SEMESTER_PER_TS.' sehingga bisa lebih kecil dari yang sebenarnya sampai data semester berikutnya masuk.';
        }

        return [
            'table_code' => '3.a.3',
            'table_name' => 'Ekuivalen Waktu Mengajar Penuh (EWMP) Dosen Tetap',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'metrics' => $this->metrics($rows, $ts),
            'notice' => $notice,
            'source' => [
                'name' => 'list_penugasan_dosen + dosen_pengajar_kelas_kuliah (PD UNSRI) + sister_list_bkd_penelitian + sister_list_bkd_pengabdian + sister_list_bkd_penunjang (SISTER)',
                'partial' => true,
            ],
        ];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<string>} [baris, semester TS yang ada datanya]
     */
    private function loadDosen(int $ts, string $prodiId): array
    {
        // v6: tambah kolom Penunjang dari sister_list_bkd_penunjang. Versi
        // dinaikkan agar cache lama (tanpa penunjang) tidak terpakai.
        $cacheKey = 'sapto:ewmp-dosen:v6:'.md5(json_encode([$ts, $prodiId]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($ts, $prodiId): array {
            $semesters = ["{$ts}1", "{$ts}2", "{$ts}3"];

            // Roster = (a) DTPS: penugasan berkode tetap di prodi ini pada TS
            // (homebase 1 maupun 0 -- definisi sama dengan Tabel 3.a.1), DITAMBAH
            // (b) semua dosen yang mengajar kelas prodi ini pada semester TS,
            // tetap (homebase PS lain) maupun tidak tetap (keputusan pengguna).
            // id_dosen => ['nama' => ..., 'dtps' => bool]
            $roster = [];

            foreach (DB::table('list_penugasan_dosen')
                ->select('id_dosen', 'nama_dosen')
                ->where('id_prodi', $prodiId)
                ->whereIn('id_ikatan_kerja', self::IKATAN_TETAP)
                ->whereRaw('substr(nama_tahun_ajaran, 1, 4) = ?', [(string) $ts])
                ->cursor() as $r) {
                $id = (string) $r->id_dosen;
                if ($id !== '') {
                    $roster[$id] = ['nama' => (string) ($r->nama_dosen ?? ''), 'dtps' => true];
                }
            }

            foreach (DB::table('dosen_pengajar_kelas_kuliah')
                ->select('id_dosen', 'nama_dosen')
                ->where('id_prodi', $prodiId)
                ->whereIn('id_semester', $semesters)
                ->whereNotNull('id_dosen')
                ->where('id_dosen', '<>', '')
                ->cursor() as $r) {
                $id = (string) $r->id_dosen;
                $roster[$id] ??= ['nama' => (string) ($r->nama_dosen ?? ''), 'dtps' => false];
                if ($roster[$id]['nama'] === '') {
                    $roster[$id]['nama'] = (string) ($r->nama_dosen ?? '');
                }
            }

            $present = DB::table('dosen_pengajar_kelas_kuliah')
                ->whereIn('id_semester', $semesters)
                ->distinct()
                ->pluck('id_semester')
                ->map(fn ($s): string => (string) $s)
                ->sort()
                ->values()
                ->all();

            $ids = array_map('strval', array_keys($roster));

            $load = [];
            foreach (array_chunk($ids, 500) as $chunk) {
                foreach (DB::table('dosen_pengajar_kelas_kuliah')
                    ->select('id_dosen', 'id_prodi', 'sks_substansi_total')
                    ->whereIn('id_dosen', $chunk)
                    ->whereIn('id_semester', $semesters)
                    ->cursor() as $row) {
                    $key = (string) $row->id_dosen;
                    $load[$key] ??= ['ps' => 0.0, 'lain' => 0.0];
                    $load[$key][(string) $row->id_prodi === $prodiId ? 'ps' : 'lain'] += (float) $row->sks_substansi_total;
                }
            }

            // EWMP Penelitian, PkM, dan Penunjang dari BKD SISTER (id_sdm = id_dosen).
            $penelitian = $this->bkdNilai(self::TABLE_BKD_PENELITIAN, $ids, $ts);
            $pkm = $this->bkdNilai(self::TABLE_BKD_PENGABDIAN, $ids, $ts);
            $penunjang = $this->bkdNilai(self::TABLE_BKD_PENUNJANG, $ids, $ts);

            $rows = [];

            // DTPS dulu, lalu dosen lain yang mengajar; masing-masing urut nama.
            uksort($roster, function ($a, $b) use ($roster): int {
                return [$roster[$a]['dtps'] ? 0 : 1, $roster[$a]['nama']]
                    <=> [$roster[$b]['dtps'] ? 0 : 1, $roster[$b]['nama']];
            });

            foreach ($roster as $dosenId => $dosen) {
                $key = (string) $dosenId;
                $sks = $load[$key] ?? ['ps' => 0.0, 'lain' => 0.0];
                $nilaiPenelitian = $penelitian[$key] ?? 0.0;
                $nilaiPkm = $pkm[$key] ?? 0.0;
                $nilaiPenunjang = $penunjang[$key] ?? 0.0;

                $jumlah = round($sks['ps'] + $sks['lain'] + $nilaiPenelitian + $nilaiPkm + $nilaiPenunjang, self::BKD_DESIMAL);

                $rows[] = [
                    'nama_dosen' => $dosen['nama'] !== '' ? $dosen['nama'] : '-',
                    'dtps' => $dosen['dtps'] ? '✓' : '',
                    'ewmp_ps_diakreditasi' => round($sks['ps'], 2),
                    'ewmp_ps_lain_pt' => round($sks['lain'], 2),
                    'ewmp_ps_lain_luar' => null,
                    'ewmp_penelitian' => $nilaiPenelitian,
                    'ewmp_pkm' => $nilaiPkm,
                    'ewmp_tugas_tambahan' => $nilaiPenunjang,
                    'jumlah_sks' => $jumlah,
                    'rata_rata_per_semester' => round($jumlah / self::SEMESTER_PER_TS, 2),
                ];
            }

            return [$rows, $present];
        });
    }

    /**
     * Jumlah kolom `nilai` BKD per id_sdm untuk tahun TS (4 digit pertama id_smt).
     * Hasil dibulatkan ke BKD_DESIMAL desimal, sehingga 1.7500000000000 -> 1.75
     * dan 0.3230 -> 0.323 (float tidak menyimpan nol di belakang koma).
     *
     * @param  list<string>  $ids
     * @return array<string, float> id_sdm => nilai
     */
    private function bkdNilai(string $table, array $ids, int $ts): array
    {
        $hasil = [];

        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (DB::table($table)
                ->select('id_sdm', 'nilai')
                ->whereIn('id_sdm', $chunk)
                ->whereRaw('substr(id_smt, 1, 4) = ?', [(string) $ts])
                ->whereNotNull('nilai')
                ->cursor() as $row) {
                $key = (string) $row->id_sdm;
                $hasil[$key] = ($hasil[$key] ?? 0.0) + (float) $row->nilai;
            }
        }

        return array_map(fn (float $v): float => round($v, self::BKD_DESIMAL), $hasil);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, int $ts): array
    {
        // Rata-rata EWMP mengikuti rumus LKPS: hanya DTPS.
        $dtps = array_values(array_filter($rows, fn (array $r): bool => $r['dtps'] === '✓'));
        $jumlahDtps = array_sum(array_column($dtps, 'jumlah_sks'));

        return [
            ['label' => 'Total dosen mengajar (TS)', 'value' => count($rows)],
            ['label' => 'Total DTPS (TS)', 'value' => count($dtps)],
            ['label' => 'Rata-rata EWMP DTPS (sks)', 'value' => count($dtps) > 0 ? round($jumlahDtps / count($dtps), 2) : 0],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    private function noticeEnvelope(int $ts, ?array $fakultas, ?array $programStudi, string $notice): array
    {
        return [
            'table_code' => '3.a.3',
            'table_name' => 'Ekuivalen Waktu Mengajar Penuh (EWMP) Dosen Tetap',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [],
            'rows' => [],
            'metrics' => [],
            'notice' => $notice,
            'source' => ['name' => 'list_penugasan_dosen + dosen_pengajar_kelas_kuliah + BKD SISTER', 'partial' => true],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        return Cache::remember('sapto:ewmp-dosen:years:v1', $this->cacheTtl(), function (): array {
            $rows = DB::select("
                SELECT DISTINCT CAST(SUBSTR(nama_tahun_ajaran, 1, 4) AS UNSIGNED) as thn
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

    private function cacheTtl(): int
    {
        return max(1, (int) config('services.pangkalan_data.cache_ttl', 3600));
    }
}