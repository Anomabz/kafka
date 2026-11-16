<?php
namespace App\Services;
use App\Services\Concerns\ResolvesDosenProfil;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/**
 * Tabel 3.a.1) Dosen Tetap Perguruan Tinggi -- dosen tetap PT yang ditugaskan
 * di program studi yang diakreditasi.
 *
 * "Dosen tetap PT" diambil dari list_penugasan_dosen baris homebase
 * (a_sp_homebase = 1) dengan ikatan kerja tetap (A/B/F/H/I/N) pada tahun
 * ajaran terpilih (TS = 4 digit awal nama_tahun_ajaran), satu baris per dosen
 * (homebase ganda -> id_prodi terkecil, deterministik).
 *
 * Seorang dosen tetap masuk daftar program studi B bila:
 *  1. homebase-nya di B, ATAU
 *  2. homebase-nya di prodi lain tetapi MENGAJAR kelas di B pada TS (semester
 *     ganjil atau genap TS, dari dosen_pengajar_kelas_kuliah.id_prodi +
 *     id_semester) -- sesuai keputusan pengguna.
 * Kolom "Program Studi (Homebase)" tetap menampilkan homebase aslinya; dosen
 * homebase diurutkan lebih dulu, lalu dosen dari prodi lain (status
 * penugasan tidak ditampilkan sebagai kolom). Pada lingkup universitas
 * semua dosen tetap tampil sekali dengan status Homebase. Aturan ini hanya
 * untuk Tabel 3.a.1; roster DTPS tabel 3.b (ResolvesPublikasiDtpsRoster) tetap
 * berbasis homebase.
 *
 * Pendidikan, jabatan akademik, dan sertifikat lewat trait ResolvesDosenProfil
 * (dipakai bersama Tabel 3.a.4).
 * Mata kuliah yang diampu dibaca per dosen dari aktivitas_mengajar_dosen
 * pada TS dan dibagi menurut lingkup prodi. Kolom kesesuaian kompetensi dan
 * kesesuaian bidang dengan mata kuliah memerlukan penilaian manual dan tetap
 * tampil "-".
 */
