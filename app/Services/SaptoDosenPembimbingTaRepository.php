<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sumber data: database lokal hasil sinkronisasi Pangkalan Data (bukan API
 * live — kredensial API belum dikonfigurasi di environment ini). Pembimbing
 * utama tugas akhir/skripsi/tesis/disertasi diambil dari dosen_pembimbing
 * (pembimbing_ke = 1), di-join ke list_aktivitas_mahasiswa untuk program
 * studi dan semester/tahun aktivitas. Agregasi dilakukan di sisi SQL supaya
 * tidak perlu menghidrasi puluhan ribu baris mentah ke PHP per request.
 *
 * Semantik kolom Tabel 3.a.2 (BAN-PT LKPS):
 * - "pada PS yang Diakreditasi" = mahasiswa yang dibimbing di dalam SCOPE yang
 *   dipilih (prodi terpilih; atau seluruh prodi fakultas terpilih; atau — untuk
 *   tingkat universitas — "prodi utama" masing-masing dosen).
 * - "pada PS Lain di PT" = mahasiswa yang dibimbing dosen yang sama di luar
 *   scope tapi masih di dalam PT.
 *
 * Di tingkat universitas (tanpa filter fakultas/prodi) tidak ada satu prodi
 * pembanding, jadi "prodi utama" tiap dosen ditentukan dari data: prodi tempat
 * ia paling banyak membimbing sepanjang jendela TS-2..TS. Bimbingan di prodi
 * utama masuk "PS yang Diakreditasi", sisanya "PS Lain di PT".
 */
class SaptoDosenPembimbingTaRepository
{
    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $ts ??= $this->latestYear();
        $years = $this->years($ts);
        $programStudis = $this->client->programStudis();
        $programStudi = $programStudiId === null ? null : $this->programStudiById($programStudiId, $programStudis);
        $fakultas = $fakultasId === null ? null : $this->fakultasById($fakultasId, $programStudis);

        if ($programStudiId !== null && $programStudi === null) {
            throw new HttpException(400, 'Program studi tidak ditemukan.');
        }

        if ($fakultasId !== null && $fakultas === null) {
            throw new HttpException(400, 'Fakultas tidak ditemukan.');
        }

        if ($programStudi !== null && $fakultasId !== null && (string) $programStudi['fakultas_id'] !== $fakultasId) {
            throw new HttpException(400, 'Program studi tidak berada dalam fakultas yang dipilih.');
        }

        // Scope prodi yang dianggap "PS yang Diakreditasi":
        // - null  -> tingkat universitas (seluruh PT, tidak ada "PS lain")
        // - array -> prodi terpilih, atau seluruh prodi dalam fakultas terpilih
        $scopeProdiIds = $this->scopeProdiIds($fakultasId, $programStudiId, $programStudis);

        $aggregate = $this->summaryAggregate($years, $scopeProdiIds);

