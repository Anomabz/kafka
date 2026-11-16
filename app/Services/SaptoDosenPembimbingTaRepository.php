<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Repository SAPTO Tabel 3.a.2
 *
 * Dosen Pembimbing Utama Tugas Akhir
 *
 * ATURAN BISNIS:
 *
 * 1. Tahun data diambil dari 4 digit pertama id_semester:
 *      20251 -> 2025
 *      20252 -> 2025
 *
 * 2. Homebase dosen ditentukan berdasarkan:
 *      list_penugasan_dosen
 *      a_sp_homebase = '1'
 *      tahun = 4 digit pertama nama_tahun_ajaran
 *
 * 3. Homebase bersifat YEAR-SPECIFIC.
 *    Jika dosen:
 *      2024 -> homebase Prodi A
 *      2025 -> homebase Prodi B
 *    maka bimbingan tahun 2024 dibandingkan dengan Prodi A,
 *    sedangkan bimbingan tahun 2025 dibandingkan dengan Prodi B.
 *
 * 4. Filter Fakultas/Prodi menentukan DOSEN YANG BOLEH MUNCUL
 *    (keputusan pengguna, sama dengan EWMP 3.a.3):
 *      - dosen homebase di scope pada tahun itu (kolom DTPS ✓), DAN
 *      - semua dosen lain yang MEMBIMBING mahasiswa prodi di scope pada
 *        tahun itu -- dosen tetap PS lain maupun tidak tetap / tanpa
 *        homebase (kolom DTPS kosong). DTPS diurutkan lebih dulu.
 *
 * 5. Klasifikasi mahasiswa (isPsDiakreditasi):
 *      - satu prodi dipilih: mahasiswa prodi itu -> PS yang
 *        Diakreditasi, prodi lain -> PS Lain di PT;
 *      - fakultas/universitas: mahasiswa prodi homebase dosen -> PS
 *        yang Diakreditasi, lainnya -> PS Lain di PT.
 *
 * 6. Tidak menggunakan pembimbing_ke = 1 agar pembimbing ke-2
 *    juga dapat muncul jika dosen tersebut merupakan homebase
 *    pada Prodi yang sedang dipilih.
 *
 * 7. Tidak menggunakan "dominant prodi".
 */