class SaptoDosenTetapRepository
{
    use ResolvesDosenProfil;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    private const PENUGASAN_HOMEBASE = 'Homebase';
    private const PENUGASAN_MENGAJAR = 'Mengajar (homebase PS lain)';

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}
    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);
        $ts ??= $this->ts();
        $programIndex = $programStudis->keyBy('id');
        $roster = $scopeProdiIds === [] ? [] : $this->roster($ts, $scopeProdiIds);
        $dosenIds = array_values(array_unique(array_map(fn (array $row): string => (string) $row['id_dosen'], $roster)));
        $pendidikan = $this->pendidikanByDosen($dosenIds);
        $jabatan = $this->jabatanAkademikByDosen($dosenIds, $ts);
        $sertifikat = $this->sertifikatByDosen($dosenIds);
        $mataKuliah = $scopeProdiIds === [] ? [] : $this->mataKuliahByDosen($ts, $dosenIds, $scopeProdiIds);
        $rows = collect($roster)
            ->map(function (array $row) use ($programIndex, $pendidikan, $jabatan, $sertifikat, $mataKuliah): array {
                $program = $programIndex->get((string) $row['id_prodi']);
                $dosenId = (string) $row['id_dosen'];
                $edu = $pendidikan[$dosenId] ?? ['magister' => null, 'doktor' => null, 'bidang_keahlian' => null];
                $sert = $sertifikat[$dosenId] ?? ['pendidik' => null, 'kompetensi' => null];
                $mk = $mataKuliah[$dosenId] ?? ['diakreditasi' => [], 'ps_lain' => []];
                return [
                    'dosen_id' => $dosenId,
                    'nama_dosen' => (string) $row['nama_dosen'],
                    // Kolom NIDN/NIDK: NUPTK bila NIDN kosong.
                    'nidn' => $this->nidnAtauNuptk($row['nidn'] ?? null, $row['nuptk'] ?? null),
                    'nuptk' => (string) ($row['nuptk'] ?? ''),
                    'jenis_kelamin' => $this->jenisKelamin((string) ($row['jk'] ?? '')),
                    'program_studi' => SaptoProgramStudiRepository::cleanLabel($program['label'] ?? $row['nama_program_studi'] ?? 'Belum Dipetakan'),
                    'penugasan' => (string) ($row['penugasan'] ?? self::PENUGASAN_HOMEBASE),
                    'fakultas' => (string) ($program['fakultas'] ?? 'Belum Dipetakan'),
                    'pendidikan_magister' => $edu['magister'],
                    'pendidikan_doktor' => $edu['doktor'],
                    'bidang_keahlian' => $edu['bidang_keahlian'],
                    'jabatan_akademik' => $jabatan[$dosenId] ?? null,
                    'sertifikat_pendidik' => $sert['pendidik'],
                    'sertifikat_kompetensi' => $sert['kompetensi'],
                    'mk_diakreditasi' => $mk['diakreditasi'] !== [] ? implode(', ', $mk['diakreditasi']) : null,
                    'mk_ps_lain' => $mk['ps_lain'] !== [] ? implode(', ', $mk['ps_lain']) : null,
                ];
            })
            // Homebase dulu, lalu dosen dari prodi lain yang mengajar di PS.
            ->sortBy([['penugasan', 'asc'], ['fakultas', 'asc'], ['program_studi', 'asc'], ['nama_dosen', 'asc']])
            ->values()
            ->map(function (array $row, int $index): array {
                $row['no'] = $index + 1;
                return $row;
            })
            ->values();
        return [
            'table_code' => '3.a.1',
            'table_name' => 'Dosen Tetap Perguruan Tinggi',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            // Kolom mengikuti format BAN-PT Tabel 3.a.1. Status penugasan
            // (Homebase / Mengajar) hanya dipakai untuk urutan, tidak
            // ditampilkan. Nama mata kuliah berasal dari aktivitas mengajar;
            // kedua kolom kesesuaian tetap manual dan tidak dihitung otomatis.
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'left'],
                ['key' => 'nama_dosen', 'label' => 'Nama Dosen', 'align' => 'left'],
                ['key' => 'nidn', 'label' => 'NIDN/NIDK', 'align' => 'left'],
                ['key' => 'program_studi', 'label' => 'Program Studi (Homebase)', 'align' => 'left'],
                ['key' => 'pendidikan_magister', 'label' => 'Pendidikan Magister/Spesialis', 'align' => 'left'],
                ['key' => 'pendidikan_doktor', 'label' => 'Pendidikan Doktor/Spesialis', 'align' => 'left'],
                ['key' => 'bidang_keahlian', 'label' => 'Bidang Keahlian', 'align' => 'left'],
                ['key' => 'kesesuaian_kompetensi', 'label' => 'Kesesuaian dengan Kompetensi Inti PS', 'align' => 'left'],
                ['key' => 'jabatan_akademik', 'label' => 'Jabatan Akademik', 'align' => 'left'],
                ['key' => 'sertifikat_pendidik', 'label' => 'Sertifikat Pendidik Profesional', 'align' => 'left'],
                ['key' => 'sertifikat_kompetensi', 'label' => 'Sertifikat Kompetensi/Profesi/Industri', 'align' => 'left'],
                ['key' => 'mk_diakreditasi', 'label' => 'Mata Kuliah pada PS yang Diakreditasi', 'align' => 'left'],
                ['key' => 'kesesuaian_mk', 'label' => 'Kesesuaian Bidang Keahlian dengan Mata Kuliah', 'align' => 'left'],
                ['key' => 'mk_ps_lain', 'label' => 'Mata Kuliah pada PS Lain', 'align' => 'left'],
            ],
            'rows' => $rows,
            'metrics' => $this->metrics($rows->all(), $ts),
            'source' => $this->sourcePayload(),
        ];
    }
    /**
     * Dosen tetap yang tampil di lingkup terpilih: homebase di lingkup itu,
     * atau homebase di luar lingkup tetapi mengajar kelas di lingkup itu pada
     * TS. Lingkup universitas (null) = semua dosen tetap, status Homebase.
     *
     * @param  array<int, string>|null  $scopeProdiIds
     * @return array<int, array<string, mixed>>
     */
    private function roster(int $ts, ?array $scopeProdiIds): array
    {
        $cacheKey = 'sapto:dosen-tetap:v3:'.md5(json_encode([$ts, $scopeProdiIds]));
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($ts, $scopeProdiIds): array {
            $semua = $this->dosenTetapPt($ts);

            if ($scopeProdiIds === null) {
                return array_values(array_map(
                    fn (array $row): array => $row + ['penugasan' => self::PENUGASAN_HOMEBASE],
                    $semua,
                ));
            }

            $scope = array_flip(array_map('strval', $scopeProdiIds));
            $pengajar = array_flip($this->pengajarDiProdi($ts, $scopeProdiIds));
            $result = [];

            foreach ($semua as $dosenId => $row) {
                $homebaseDiScope = isset($scope[(string) $row['id_prodi']]);

                if (! $homebaseDiScope && ! isset($pengajar[$dosenId])) {
                    continue;
                }

                $row['penugasan'] = $homebaseDiScope ? self::PENUGASAN_HOMEBASE : self::PENUGASAN_MENGAJAR;
                $result[] = $row;
            }

            return $result;
        });
    }
    /**
     * Seluruh dosen tetap PT pada TS, satu baris per dosen (homebase). Bila
     * seorang dosen punya lebih dari satu baris homebase, ambil id_prodi
     * terkecil (deterministik).
     *
     * @return array<string, array<string, mixed>> id_dosen => baris
     */
    private function dosenTetapPt(int $ts): array
    {
        return Cache::remember('sapto:dosen-tetap:semua:v1:'.$ts, $this->cacheTtl(), function () use ($ts): array {
            $query = DB::table('list_penugasan_dosen')
                ->select(
                    'id_dosen',
                    'nama_dosen',
                    'nidn',
                    'nuptk',
                    'jk',
                    'id_prodi',
                    'nama_program_studi',
                    'id_ikatan_kerja'
                )
                ->where('a_sp_homebase', '1')
                ->whereIn('id_ikatan_kerja', ['A', 'B', 'F', 'H', 'I', 'N'])
                ->whereRaw('substr(nama_tahun_ajaran,1,4) = ?', [(string) $ts])
                // Beberapa baris homebase menunjuk ke unit non-akademik (biro,
                // bukan program studi) — pegawai biro bukan dosen. Filter
                // hanya id_prodi yang benar-benar terdaftar di program_studi.
                ->whereIn('id_prodi', function ($sub) {
                    $sub->select('id_prodi')
                        ->from('program_studi')
                        ->whereNotNull('nama_program_studi')
                        ->where('nama_program_studi', '<>', '');
                });
            $byDosen = [];
            foreach ($query->cursor() as $row) {
                $dosenId = (string) $row->id_dosen;
                if ($dosenId === '') {
                    continue;
                }
                $existing = $byDosen[$dosenId] ?? null;
                if ($existing === null || (string) $row->id_prodi < (string) $existing->id_prodi) {
                    $byDosen[$dosenId] = $row;
                }
            }
            return array_map(fn (object $row): array => (array) $row, $byDosen);
        });
    }
    /**
     * id_dosen yang mengajar kelas di prodi-prodi lingkup pada TS (semester
     * ganjil atau genap; 4 digit awal id_semester = TS).
     *
     * @param  array<int, string>  $scopeProdiIds
     * @return array<int, string>
     */
    private function pengajarDiProdi(int $ts, array $scopeProdiIds): array
    {
        if ($scopeProdiIds === []) {
            return [];
        }

        return DB::table('dosen_pengajar_kelas_kuliah')
            ->whereRaw('left(id_semester,4) = ?', [(string) $ts])
            ->whereIn('id_prodi', $scopeProdiIds)
            ->whereNotNull('id_dosen')
            ->where('id_dosen', '<>', '')
            ->distinct()
            ->pluck('id_dosen')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Daftar mata kuliah per dosen dari aktivitas_mengajar_dosen pada TS,
     * dipisahkan menurut prodi lingkup. Empat karakter awal id_periode
     * adalah tahun akademik (contoh 20251 = TS 2025).
     *
     * @param  array<int, string>  $dosenIds
     * @param  array<int, string>|null  $scopeProdiIds
     * @return array<string, array{diakreditasi: array<int, string>, ps_lain: array<int, string>}>
     */
    private function mataKuliahByDosen(int $ts, array $dosenIds, ?array $scopeProdiIds): array
    {
        if ($dosenIds === []) {
            return [];
        }

        $cacheKey = 'sapto:dosen-tetap:mk:v1:'.md5(json_encode([$ts, $scopeProdiIds, $dosenIds]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($ts, $dosenIds, $scopeProdiIds): array {
            // SQLite tidak menyediakan fungsi LEFT; SUBSTR memberi hasil
            // yang sama untuk pengujian lokal. Database utama memakai LEFT.
            $periodExpression = DB::connection()->getDriverName() === 'sqlite'
                ? 'substr(id_periode,1,4)'
                : 'left(id_periode,4)';

            $rows = [];
            foreach (array_chunk($dosenIds, 1000) as $chunk) {
                $chunkQuery = DB::table('aktivitas_mengajar_dosen')
                    ->select('id_dosen', 'id_prodi', 'nama_mata_kuliah')
                    ->whereRaw("{$periodExpression} = ?", [(string) $ts])
                    ->whereIn('id_dosen', $chunk)
                    ->whereNotNull('id_dosen')
                    ->whereNotNull('nama_mata_kuliah')
                    ->where('nama_mata_kuliah', '<>', '')
                    ->distinct();

                foreach ($chunkQuery->get() as $row) {
                    $dosenId = (string) $row->id_dosen;
                    $namaMk = trim((string) $row->nama_mata_kuliah);
                    if ($dosenId === '' || $namaMk === '') {
                        continue;
                    }

                    $kelompok = $scopeProdiIds === null || in_array((string) $row->id_prodi, $scopeProdiIds, true)
                        ? 'diakreditasi'
                        : 'ps_lain';
                    $rows[$dosenId][$kelompok][$namaMk] = $namaMk;
                }
            }

            $result = [];
            foreach ($rows as $dosenId => $groups) {
                $result[$dosenId] = [];
                foreach (['diakreditasi', 'ps_lain'] as $group) {
                    $names = array_values($groups[$group] ?? []);
                    sort($names, SORT_NATURAL | SORT_FLAG_CASE);
                    $result[$dosenId][$group] = $names;
                }
            }

            return $result;
        });
    }

    private function jenisKelamin(string $jk): string
    {
        return match (strtoupper(trim($jk))) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '-',
        };
    }
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, int $ts): array
    {
        // Jenjang pendidikan tertinggi per dosen: punya riwayat doktor (S3/
        // S3 Terapan/Sp-2) -> dihitung S3 saja meski juga punya S2; punya
        // magister (S2/S2 Terapan/Sp-1) tanpa doktor -> S2. Dosen tanpa
        // riwayat keduanya tidak masuk kartu manapun (selisih terhadap total
        // = riwayat pendidikannya belum tercatat di sumber). Total mencakup
        // dosen homebase + dosen dari prodi lain yang mengajar di PS.
        $s3 = count(array_filter($rows, fn (array $r): bool => $r['pendidikan_doktor'] !== null));
        $s2 = count(array_filter($rows, fn (array $r): bool => $r['pendidikan_doktor'] === null && $r['pendidikan_magister'] !== null));
        return [
            ['label' => 'Total dosen tetap', 'value' => count($rows)],
            ['label' => 'Pendidikan S2 (Magister)', 'value' => $s2],
            ['label' => 'Pendidikan S3 (Doktor)', 'value' => $s3],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }
    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:dosen-tetap:years:v1', $this->cacheTtl(), function (): array {
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
            'name' => 'Pangkalan Data UNSRI (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}