        $rows = collect($aggregate)
            ->map(function (array $row): array {
                $totalCounts = [
                    'ts_2' => (int) $row['total_ts_2'],
                    'ts_1' => (int) $row['total_ts_1'],
                    'ts' => (int) $row['total_ts'],
                ];
                $matchCounts = [
                    'ts_2' => (int) $row['match_ts_2'],
                    'ts_1' => (int) $row['match_ts_1'],
                    'ts' => (int) $row['match_ts'],
                ];

                // "match" = bimbingan di dalam scope (prodi/fakultas terpilih)
                // atau, di tingkat universitas, di "prodi utama" dosen. Sisanya
                // (total - match) adalah "PS Lain di PT".
                $psDiakreditasi = $matchCounts;
                $psLainPt = [
                    'ts_2' => $totalCounts['ts_2'] - $matchCounts['ts_2'],
                    'ts_1' => $totalCounts['ts_1'] - $matchCounts['ts_1'],
                    'ts' => $totalCounts['ts'] - $matchCounts['ts'],
                ];

                $psDiakreditasi['rata_rata'] = $this->average($psDiakreditasi);
                $psLainPt['rata_rata'] = $this->average($psLainPt);

                return [
                    'dosen_id' => (string) $row['dosen_id'],
                    'nama_dosen' => (string) $row['nama_dosen'],
                    'nidn' => (string) $row['nidn'],
                    'nidk' => null,
                    'nip' => null,
                    'identifier' => $row['nidn'] !== null && $row['nidn'] !== '' ? (string) $row['nidn'] : ((string) $row['nuptk'] ?: null),
                    'ps_diakreditasi' => $psDiakreditasi,
                    'ps_lain_pt' => $psLainPt,
                    'rata_rata_semua_ps' => $this->average([
                        'ts_2' => $psDiakreditasi['ts_2'] + $psLainPt['ts_2'],
                        'ts_1' => $psDiakreditasi['ts_1'] + $psLainPt['ts_1'],
                        'ts' => $psDiakreditasi['ts'] + $psLainPt['ts'],
                    ]),
                ];
            })
            ->sortBy('nama_dosen')
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
            'totals' => $this->totals($rows->all()),
            'source' => $this->sourcePayload(),
        ];
    }

    public function detail(?int $ts, ?string $fakultasId, ?string $programStudiId, string $dosenId, string $scope, string $yearKey): array
    {
        $ts ??= $this->latestYear();
        $years = $this->years($ts);
        $programStudis = $this->client->programStudis();
        $programStudi = $programStudiId === null ? null : $this->programStudiById($programStudiId, $programStudis);
        $fakultas = $fakultasId === null ? null : $this->fakultasById($fakultasId, $programStudis);

        if ($programStudiId !== null && $programStudi === null) {
            throw new HttpException(400, 'Program studi tidak ditemukan.');
        }

        if ($fakultasId !== null && $fakultas === null) {
            throw new HttpException(400, 'Fakultas tidak ditemukan.');
        }

        if ($programStudi !== null && $fakultasId !== null && (string) $programStudi['fakultas_id'] !== $fakultasId) {
            throw new HttpException(400, 'Program studi tidak berada dalam fakultas yang dipilih.');
        }

        $programIndex = $programStudis->keyBy('id');
        $scopeProdiIds = $this->scopeProdiIds($fakultasId, $programStudiId, $programStudis);

        // Tingkat universitas: scope efektif = "prodi utama" dosen (prodi tempat
        // ia paling banyak membimbing). Harus dihitung dengan aturan yang sama
        // seperti summary() agar bucket diakreditasi/lain konsisten.
        if ($scopeProdiIds === null) {
            $dominant = $this->dominantProdiForDosen($dosenId, $years);
            $effectiveScope = $dominant === null ? [] : [$dominant];
        } else {
            $effectiveScope = $scopeProdiIds;
        }

        if ($effectiveScope === []) {
            // Scope fakultas tanpa prodi, atau dosen tanpa bimbingan sama sekali.
            $items = collect();
        } else {
            $rows = $this->detailRows($dosenId, $years[$yearKey]);

            // Satu mahasiswa (id_registrasi_mahasiswa) bisa punya lebih dari satu
            // baris (id_bimbing_mahasiswa/id_aktivitas berbeda) di tahun yang
            // sama. Kelompokkan per mahasiswa lalu tentukan bucket-nya sekali:
            // "di dalam scope" jika ADA baris yang prodinya termasuk scope
            // (konsisten dengan count(distinct ...) di summary()).
            $items = collect($rows)
                ->groupBy(fn (object $row): string => (string) ($row->id_registrasi_mahasiswa ?: $row->id_bimbing_mahasiswa))
                ->map(function (Collection $studentRows) use ($effectiveScope): array {
                    $inScope = $studentRows->contains(fn (object $row): bool => in_array((string) $row->id_prodi, $effectiveScope, true));

                    // Baris representatif: utamakan baris yang berada di dalam
                    // scope agar prodi yang ditampilkan konsisten dengan bucket.
                    $representative = $studentRows->first(fn (object $row): bool => in_array((string) $row->id_prodi, $effectiveScope, true)) ?? $studentRows->first();

                    return ['in_scope' => $inScope, 'row' => $representative];
                })
                ->filter(fn (array $entry): bool => $scope === 'ps_diakreditasi' ? $entry['in_scope'] : ! $entry['in_scope'])
                ->map(function (array $entry) use ($programIndex, $years, $yearKey): array {
                    $row = $entry['row'];
                    $program = $programIndex->get((string) $row->id_prodi);

                    return [
                        'mahasiswa_id' => (string) ($row->id_registrasi_mahasiswa ?: $row->id_bimbing_mahasiswa),
                        'nim' => (string) $row->nim,
                        'nama_mahasiswa' => (string) $row->nama_mahasiswa,
                        'fakultas_id' => (string) data_get($program, 'fakultas_id', ''),
                        'fakultas' => (string) data_get($program, 'fakultas', 'Belum Dipetakan'),
                        'program_studi_id' => (string) $row->id_prodi,
                        'program_studi' => (string) data_get($program, 'label', ''),
                        'judul_tugas_akhir' => (string) $row->judul,
                        'jenis_tugas_akhir' => (string) $row->jenis_aktivitas,
                        'tahun' => $years[$yearKey],
                        'status' => (string) $row->status_sync,
                        'sumber_data' => 'Pangkalan Data UNSRI (sinkronisasi lokal)',
                    ];
                })
                ->sortBy('nama_mahasiswa')
                ->values();
        }

        $namaDosen = (string) (DB::table('dosen_pembimbing')
            ->where('id_dosen', $dosenId)
            ->value('nama_dosen') ?? '');

        return [
            'dosen_id' => $dosenId,
            'nama_dosen' => $namaDosen !== '' ? $namaDosen : null,
            'scope' => $scope,
            'year_key' => $yearKey,
            'year' => $years[$yearKey],
            'items' => $items->values(),
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * Prodi yang dianggap "PS yang Diakreditasi" untuk kombinasi filter:
     * - prodi terpilih  -> hanya prodi itu
     * - fakultas terpilih -> seluruh prodi dalam fakultas
     * - tidak keduanya  -> null (tingkat universitas / seluruh PT)
     *
     * @return array<int, string>|null
     */
    private function scopeProdiIds(?string $fakultasId, ?string $programStudiId, Collection $programStudis): ?array
    {
        if ($programStudiId !== null) {
            return [$programStudiId];
        }

        if ($fakultasId !== null) {
            return $programStudis
                ->where('fakultas_id', $fakultasId)
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->values()
                ->all();
        }

        return null;
    }

    /**
     * Agregasi jumlah MAHASISWA (bukan baris) yang dibimbing per dosen per
     * tahun (TS-2/TS-1/TS), dan berapa di antaranya yang berada di dalam scope
     * (jika scope dipilih). Dihitung dengan count(distinct id_registrasi_
     * mahasiswa) karena data sinkronisasi bisa punya lebih dari satu baris untuk
     * mahasiswa+dosen+tahun yang sama (id_bimbing_mahasiswa/id_aktivitas berbeda
     * tapi id_registrasi_mahasiswa sama). "total" dihitung se-PT (tanpa filter
     * prodi) supaya "PS Lain di PT" = total - dalam scope.
     *
     * @param  array{ts_2:int,ts_1:int,ts:int}  $years
     * @param  array<int, string>|null  $scopeProdiIds
     */
    private function summaryAggregate(array $years, ?array $scopeProdiIds): array
    {
        // Scope dipilih tapi tidak punya prodi sama sekali -> tidak ada data.
        if ($scopeProdiIds === []) {
            return [];
        }

        $cacheKey = 'sapto:dosen-pembimbing-ta:summary:v4:'.md5(json_encode([$years, $scopeProdiIds]));

        return Cache::remember($cacheKey, $this->cacheTtl(), fn (): array => $scopeProdiIds === null
            ? $this->universityAggregate($years)
            : $this->scopedAggregate($years, $scopeProdiIds));
    }

    /**
     * Agregasi untuk scope prodi/fakultas terpilih: total mahasiswa se-PT dan
     * berapa di antaranya berada di dalam scope ("match"). "PS Lain di PT" =
     * total - match.
     *
     * @param  array{ts_2:int,ts_1:int,ts:int}  $years
     * @param  array<int, string>  $scopeProdiIds
     * @return array<int, array<string, mixed>>
     */
    private function scopedAggregate(array $years, array $scopeProdiIds): array
    {
        // Tahun di-bind sebagai STRING: substr(id_semester,1,4) bertipe teks;
        // MySQL menjuggle teks<->angka, tapi SQLite (dipakai di test) tidak,
        // jadi bandingkan teks dengan teks agar konsisten di kedua driver.
        $ts2 = (string) $years['ts_2'];
        $ts1 = (string) $years['ts_1'];
        $ts0 = (string) $years['ts'];

        $placeholders = implode(',', array_fill(0, count($scopeProdiIds), '?'));
        $matchSelect = "
            count(distinct case when substr(lam.id_semester,1,4) = ? and lam.id_prodi in ({$placeholders}) then dp.id_registrasi_mahasiswa end) as match_ts_2,
            count(distinct case when substr(lam.id_semester,1,4) = ? and lam.id_prodi in ({$placeholders}) then dp.id_registrasi_mahasiswa end) as match_ts_1,
            count(distinct case when substr(lam.id_semester,1,4) = ? and lam.id_prodi in ({$placeholders}) then dp.id_registrasi_mahasiswa end) as match_ts
        ";

        $bindings = [$ts2, $ts1, $ts0];
        array_push($bindings, $ts2, ...$scopeProdiIds);
        array_push($bindings, $ts1, ...$scopeProdiIds);
        array_push($bindings, $ts0, ...$scopeProdiIds);

        $sql = "
            select
                dp.id_dosen as dosen_id,
                max(dp.nama_dosen) as nama_dosen,
                max(dp.nidn) as nidn,
                max(dp.nuptk) as nuptk,
                count(distinct case when substr(lam.id_semester,1,4) = ? then dp.id_registrasi_mahasiswa end) as total_ts_2,
                count(distinct case when substr(lam.id_semester,1,4) = ? then dp.id_registrasi_mahasiswa end) as total_ts_1,
                count(distinct case when substr(lam.id_semester,1,4) = ? then dp.id_registrasi_mahasiswa end) as total_ts,
                {$matchSelect}
            from dosen_pembimbing dp
            inner join list_aktivitas_mahasiswa lam on lam.id_aktivitas = dp.id_aktivitas
            where dp.pembimbing_ke = '1'
              and {$this->thesisFilterSql()}
              and substr(lam.id_semester,1,4) in (?, ?, ?)
            group by dp.id_dosen
        ";

        $bindings = array_merge($bindings, [$ts2, $ts1, $ts0]);

        // Simpan sebagai array asosiatif biasa (bukan stdClass) supaya aman
        // di-serialize/unserialize oleh cache file antar-request — objek
        // stdClass hasil DB::select bisa gagal di-hydrate saat dibaca ulang.
        $rows = array_map(fn (object $row): array => (array) $row, DB::select($sql, $bindings));

        // Hanya dosen yang membimbing minimal satu mahasiswa di dalam scope
        // yang relevan untuk tabel akreditasi PS/fakultas ini.
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => ((int) $row['match_ts_2'] + (int) $row['match_ts_1'] + (int) $row['match_ts']) > 0
        ));
    }

    /**
     * Agregasi tingkat universitas: hitung per dosen per prodi, tentukan "prodi
     * utama" tiap dosen (prodi dengan jumlah mahasiswa terbanyak sepanjang
     * jendela TS-2..TS), lalu "match" = bimbingan di prodi utama itu. "PS Lain
     * di PT" = total - match. Bentuk keluaran identik dengan scopedAggregate()
     * agar map() di summary() tidak perlu bercabang.
     *
     * @param  array{ts_2:int,ts_1:int,ts:int}  $years
     * @return array<int, array<string, mixed>>
     */
    private function universityAggregate(array $years): array
    {
        $ts2 = (string) $years['ts_2'];
        $ts1 = (string) $years['ts_1'];
        $ts0 = (string) $years['ts'];

        $sql = "
            select
                dp.id_dosen as dosen_id,
                lam.id_prodi as id_prodi,
                max(dp.nama_dosen) as nama_dosen,
                max(dp.nidn) as nidn,
                max(dp.nuptk) as nuptk,
                count(distinct dp.id_registrasi_mahasiswa) as cnt_window,
                count(distinct case when substr(lam.id_semester,1,4) = ? then dp.id_registrasi_mahasiswa end) as cnt_ts_2,
                count(distinct case when substr(lam.id_semester,1,4) = ? then dp.id_registrasi_mahasiswa end) as cnt_ts_1,
                count(distinct case when substr(lam.id_semester,1,4) = ? then dp.id_registrasi_mahasiswa end) as cnt_ts
            from dosen_pembimbing dp
            inner join list_aktivitas_mahasiswa lam on lam.id_aktivitas = dp.id_aktivitas
            where dp.pembimbing_ke = '1'
              and {$this->thesisFilterSql()}
              and substr(lam.id_semester,1,4) in (?, ?, ?)
            group by dp.id_dosen, lam.id_prodi
        ";

        $rows = DB::select($sql, [$ts2, $ts1, $ts0, $ts2, $ts1, $ts0]);

        $byDosen = [];
        foreach ($rows as $row) {
            $byDosen[(string) $row->dosen_id][] = $row;
        }

        $result = [];

        foreach ($byDosen as $dosenId => $prodiRows) {
            $dominant = $this->pickDominantProdi($prodiRows);

            $totalTs2 = 0;
            $totalTs1 = 0;
            $totalTs0 = 0;
            $matchTs2 = 0;
            $matchTs1 = 0;
            $matchTs0 = 0;
            $namaDosen = null;
            $nidn = null;
            $nuptk = null;

            // Satu mahasiswa tesis hanya berada di satu prodi, jadi menjumlah
            // count-distinct per prodi = jumlah mahasiswa distinct se-PT.
            foreach ($prodiRows as $pr) {
                $totalTs2 += (int) $pr->cnt_ts_2;
                $totalTs1 += (int) $pr->cnt_ts_1;
                $totalTs0 += (int) $pr->cnt_ts;

                if ((string) $pr->id_prodi === $dominant) {
                    $matchTs2 = (int) $pr->cnt_ts_2;
                    $matchTs1 = (int) $pr->cnt_ts_1;
                    $matchTs0 = (int) $pr->cnt_ts;
                }

                $namaDosen ??= ($pr->nama_dosen !== null && $pr->nama_dosen !== '' ? (string) $pr->nama_dosen : null);
                $nidn ??= ($pr->nidn !== null && $pr->nidn !== '' ? (string) $pr->nidn : null);
                $nuptk ??= ($pr->nuptk !== null && $pr->nuptk !== '' ? (string) $pr->nuptk : null);
            }

            $result[] = [
                'dosen_id' => (string) $dosenId,
                'nama_dosen' => $namaDosen,
                'nidn' => $nidn,
                'nuptk' => $nuptk,
                'total_ts_2' => $totalTs2,
                'total_ts_1' => $totalTs1,
                'total_ts' => $totalTs0,
                'match_ts_2' => $matchTs2,
                'match_ts_1' => $matchTs1,
                'match_ts' => $matchTs0,
            ];
        }

        return $result;
    }

    /**
     * "Prodi utama" seorang dosen: prodi dengan jumlah mahasiswa distinct
     * terbanyak sepanjang jendela TS. Bila seri, pilih id_prodi terkecil
     * (perbandingan string) supaya deterministik dan konsisten antara summary()
     * dan detail().
     *
     * @param  array<int, object>  $prodiRows  baris dengan properti id_prodi & cnt_window
     */
    private function pickDominantProdi(array $prodiRows): ?string
    {
        $dominant = null;
        $dominantScore = -1;

        foreach ($prodiRows as $pr) {
            $score = (int) $pr->cnt_window;
            $prodiId = (string) $pr->id_prodi;

            if ($dominant === null
                || $score > $dominantScore
                || ($score === $dominantScore && $prodiId < $dominant)) {
                $dominantScore = $score;
                $dominant = $prodiId;
            }
        }

        return $dominant;
    }

    /**
     * Prodi utama satu dosen (dipakai detail() di tingkat universitas). Aturan
     * identik dengan pickDominantProdi() agar bucket diakreditasi/lain sama
     * dengan yang dihitung summary().
     *
     * @param  array{ts_2:int,ts_1:int,ts:int}  $years
     */
    private function dominantProdiForDosen(string $dosenId, array $years): ?string
    {
        $rows = DB::select("
            select
                lam.id_prodi as id_prodi,
                count(distinct dp.id_registrasi_mahasiswa) as cnt_window
            from dosen_pembimbing dp
            inner join list_aktivitas_mahasiswa lam on lam.id_aktivitas = dp.id_aktivitas
            where dp.pembimbing_ke = '1'
              and dp.id_dosen = ?
              and {$this->thesisFilterSql()}
              and substr(lam.id_semester,1,4) in (?, ?, ?)
            group by lam.id_prodi
        ", [$dosenId, (string) $years['ts_2'], (string) $years['ts_1'], (string) $years['ts']]);

        return $this->pickDominantProdi($rows);
    }

    /**
     * Detail mahasiswa bimbingan seorang dosen pada satu tahun TS (seluruh PT,
     * tidak difilter prodi — pemisahan diakreditasi/lain dilakukan di PHP agar
     * konsisten dengan summary). Diambil langsung (tidak lewat cache dataset
     * besar) karena scope-nya sudah sempit per dosen+tahun.
     */
    private function detailRows(string $dosenId, int $year): array
    {
        return DB::select("
            select distinct
                dp.id_registrasi_mahasiswa,
                dp.id_bimbing_mahasiswa,
                dp.nim,
                dp.nama_mahasiswa,
                dp.jenis_aktivitas,
                lam.id_prodi,
                lam.judul,
                lam.status_sync
            from dosen_pembimbing dp
            inner join list_aktivitas_mahasiswa lam on lam.id_aktivitas = dp.id_aktivitas
            where dp.pembimbing_ke = '1'
              and dp.id_dosen = ?
              and substr(lam.id_semester,1,4) = ?
              and {$this->thesisFilterSql()}
        ", [$dosenId, (string) $year]);
    }

    /**
     * Tahun akademik (substr(id_semester,1,4)) yang benar-benar punya data
     * bimbingan tugas akhir, terurut menurun. Dipakai untuk menentukan TS
     * default dan mengisi dropdown TS di frontend sesuai isi database.
     *
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:dosen-pembimbing-ta:years:v1', $this->cacheTtl(), function (): array {
            $rows = DB::select("
                select distinct substr(lam.id_semester,1,4) as thn
                from dosen_pembimbing dp
                inner join list_aktivitas_mahasiswa lam on lam.id_aktivitas = dp.id_aktivitas
                where dp.pembimbing_ke = '1'
                  and {$this->thesisFilterSql()}
                  and lam.id_semester is not null
                  and lam.id_semester <> ''
                order by thn desc
            ");

            return array_values(array_filter(
                array_map(fn (object $row): int => (int) $row->thn, $rows),
                fn (int $year): bool => $year >= 2000 && $year <= 2100
            ));
        });
    }

    private function latestYear(): int
    {
        return $this->availableYears()[0] ?? (int) now()->year;
    }

    /**
     * Jenis aktivitas yang dihitung sebagai "Tugas Akhir" pada Tabel 3.a.2:
     * skripsi (S1), tesis (S2), disertasi (S3), dan "tugas akhir".
     */
    private function thesisFilterSql(): string
    {
        return "(
            lower(dp.jenis_aktivitas) like '%tugas akhir%'
            or lower(dp.jenis_aktivitas) like '%skripsi%'
            or lower(dp.jenis_aktivitas) like '%tesis%'
            or lower(dp.jenis_aktivitas) like '%disertasi%'
        )";
    }

    private function cacheTtl(): int
    {
        return max(1, (int) config('services.pangkalan_data.cache_ttl', 3600));
    }

    private function programStudiById(string $id, ?Collection $programStudis = null): ?array
    {
        return ($programStudis ?? $this->client->programStudis())
            ->first(fn (array $programStudi): bool => (string) $programStudi['id'] === $id);
    }

    private function fakultasById(string $id, ?Collection $programStudis = null): ?array
    {
        $programStudi = ($programStudis ?? $this->client->programStudis())
            ->first(fn (array $item): bool => (string) ($item['fakultas_id'] ?? '') === $id);

        if ($programStudi === null) {
            return null;
        }

        return [
            'id' => $programStudi['fakultas_id'],
            'nama' => $programStudi['fakultas'],
            'label' => $programStudi['fakultas'],
        ];
    }

    private function sourcePayload(): array
    {
        return [
            'name' => 'Pangkalan Data UNSRI (sinkronisasi lokal)',
            'partial' => false,
        ];
    }

    /**
     * @return array{ts_2: int, ts_1: int, ts: int}
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
     * @return array{ts_2: int, ts_1: int, ts: int}
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
     * @param  array{ts_2: int|float, ts_1: int|float, ts: int|float}  $counts
     */
    private function average(array $counts): float
    {
        return round(($counts['ts_2'] + $counts['ts_1'] + $counts['ts']) / 3, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function totals(array $rows): array
    {
        $totals = [
            'ps_diakreditasi' => $this->blankYearCounts(),
            'ps_lain_pt' => $this->blankYearCounts(),
        ];

        foreach ($rows as $row) {
            foreach (['ts_2', 'ts_1', 'ts'] as $yearKey) {
                $totals['ps_diakreditasi'][$yearKey] += $row['ps_diakreditasi'][$yearKey];
                $totals['ps_lain_pt'][$yearKey] += $row['ps_lain_pt'][$yearKey];
            }
        }

        $totals['ps_diakreditasi']['rata_rata'] = $this->average($totals['ps_diakreditasi']);
        $totals['ps_lain_pt']['rata_rata'] = $this->average($totals['ps_lain_pt']);

        return $totals;
    }
}
