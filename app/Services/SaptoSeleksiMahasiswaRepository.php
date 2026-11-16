<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 2.a) Seleksi Mahasiswa Baru.
 *
 * Mengikuti scope filter: universitas (tanpa filter), fakultas (semua prodi
 * di fakultas itu), atau satu program studi -- LKPS 2.a diisi per prodi.
 * Baris = tahun akademik TS-4..TS. Semua jumlah berasal dari list_mahasiswa,
 * memakai COUNT(DISTINCT id_registrasi_mahasiswa) dan tahun LEFT(id_periode, 4)
 * (semester ganjil dan genap digabung). Kategori reguler/transfer ditentukan
 * dari satu label jenis daftar per registrasi, agar riwayat ganda tidak
 * menggandakan hitungan.
 *
 * Definisi yang digunakan:
 * - Pendaftar: dikosongkan/0 karena data calon pendaftar PMB belum tersedia
 *   pada sumber data lokal yang digunakan repository ini.
 * - Mahasiswa baru: semua registrasi pada angkatan tersebut, apa pun statusnya,
 *   ditampilkan terpisah sebagai reguler (Peserta Didik Baru) dan transfer
 *   (jenis daftar lainnya).
 * - Lulus: sama dengan jumlah mahasiswa baru.
 * - Mahasiswa aktif: registrasi angkatan tersebut dengan status AKTIF.
 *
 * Pendaftar dan daya tampung belum memiliki sumber data.
 */
class SaptoSeleksiMahasiswaRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'tahun_akademik', 'label' => 'Tahun Akademik', 'align' => 'left'],
        ['key' => 'daya_tampung', 'label' => 'Daya Tampung', 'align' => 'num'],
        ['key' => 'calon_pendaftar', 'label' => 'Pendaftar', 'align' => 'num', 'group' => 'Jumlah Calon Mahasiswa'],
        ['key' => 'calon_lulus', 'label' => 'Lulus', 'align' => 'num', 'group' => 'Jumlah Calon Mahasiswa'],
        ['key' => 'baru_reguler', 'label' => 'Reguler', 'align' => 'num', 'group' => 'Jumlah Mahasiswa Baru'],
        ['key' => 'baru_transfer', 'label' => 'Transfer', 'align' => 'num', 'group' => 'Jumlah Mahasiswa Baru'],
        ['key' => 'aktif_reguler', 'label' => 'Reguler', 'align' => 'num', 'group' => 'Jumlah Mahasiswa Aktif'],
        ['key' => 'aktif_transfer', 'label' => 'Transfer', 'align' => 'num', 'group' => 'Jumlah Mahasiswa Aktif'],
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId = null, ?string $programStudiId = null): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();
        $years = [$ts - 4, $ts - 3, $ts - 2, $ts - 1, $ts];

        // [] = scope tanpa prodi (mis. fakultas kosong) -> semua nol.
        $data = $scopeProdiIds === []
            ? array_map(fn (int $y): array => $this->emptyRow($y), array_combine($years, $years))
            : $this->loadData($years, $scopeProdiIds);

        $totals = [
            'calon_pendaftar' => 0,
            'calon_lulus' => 0,
            'mahasiswa_baru' => 0,
            'mahasiswa_aktif' => 0,
            'baru_reguler' => 0,
            'baru_transfer' => 0,
            'aktif_reguler' => 0,
            'aktif_transfer' => 0,
        ];

        $rows = [];

        foreach ($years as $i => $year) {
            $row = $data[$year] ?? $this->emptyRow($year);
            $row['no'] = $i + 1;
            $row['tahun_akademik'] = (string) $year . '/' . ($year + 1);
            $row['detail_year'] = $year;
            $row['daya_tampung'] = null;
            $rows[] = $row;

            foreach (array_keys($totals) as $key) {
                $totals[$key] += (int) ($row[$key] ?? 0);
            }
        }

        $rows[] = [
            'no' => null,
            'tahun_akademik' => 'Jumlah',
            'daya_tampung' => null,
            'calon_pendaftar' => $totals['calon_pendaftar'],
            'calon_lulus' => $totals['calon_lulus'],
            'baru_reguler' => $totals['baru_reguler'],
            'baru_transfer' => $totals['baru_transfer'],
            'aktif_reguler' => $totals['aktif_reguler'],
            'aktif_transfer' => $totals['aktif_transfer'],
        ];

        $tsAktif = $data[$ts] ?? [];
        $aktifTotal = (int) ($tsAktif['mahasiswa_aktif'] ?? 0);

        return [
            'table_code' => '2.a',
            'table_name' => 'Seleksi Mahasiswa Baru',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'metrics' => [
                ['label' => 'Total pendaftar (TS-4..TS)', 'value' => 0],
                ['label' => 'Total lulus seleksi (TS-4..TS)', 'value' => $totals['calon_lulus']],
                ['label' => 'Total mahasiswa baru (TS-4..TS)', 'value' => $totals['mahasiswa_baru']],
                ['label' => 'Total mahasiswa aktif (TS)', 'value' => $aktifTotal],
                ['label' => 'Tahun akademik (TS)', 'value' => $ts],
            ],
            'notice' => $this->buildNotice($data, $years),
            'source' => [
                'name' => 'list_mahasiswa + jenis daftar registrasi',
                'partial' => true,
            ],
        ];
    }

    public function detail(int $year, string $category, int $perPage, int $page, ?string $fakultasId, ?string $programStudiId, ?string $jenisDaftar = null): array
    {
        $programStudis = $this->client->programStudis();
        $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scope = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);
        $allowed = ['calon_lulus', 'mahasiswa_baru', 'mahasiswa_aktif'];
        if (! in_array($category, $allowed, true) || $scope === []) {
            return ['data' => [], 'pagination' => ['total' => 0, 'page' => $page, 'per_page' => $perPage, 'last_page' => 1]];
        }

        // jenis_daftar hanya dipakai sebagai atribut rincian. Agregasi per
        // registrasi mencegah riwayat ganda menggandakan baris mahasiswa.
        $jenisDaftarSubquery = DB::table('list_riwayat_pendidikan_mahasiswa')
            ->select('id_registrasi_mahasiswa')
            ->selectRaw('MAX(nama_jenis_daftar) as jenis_daftar')
            ->groupBy('id_registrasi_mahasiswa');

        $query = DB::table('list_mahasiswa as lm')
            ->leftJoinSub($jenisDaftarSubquery, 'rpd', 'rpd.id_registrasi_mahasiswa', '=', 'lm.id_registrasi_mahasiswa')
            ->selectRaw('lm.id_registrasi_mahasiswa, MAX(lm.nim) as nim, MAX(lm.nama_mahasiswa) as nama, MAX(lm.nama_program_studi) as program_studi, MAX(lm.id_periode) as periode, MAX(lm.nama_status_mahasiswa) as status, MAX(rpd.jenis_daftar) as jenis_daftar')
            ->whereNotNull('lm.id_registrasi_mahasiswa')
            ->where('lm.id_registrasi_mahasiswa', '<>', '')
            ->whereRaw('LEFT(lm.id_periode, 4) = ?', [(string) $year])
            ->groupBy('lm.id_registrasi_mahasiswa');
        if ($scope !== null) {
            $query->whereIn('lm.id_prodi', $scope);
        }
        if ($category === 'mahasiswa_aktif') {
            $query->whereRaw("UPPER(TRIM(lm.nama_status_mahasiswa)) = 'AKTIF'");
        }
        if ($jenisDaftar === 'reguler') {
            $query->where(function ($builder): void {
                $builder->whereNull('rpd.jenis_daftar')
                    ->orWhereRaw("UPPER(TRIM(rpd.jenis_daftar)) = 'PESERTA DIDIK BARU'");
            });
        } elseif ($jenisDaftar === 'transfer') {
            $query->whereNotNull('rpd.jenis_daftar')
                ->whereRaw("UPPER(TRIM(rpd.jenis_daftar)) <> 'PESERTA DIDIK BARU'");
        }
        $baseQuery = $query->distinct();

        $total = DB::query()->fromSub((clone $baseQuery)->reorder(), 'detail_rows')->count();
        $items = $baseQuery->orderBy('nama')->forPage($page, $perPage)->get()
            ->map(function ($row) use ($year, $category): array {
                return [
                    'nim' => (string) ($row->nim ?? ''), 'nama' => (string) ($row->nama ?? ''),
                    'program_studi' => (string) ($row->program_studi ?? ''),
                    'periode' => (string) ($row->periode ?? ''),
                    'jenis_daftar' => (string) ($row->jenis_daftar ?? ''),
                    'status' => (string) ($row->status ?? ($category === 'calon_lulus' ? 'Lulus seleksi' : '-')),
                ];
            })->all();

        return ['data' => $items, 'pagination' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))]];
    }

    /**
     * @param  array<int, int>  $years
     * @param  array<int, string>|null  $scopeProdiIds  null = seluruh universitas
     * @return array<int, array{calon_pendaftar: int, calon_lulus: int, mahasiswa_baru: int, mahasiswa_aktif: int}>
     */
    private function loadData(array $years, ?array $scopeProdiIds): array
    {
        $cacheKey = 'sapto:seleksi-mahasiswa:v10:' . md5(json_encode([$years, $scopeProdiIds]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years, $scopeProdiIds): array {
            $yearBindings = array_map(fn (int $y): string => (string) $y, $years);
            $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
            $data = [];

            foreach ($years as $year) {
                $data[$year] = $this->emptyRow($year);
            }

            $scopeSql = $scopeProdiIds === null
                ? ''
                : 'AND lm.id_prodi IN (' . implode(',', array_fill(0, count($scopeProdiIds), '?')) . ')';
            $scopeBindings = $scopeProdiIds ?? [];
            $sql = "
                SELECT
                    LEFT(lm.id_periode, 4) AS tahun,
                    COUNT(DISTINCT lm.id_registrasi_mahasiswa) AS mahasiswa_baru,
                    COUNT(DISTINCT CASE
                        WHEN rpd.jenis_daftar IS NULL OR UPPER(TRIM(rpd.jenis_daftar)) = 'PESERTA DIDIK BARU'
                        THEN lm.id_registrasi_mahasiswa END) AS baru_reguler,
                    COUNT(DISTINCT CASE
                        WHEN rpd.jenis_daftar IS NOT NULL AND UPPER(TRIM(rpd.jenis_daftar)) <> 'PESERTA DIDIK BARU'
                        THEN lm.id_registrasi_mahasiswa END) AS baru_transfer,
                    COUNT(DISTINCT CASE
                        WHEN UPPER(TRIM(lm.nama_status_mahasiswa)) = 'AKTIF'
                        THEN lm.id_registrasi_mahasiswa END) AS mahasiswa_aktif,
                    COUNT(DISTINCT CASE
                        WHEN (rpd.jenis_daftar IS NULL OR UPPER(TRIM(rpd.jenis_daftar)) = 'PESERTA DIDIK BARU')
                         AND UPPER(TRIM(lm.nama_status_mahasiswa)) = 'AKTIF'
                        THEN lm.id_registrasi_mahasiswa END) AS aktif_reguler,
                    COUNT(DISTINCT CASE
                        WHEN rpd.jenis_daftar IS NOT NULL AND UPPER(TRIM(rpd.jenis_daftar)) <> 'PESERTA DIDIK BARU'
                         AND UPPER(TRIM(lm.nama_status_mahasiswa)) = 'AKTIF'
                        THEN lm.id_registrasi_mahasiswa END) AS aktif_transfer
                FROM list_mahasiswa lm
                LEFT JOIN (
                    SELECT id_registrasi_mahasiswa, MAX(nama_jenis_daftar) AS jenis_daftar
                    FROM list_riwayat_pendidikan_mahasiswa
                    GROUP BY id_registrasi_mahasiswa
                ) rpd ON rpd.id_registrasi_mahasiswa = lm.id_registrasi_mahasiswa
                WHERE lm.id_periode IS NOT NULL
                  AND lm.id_periode <> ''
                  AND lm.id_registrasi_mahasiswa IS NOT NULL
                  AND lm.id_registrasi_mahasiswa <> ''
                  AND LEFT(lm.id_periode, 4) IN ({$yearPlaceholders})
                  {$scopeSql}
                GROUP BY LEFT(lm.id_periode, 4)
            ";

            foreach (DB::select($sql, [...$yearBindings, ...$scopeBindings]) as $row) {
                $year = (int) $row->tahun;
                $data[$year] ??= $this->emptyRow($year);
                $data[$year]['mahasiswa_baru'] = (int) $row->mahasiswa_baru;
                $data[$year]['calon_lulus'] = (int) $row->mahasiswa_baru;
                $data[$year]['mahasiswa_aktif'] = (int) $row->mahasiswa_aktif;
                $data[$year]['baru_reguler'] = (int) $row->baru_reguler;
                $data[$year]['baru_transfer'] = (int) $row->baru_transfer;
                $data[$year]['aktif_reguler'] = (int) $row->aktif_reguler;
                $data[$year]['aktif_transfer'] = (int) $row->aktif_transfer;
            }

            return $data;
        });
    }

    private function emptyRow(int $year): array
    {
        return [
            'calon_pendaftar' => 0,
            'calon_lulus' => 0,
            'mahasiswa_baru' => 0,
            'mahasiswa_aktif' => 0,
            'baru_reguler' => 0,
            'baru_transfer' => 0,
            'aktif_reguler' => 0,
            'aktif_transfer' => 0,
        ];
    }

    private function buildNotice(array $data, array $years): ?string
    {
        $populatedYears = count(array_filter(
            $years,
            fn (int $y): bool =>
                ($data[$y]['calon_lulus'] ?? 0) > 0
                || ($data[$y]['mahasiswa_baru'] ?? 0) > 0
                || ($data[$y]['mahasiswa_aktif'] ?? 0) > 0
        ));

        if ($populatedYears === 0) {
            return 'Data mahasiswa baru/aktif belum tersedia untuk lingkup ini. Pendaftar dikosongkan karena data calon mahasiswa PMB belum tersedia pada sumber lokal.';
        }

        $catatan = 'Pendaftar dikosongkan karena data calon mahasiswa PMB belum tersedia. Lulus dan mahasiswa baru dihitung per registrasi pada list_mahasiswa; kategori reguler/transfer mengikuti jenis daftar registrasi. Mahasiswa aktif adalah registrasi pada angkatan tersebut dengan status AKTIF.';

        if ($populatedYears < count($years)) {
            return "{$populatedYears} dari " . count($years) . ' tahun akademik memiliki data. ' . $catatan;
        }

        return $catatan;
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        return Cache::remember('sapto:seleksi-mahasiswa:years:v7', $this->cacheTtl(), function (): array {
            $rows = DB::select("
                SELECT DISTINCT CAST(LEFT(id_periode, 4) AS UNSIGNED) AS thn
                FROM list_mahasiswa
                WHERE id_periode IS NOT NULL
                  AND id_periode <> ''
                  AND LEFT(id_periode, 4) REGEXP '^[0-9]{4}$'
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