class SaptoDosenPembimbingTaRepository
{
    use ResolvesSaptoTs;

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    /**
     * ============================================================
     * SUMMARY
     * ============================================================
     */
    public function summary(
        ?int $ts,
        ?string $fakultasId,
        ?string $programStudiId
    ): array {
        $ts ??= $this->ts();

        $years = $this->years($ts);

        $programStudis = $this->client->programStudis();

        $programStudi = $programStudiId === null
            ? null
            : $this->programStudiById($programStudiId, $programStudis);

        $fakultas = $fakultasId === null
            ? null
            : $this->fakultasById($fakultasId, $programStudis);

        if ($programStudiId !== null && $programStudi === null) {
            throw new HttpException(
                400,
                'Program studi tidak ditemukan.'
            );
        }

        if ($fakultasId !== null && $fakultas === null) {
            throw new HttpException(
                400,
                'Fakultas tidak ditemukan.'
            );
        }

        if (
            $programStudi !== null
            && $fakultasId !== null
            && (string) $programStudi['fakultas_id'] !== $fakultasId
        ) {
            throw new HttpException(
                400,
                'Program studi tidak berada dalam fakultas yang dipilih.'
            );
        }

        /**
         * Scope PS yang dipilih.
         *
         * null:
         *   tingkat universitas
         *
         * array:
         *   prodi tertentu
         *   atau seluruh prodi dalam fakultas
         */
        $scopeProdiIds = $this->scopeProdiIds(
            $fakultasId,
            $programStudiId,
            $programStudis
        );

        $aggregate = $this->summaryAggregate(
            $years,
            $scopeProdiIds
        );

        $rows = collect($aggregate)
            ->map(function (array $row): array {

                $psDiakreditasi = [
                    'ts_2' => (int) $row['match_ts_2'],
                    'ts_1' => (int) $row['match_ts_1'],
                    'ts'   => (int) $row['match_ts'],
                ];

                $psLainPt = [
                    'ts_2' => (int) $row['other_ts_2'],
                    'ts_1' => (int) $row['other_ts_1'],
                    'ts'   => (int) $row['other_ts'],
                ];

                /**
                 * Rata-rata masing-masing kelompok.
                 */
                $psDiakreditasi['rata_rata'] =
                    $this->average($psDiakreditasi);

                $psLainPt['rata_rata'] =
                    $this->average($psLainPt);

                /**
                 * Sesuai struktur Tabel 3.a.2:
                 *
                 * rata-rata semua PS =
                 * (rata-rata PS yang Diakreditasi
                 *  + rata-rata PS lain di PT) / 2
                 */
                $rataRataSemuaPs = round(
                    (
                        $psDiakreditasi['rata_rata']
                        + $psLainPt['rata_rata']
                    ) / 2,
                    2
                );

                return [
                    'dosen_id' => (string) $row['dosen_id'],

                    'nama_dosen' => (string) $row['nama_dosen'],

                    // ✓ = dosen homebase di scope (DTPS); kosong = dosen
                    // PS lain / tidak tetap yang membimbing mahasiswa PS.
                    'dtps' => !empty($row['dtps']) ? '✓' : '',

                    'nidn' => $row['nidn'] !== null
                        ? (string) $row['nidn']
                        : null,

                    'nidk' => null,

                    'nip' => null,

                    'identifier' =>
                        $row['nidn'] !== null
                        && $row['nidn'] !== ''
                            ? (string) $row['nidn']
                            : (
                                $row['nuptk'] !== null
                                && $row['nuptk'] !== ''
                                    ? (string) $row['nuptk']
                                    : null
                            ),

                    'ps_diakreditasi' => $psDiakreditasi,

                    'ps_lain_pt' => $psLainPt,

                    'rata_rata_semua_ps' => $rataRataSemuaPs,
                ];
            })
            // DTPS dulu, lalu dosen lain yang membimbing; urut nama.
            ->sortBy([
                fn (array $a, array $b): int => ($a['dtps'] === '✓' ? 0 : 1) <=> ($b['dtps'] === '✓' ? 0 : 1),
                fn (array $a, array $b): int => strcmp($a['nama_dosen'], $b['nama_dosen']),
            ])
            ->values()
            ->map(function (array $row, int $index): array {

                $row['no'] = $index + 1;

                return $row;
            })
            ->values();

        return [
            'table_code' => '3.a.2',

            'table_name' => 'Dosen Pembimbing Utama Tugas Akhir',

            'ts' => $ts,

            'year_columns' => $years,

            'available_years' => $this->availableYears(),

            'fakultas' => $fakultas,

            'program_studi' => $programStudi,

            'rows' => $rows,

            'totals' => $this->totals(
                $rows->all()
            ),

            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * ============================================================
     * DETAIL
     * ============================================================
     */
    public function detail(
        ?int $ts,
        ?string $fakultasId,
        ?string $programStudiId,
        string $dosenId,
        string $scope,
        string $yearKey
    ): array {
        $ts ??= $this->ts();

        $years = $this->years($ts);

        $programStudis = $this->client->programStudis();

        $programStudi = $programStudiId === null
            ? null
            : $this->programStudiById(
                $programStudiId,
                $programStudis
            );

        $fakultas = $fakultasId === null
            ? null
            : $this->fakultasById(
                $fakultasId,
                $programStudis
            );

        if ($programStudiId !== null && $programStudi === null) {
            throw new HttpException(
                400,
                'Program studi tidak ditemukan.'
            );
        }

        if ($fakultasId !== null && $fakultas === null) {
            throw new HttpException(
                400,
                'Fakultas tidak ditemukan.'
            );
        }

        if (
            $programStudi !== null
            && $fakultasId !== null
            && (string) $programStudi['fakultas_id'] !== $fakultasId
        ) {
            throw new HttpException(
                400,
                'Program studi tidak berada dalam fakultas yang dipilih.'
            );
        }

        if (!array_key_exists($yearKey, $years)) {
            throw new HttpException(
                400,
                'Tahun tidak valid.'
            );
        }

        $programIndex = $programStudis->keyBy('id');

        $scopeProdiIds = $this->scopeProdiIds(
            $fakultasId,
            $programStudiId,
            $programStudis
        );

        $year = $years[$yearKey];

        /**
         * ========================================================
         * HOMEBASE DOSEN PADA TAHUN DETAIL
         * ========================================================
         */
        $homebaseMap = $this->homebaseMap(
            [$year]
        );

        $dosenHomebase = $homebaseMap[$dosenId][$year] ?? [];

        $rows = $this->detailRows(
            $dosenId,
            $year
        );

        /**
         * Aturan sama dengan summary: dosen boleh tampil bila lingkup
         * universitas, homebase di scope, ATAU membimbing mahasiswa
         * prodi di scope pada tahun itu (dosen tanpa homebase /
         * tidak tetap juga boleh).
         */
        $homebaseInScope = $dosenHomebase !== [] && (
            $scopeProdiIds === null
            || array_intersect($dosenHomebase, $scopeProdiIds) !== []
        );

        $membimbingDiScope = $scopeProdiIds !== null && collect($rows)->contains(
            fn (object $row): bool => in_array((string) $row->id_prodi, $scopeProdiIds, true)
        );

        if ($scopeProdiIds !== null && !$homebaseInScope && !$membimbingDiScope) {

                /**
                 * Dosen tidak terkait scope yang dipilih.
                 */
                $items = collect();

            } else {

                /**
                 * Satu mahasiswa dapat mempunyai beberapa record
                 * bimbingan pada tahun yang sama.
                 *
                 * Kita kelompokkan berdasarkan mahasiswa.
                 */
                $items = collect($rows)
                    ->groupBy(
                        fn (object $row): string =>
                            (string) (
                                $row->id_registrasi_mahasiswa
                                ?: $row->id_bimbing_mahasiswa
                            )
                    )
                    ->map(
                        function (
                            Collection $studentRows
                        ) use (
                            $dosenHomebase,
                            $scopeProdiIds
                        ): array {

                            /**
                             * Klasifikasi sama dengan summary
                             * (isPsDiakreditasi): prodi terpilih bila
                             * satu prodi, homebase dosen bila
                             * fakultas/universitas.
                             */
                            $cocok = fn (object $row): bool =>
                                $this->isPsDiakreditasi(
                                    (string) $row->id_prodi,
                                    $dosenHomebase,
                                    $scopeProdiIds
                                );

                            $inScope = $studentRows->contains($cocok);

                            /**
                             * Utamakan baris yang masuk PS yang
                             * Diakreditasi jika ada.
                             */
                            $representative =
                                $studentRows->first($cocok)
                                ?? $studentRows->first();

                            return [
                                'in_scope' => $inScope,
                                'row' => $representative,
                            ];
                        }
                    )
                    ->filter(
                        fn (array $entry): bool =>
                            $scope === 'ps_diakreditasi'
                                ? $entry['in_scope']
                                : ! $entry['in_scope']
                    )
                    ->map(
                        function (
                            array $entry
                        ) use (
                            $programIndex,
                            $year
                        ): array {

                            $row = $entry['row'];

                            $program = $programIndex->get(
                                (string) $row->id_prodi
                            );

                            return [
                                'mahasiswa_id' =>
                                    (string) (
                                        $row->id_registrasi_mahasiswa
                                        ?: $row->id_bimbing_mahasiswa
                                    ),

                                'nim' =>
                                    (string) $row->nim,

                                'nama_mahasiswa' =>
                                    (string) $row->nama_mahasiswa,

                                'fakultas_id' =>
                                    (string) data_get(
                                        $program,
                                        'fakultas_id',
                                        ''
                                    ),

                                'fakultas' =>
                                    (string) data_get(
                                        $program,
                                        'fakultas',
                                        'Belum Dipetakan'
                                    ),

                                'program_studi_id' =>
                                    (string) $row->id_prodi,

                                'program_studi' =>
                                    (string) data_get(
                                        $program,
                                        'label',
                                        ''
                                    ),

                                'judul_tugas_akhir' =>
                                    (string) $row->judul,

                                'jenis_tugas_akhir' =>
                                    (string) $row->jenis_aktivitas,

                                'tahun' => $year,

                                'status' =>
                                    (string) $row->status_sync,

                                'pembimbing_ke' =>
                                    (string) $row->pembimbing_ke,

                                'sumber_data' =>
                                    'Pangkalan Data UNSRI (sinkronisasi lokal)',
                            ];
                        }
                    )
                    ->sortBy('nama_mahasiswa')
                    ->values();
            }

        $namaDosen = (string) (
            DB::table('dosen_pembimbing')
                ->where('id_dosen', $dosenId)
                ->value('nama_dosen')
            ?? ''
        );

        return [
            'dosen_id' => $dosenId,

            'nama_dosen' =>
                $namaDosen !== ''
                    ? $namaDosen
                    : null,

            'scope' => $scope,

            'year_key' => $yearKey,

            'year' => $year,

            'items' => $items->values(),

            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * ============================================================
     * SCOPE PRODI
     * ============================================================
     *
     * Jika prodi dipilih:
     *   hanya prodi tersebut.
     *
     * Jika fakultas dipilih:
     *   seluruh prodi dalam fakultas tersebut.
     *
     * Jika tidak ada:
     *   null = seluruh universitas.
     */
    private function scopeProdiIds(
        ?string $fakultasId,
        ?string $programStudiId,
        Collection $programStudis
    ): ?array {
        if ($programStudiId !== null) {
            return [
                (string) $programStudiId
            ];
        }

        if ($fakultasId !== null) {

            return $programStudis
                ->where(
                    'fakultas_id',
                    $fakultasId
                )
                ->pluck('id')
                ->map(
                    fn ($id): string => (string) $id
                )
                ->values()
                ->all();
        }

        return null;
    }

    /**
     * ============================================================
     * SUMMARY AGGREGATE
     * ============================================================
     *
     * Logika:
     *
     * 1. Ambil homebase dosen per tahun.
     * 2. Ambil seluruh data bimbingan.
     * 3. Untuk setiap dosen+tahun:
     *      - cek apakah homebase masuk scope.
     *      - jika tidak -> jangan dihitung.
     * 4. Jika mahasiswa prodi = homebase:
     *      PS yang Diakreditasi
     *    Jika berbeda:
     *      PS Lain di PT
     */
    private function summaryAggregate(
        array $years,
        ?array $scopeProdiIds
    ): array {

        if ($scopeProdiIds === []) {
            return [];
        }

        $cacheKey =
            'sapto:dosen-pembimbing-ta:summary:v8:'
            . md5(
                json_encode(
                    [
                        $years,
                        $scopeProdiIds,
                    ]
                )
            );

        return Cache::remember(
            $cacheKey,
            $this->cacheTtl(),
            function () use (
                $years,
                $scopeProdiIds
            ): array {

                /**
                 * Homebase dosen per tahun.
                 */
                $homebaseMap =
                    $this->homebaseMap(
                        array_values($years)
                    );

                /**
                 * Bimbingan per dosen/tahun/prodi.
                 */
                $bimbingan =
                    $this->bimbinganAggregate(
                        $years
                    );

                /**
                 * Dosen+tahun yang MEMBIMBING mahasiswa prodi di scope
                 * (keputusan pengguna: seperti EWMP, semua dosen yang
                 * membimbing mahasiswa PS ikut tampil, bukan hanya
                 * dosen homebase).
                 */
                $membimbingDiScope = [];

                if ($scopeProdiIds !== null) {
                    foreach ($bimbingan as $row) {
                        if (in_array((string) $row['id_prodi'], $scopeProdiIds, true)) {
                            $membimbingDiScope[(string) $row['dosen_id']][(int) $row['tahun']] = true;
                        }
                    }
                }

                $result = [];

                foreach ($bimbingan as $row) {

                    $dosenId =
                        (string) $row['dosen_id'];

                    $year =
                        (int) $row['tahun'];

                    $studentProdi =
                        (string) $row['id_prodi'];

                    $homebases =
                        $homebaseMap[$dosenId][$year]
                        ?? [];

                    $homebaseInScope = $homebases !== [] && (
                        $scopeProdiIds === null
                        || array_intersect($homebases, $scopeProdiIds) !== []
                    );

                    /**
                     * ==================================================
                     * FILTER DOSEN
                     * ==================================================
                     * Dosen+tahun dihitung bila:
                     *  - lingkup universitas (semua dosen), ATAU
                     *  - homebase di scope, ATAU
                     *  - membimbing mahasiswa prodi di scope pada tahun
                     *    itu (dosen tetap PS lain maupun tidak tetap).
                     */
                    if (
                        $scopeProdiIds !== null
                        && !$homebaseInScope
                        && !isset($membimbingDiScope[$dosenId][$year])
                    ) {
                        continue;
                    }

                    $isMatch = $this->isPsDiakreditasi(
                        $studentProdi,
                        $homebases,
                        $scopeProdiIds
                    );

                    if (!isset($result[$dosenId])) {

                        $result[$dosenId] = [
                            'dosen_id' =>
                                $dosenId,

                            'nama_dosen' =>
                                $row['nama_dosen'],

                            'nidn' =>
                                $row['nidn'],

                            'nuptk' =>
                                $row['nuptk'],

                            'dtps' => false,

                            'total_ts_2' => 0,
                            'total_ts_1' => 0,
                            'total_ts' => 0,

                            'match_ts_2' => 0,
                            'match_ts_1' => 0,
                            'match_ts' => 0,

                            'other_ts_2' => 0,
                            'other_ts_1' => 0,
                            'other_ts' => 0,
                        ];
                    }

                    if ($homebaseInScope) {
                        $result[$dosenId]['dtps'] = true;
                    }

                    $count =
                        (int) $row['jumlah_mahasiswa'];

                    if ($year === $years['ts_2']) {

                        $result[$dosenId]['total_ts_2']
                            += $count;

                        if ($isMatch) {
                            $result[$dosenId]['match_ts_2']
                                += $count;
                        } else {
                            $result[$dosenId]['other_ts_2']
                                += $count;
                        }
                    }

                    if ($year === $years['ts_1']) {

                        $result[$dosenId]['total_ts_1']
                            += $count;

                        if ($isMatch) {
                            $result[$dosenId]['match_ts_1']
                                += $count;
                        } else {
                            $result[$dosenId]['other_ts_1']
                                += $count;
                        }
                    }

                    if ($year === $years['ts']) {

                        $result[$dosenId]['total_ts']
                            += $count;

                        if ($isMatch) {
                            $result[$dosenId]['match_ts']
                                += $count;
                        } else {
                            $result[$dosenId]['other_ts']
                                += $count;
                        }
                    }
                }

                /**
                 * Jangan tampilkan dosen yang setelah filtering
                 * ternyata tidak memiliki mahasiswa pada 3 tahun.
                 */
                return array_values(
                    array_filter(
                        $result,
                        fn (array $row): bool =>
                            (
                                $row['match_ts_2']
                                + $row['match_ts_1']
                                + $row['match_ts']
                                + $row['other_ts_2']
                                + $row['other_ts_1']
                                + $row['other_ts']
                            ) > 0
                    )
                );
            }
        );
    }

    /**
     * ============================================================
     * KLASIFIKASI PS YANG DIAKREDITASI
     * ============================================================
     *
     * Satu prodi dipilih:
     *   mahasiswa prodi itu = PS yang Diakreditasi, prodi lain =
     *   PS Lain di PT (sama dengan EWMP 3.a.3). Berlaku untuk semua
     *   dosen, homebase maupun bukan.
     *
     * Fakultas / universitas:
     *   mahasiswa dari prodi homebase dosen pada tahun itu = PS yang
     *   Diakreditasi (aturan lama); dosen tanpa homebase -> semua
     *   bimbingannya PS Lain di PT.
     *
     * @param  array<int, string>  $homebases
     * @param  array<int, string>|null  $scopeProdiIds
     */
    private function isPsDiakreditasi(
        string $studentProdi,
        array $homebases,
        ?array $scopeProdiIds
    ): bool {
        if ($scopeProdiIds !== null && count($scopeProdiIds) === 1) {
            return $studentProdi === (string) $scopeProdiIds[0];
        }

        return in_array($studentProdi, $homebases, true);
    }

    /**
     * ============================================================
     * BIMBINGAN AGGREGATE
     * ============================================================
     *
     * Tidak menggunakan pembimbing_ke = 1.
     *
     * Artinya pembimbing ke-1 dan pembimbing ke-2 sama-sama dapat
     * menjadi data dosen, selama dosen tersebut memenuhi aturan
     * homebase.
     */
    private function bimbinganAggregate(
        array $years
    ): array {

        $ts2 = (string) $years['ts_2'];
        $ts1 = (string) $years['ts_1'];
        $ts0 = (string) $years['ts'];

        $rows = DB::select(
            "
            select
                dp.id_dosen as dosen_id,

                max(dp.nama_dosen) as nama_dosen,

                max(dp.nidn) as nidn,

                max(dp.nuptk) as nuptk,

                substr(lam.id_semester, 1, 4) as tahun,

                lam.id_prodi as id_prodi,

                count(
                    distinct dp.id_registrasi_mahasiswa
                ) as jumlah_mahasiswa

            from dosen_pembimbing dp

            inner join list_aktivitas_mahasiswa lam
                on lam.id_aktivitas = dp.id_aktivitas

            where {$this->thesisFilterSql()}

              and substr(lam.id_semester, 1, 4)
                  in (?, ?, ?)

            group by
                dp.id_dosen,
                substr(lam.id_semester, 1, 4),
                lam.id_prodi
            ",
            [
                $ts2,
                $ts1,
                $ts0,
            ]
        );

        return array_map(
            fn (object $row): array => [
                'dosen_id' =>
                    (string) $row->dosen_id,

                'nama_dosen' =>
                    $row->nama_dosen,

                'nidn' =>
                    $row->nidn,

                'nuptk' =>
                    $row->nuptk,

                'tahun' =>
                    (int) $row->tahun,

                'id_prodi' =>
                    (string) $row->id_prodi,

                'jumlah_mahasiswa' =>
                    (int) $row->jumlah_mahasiswa,
            ],
            $rows
        );
    }

    /**
     * ============================================================
     * HOMEBASE MAP
     * ============================================================
     *
     * Struktur:
     *
     * [
     *     dosen_id => [
     *         2024 => ['prodi-a'],
     *         2025 => ['prodi-b'],
     *     ]
     * ]
     */
    private function homebaseMap(
        array $years
    ): array {

        $years = array_values(
            array_unique(
                array_map(
                    fn ($year): string => (string) $year,
                    $years
                )
            )
        );

        if ($years === []) {
            return [];
        }

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($years),
                    '?'
                )
            );

        $rows = DB::select(
            "
            select
                id_dosen,
                id_prodi,
                substr(nama_tahun_ajaran, 1, 4) as tahun

            from list_penugasan_dosen

            where a_sp_homebase = '1'

              and substr(nama_tahun_ajaran, 1, 4)
                  in ({$placeholders})

              and id_dosen is not null

              and id_prodi is not null
            ",
            $years
        );

        $map = [];

        foreach ($rows as $row) {

            $dosenId =
                (string) $row->id_dosen;

            $year =
                (int) $row->tahun;

            $prodiId =
                (string) $row->id_prodi;

            $map[$dosenId][$year] ??= [];

            if (
                !in_array(
                    $prodiId,
                    $map[$dosenId][$year],
                    true
                )
            ) {
                $map[$dosenId][$year][] =
                    $prodiId;
            }
        }

        return $map;
    }

    /**
     * ============================================================
     * DETAIL ROWS
     * ============================================================
     */
    private function detailRows(
        string $dosenId,
        int $year
    ): array {

        return DB::select(
            "
            select distinct

                dp.id_registrasi_mahasiswa,

                dp.id_bimbing_mahasiswa,

                dp.nim,

                dp.nama_mahasiswa,

                dp.jenis_aktivitas,

                dp.pembimbing_ke,

                lam.id_prodi,

                lam.judul,

                lam.status_sync

            from dosen_pembimbing dp

            inner join list_aktivitas_mahasiswa lam
                on lam.id_aktivitas = dp.id_aktivitas

            where dp.id_dosen = ?

              and substr(lam.id_semester, 1, 4) = ?

              and {$this->thesisFilterSql()}
            ",
            [
                $dosenId,
                (string) $year,
            ]
        );
    }

    /**
     * ============================================================
     * AVAILABLE YEARS
     * ============================================================
     */
    public function availableYears(): array
    {
        return Cache::remember(
            'sapto:dosen-pembimbing-ta:years:v2',
            $this->cacheTtl(),
            function (): array {

                $rows = DB::select(
                    "
                    select distinct
                        substr(
                            lam.id_semester,
                            1,
                            4
                        ) as thn

                    from dosen_pembimbing dp

                    inner join list_aktivitas_mahasiswa lam
                        on lam.id_aktivitas =
                           dp.id_aktivitas

                    where {$this->thesisFilterSql()}

                      and lam.id_semester is not null

                      and lam.id_semester <> ''

                    order by thn desc
                    "
                );

                return array_values(
                    array_filter(
                        array_map(
                            fn (object $row): int =>
                                (int) $row->thn,
                            $rows
                        ),
                        fn (int $year): bool =>
                            $year >= 2000
                            && $year <= 2100
                    )
                );
            }
        );
    }

    /**
     * ============================================================
     * FILTER TUGAS AKHIR
     * ============================================================
     */
    private function thesisFilterSql(): string
    {
        return "
            (
                lower(dp.jenis_aktivitas)
                    like '%tugas akhir%'

                or lower(dp.jenis_aktivitas)
                    like '%skripsi%'

                or lower(dp.jenis_aktivitas)
                    like '%tesis%'

                or lower(dp.jenis_aktivitas)
                    like '%disertasi%'

                or lower(dp.jenis_aktivitas)
                    like '%laporan akhir studi%'
            )

            and lower(
                trim(
                    coalesce(
                        lam.judul,
                        ''
                    )
                )
            ) not like 'kerja prakt%'

            and lower(
                trim(
                    coalesce(
                        lam.judul,
                        ''
                    )
                )
            ) <> 'pkl'
        ";
    }

    /**
     * ============================================================
     * CACHE TTL
     * ============================================================
     */
    private function cacheTtl(): int
    {
        return max(
            1,
            (int) config(
                'services.pangkalan_data.cache_ttl',
                3600
            )
        );
    }

    /**
     * ============================================================
     * PROGRAM STUDI
     * ============================================================
     */
    private function programStudiById(
        string $id,
        ?Collection $programStudis = null
    ): ?array {
        return ($programStudis ?? $this->client->programStudis())
            ->first(
                fn (array $programStudi): bool =>
                    (string) $programStudi['id'] === $id
            );
    }

    /**
     * ============================================================
     * FAKULTAS
     * ============================================================
     */
    private function fakultasById(
        string $id,
        ?Collection $programStudis = null
    ): ?array {

        $programStudi =
            ($programStudis ?? $this->client->programStudis())
                ->first(
                    fn (array $item): bool =>
                        (string) (
                            $item['fakultas_id'] ?? ''
                        ) === $id
                );

        if ($programStudi === null) {
            return null;
        }

        return [
            'id' =>
                $programStudi['fakultas_id'],

            'nama' =>
                $programStudi['fakultas'],

            'label' =>
                $programStudi['fakultas'],
        ];
    }

    /**
     * ============================================================
     * SOURCE
     * ============================================================
     */
    private function sourcePayload(): array
    {
        return [
            'name' =>
                'Pangkalan Data UNSRI (sinkronisasi lokal)',

            'partial' => false,
        ];
    }

    /**
     * ============================================================
     * YEARS
     * ============================================================
     */
    private function years(int $ts): array
    {
        return [
            'ts_2' => $ts - 2,

            'ts_1' => $ts - 1,

            'ts' => $ts,
        ];
    }

    /**
     * ============================================================
     * BLANK COUNTS
     * ============================================================
     */
    private function blankYearCounts(): array
    {
        return [
            'ts_2' => 0,

            'ts_1' => 0,

            'ts' => 0,
        ];
    }

    /**
     * ============================================================
     * AVERAGE
     * ============================================================
     */
    private function average(
        array $counts
    ): float {
        return round(
            (
                $counts['ts_2']
                + $counts['ts_1']
                + $counts['ts']
            ) / 3,
            2
        );
    }

    /**
     * ============================================================
     * TOTALS
     * ============================================================
     */
    private function totals(
        array $rows
    ): array {

        $totals = [
            'ps_diakreditasi' =>
                $this->blankYearCounts(),

            'ps_lain_pt' =>
                $this->blankYearCounts(),
        ];

        foreach ($rows as $row) {

            foreach (
                [
                    'ts_2',
                    'ts_1',
                    'ts'
                ] as $yearKey
            ) {

                $totals[
                    'ps_diakreditasi'
                ][$yearKey]
                    += $row[
                        'ps_diakreditasi'
                    ][$yearKey];

                $totals[
                    'ps_lain_pt'
                ][$yearKey]
                    += $row[
                        'ps_lain_pt'
                    ][$yearKey];
            }
        }

        $totals[
            'ps_diakreditasi'
        ]['rata_rata'] =
            $this->average(
                $totals['ps_diakreditasi']
            );

        $totals[
            'ps_lain_pt'
        ]['rata_rata'] =
            $this->average(
                $totals['ps_lain_pt']
            );

        return $totals;
    }
}