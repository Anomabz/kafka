<?php

namespace App\Services;

use App\Services\Concerns\ResolvesDosenProfil;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.a.4) Dosen Tidak Tetap.
 *
 * Status dinilai PADA TS TERPILIH (TS = 4 digit awal nama_tahun_ajaran /
 * id_semester). Dosen yang tidak tetap di 2022 lalu tetap di 2023 tampil di
 * 3.a.4 untuk TS 2022 dan di 3.a.1 mulai TS 2023.
 *
 * "Dosen tetap" pada TS = punya penugasan dengan ikatan kerja tetap
 * (A/B/F/H/I/N), homebase 1 MAUPUN 0 -- definisi yang sama dengan Tabel 3.a.1.
 * Dosen tetap yang mengajar di PS lain masuk 3.a.1, bukan tabel ini.
 *
 * Dosen masuk daftar program studi B bila:
 *  1. punya penugasan di B pada TS dengan ikatan kerja G -- KODE G YANG
 *     MENENTUKAN (keputusan pengguna), APA PUN nilai a_sp_homebase-nya.
 *     Terverifikasi dosen G (contoh: ERWEDI, S1 Teknik Pertambangan)
 *     tercatat a_sp_homebase = 1, sehingga syarat lama "a_sp_homebase = 0"
 *     membuatnya hilang dari 3.a.1 maupun 3.a.4. PENGECUALIAN: bila pada TS
 *     yang sama dosen itu juga punya penugasan berkode tetap di prodi yang
 *     sama (diangkat CPNS/PPPK di tengah tahun), status tetap yang menang
 *     dan ia hanya tampil di 3.a.1; ATAU
 *  2. mengajar kelas di B pada TS (dosen_pengajar_kelas_kuliah, semester
 *     ganjil atau genap) dan BUKAN dosen tetap pada TS itu.
 * Roster dihimpun per pasangan dosen + prodi, lalu DITAMPILKAN SATU BARIS PER
 * DOSEN: prodi-prodinya digabung dalam kolom Program Studi (dipisah "; ").
 * Total = jumlah dosen unik.
 *
 * Pendidikan pasca sarjana (doktor, bila tidak ada magister), bidang
 * keahlian, jabatan akademik, dan sertifikat lewat trait ResolvesDosenProfil
 * (sama dengan 3.a.1). Kolom mata kuliah dan kesesuaian masih "-".
 */
class SaptoDosenTidakTetapRepository
{
    use ResolvesDosenProfil;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    /** Ikatan kerja tetap -- harus sama dengan Tabel 3.a.1. */
    private const IKATAN_TETAP = ['A', 'B', 'F', 'H', 'I', 'N'];

    /** Ikatan kerja yang MENENTUKAN dosen tidak tetap (keputusan pengguna). */
    private const IKATAN_TIDAK_TETAP = ['G'];

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

        $rows = collect($roster)
            ->map(function (array $row) use ($programIndex, $pendidikan, $jabatan, $sertifikat): array {
                $program = $programIndex->get((string) $row['id_prodi']);
                $dosenId = (string) $row['id_dosen'];
                $edu = $pendidikan[$dosenId] ?? ['magister' => null, 'doktor' => null, 'bidang_keahlian' => null];
                $sert = $sertifikat[$dosenId] ?? ['pendidik' => null, 'kompetensi' => null];

                return [
                    'dosen_id' => $dosenId,
                    'nama_dosen' => (string) $row['nama_dosen'],
                    'nidn' => $this->nidnAtauNuptk($row['nidn'] ?? null, $row['nuptk'] ?? null),
                    'nuptk' => (string) ($row['nuptk'] ?? ''),
                    'jenis_kelamin' => $this->jenisKelamin((string) ($row['jk'] ?? '')),
                    'program_studi' => SaptoProgramStudiRepository::cleanLabel(
                        $program['label'] ?? $row['nama_program_studi'] ?? 'Belum Dipetakan'
                    ),
                    'fakultas' => (string) ($program['fakultas'] ?? 'Belum Dipetakan'),
                    // Pendidikan pasca sarjana tertinggi: doktor, lalu magister.
                    'pendidikan_pasca_sarjana' => $edu['doktor'] ?? $edu['magister'],
                    'bidang_keahlian' => $edu['bidang_keahlian'],
                    'jabatan_akademik' => $jabatan[$dosenId] ?? null,
                    'sertifikat_pendidik' => $sert['pendidik'],
                    'sertifikat_kompetensi' => $sert['kompetensi'],
                ];
            })
            // SATU BARIS PER DOSEN (keputusan pengguna): dosen yang mengajar
            // di beberapa prodi tidak diulang; prodinya digabung dalam satu sel.
            ->groupBy('dosen_id')
            ->map(function ($group): array {
                $first = $group->first();
                $first['program_studi'] = $group->pluck('program_studi')->unique()->sort()->implode('; ');
                $first['fakultas'] = $group->pluck('fakultas')->unique()->sort()->implode('; ');

                foreach ($group as $row) {
                    foreach ($row as $field => $value) {
                        if (($first[$field] ?? null) === null || $first[$field] === '' || $first[$field] === '-') {
                            $first[$field] = $value;
                        }
                    }
                }

                return $first;
            })
            ->values()
            ->sortBy([
                ['fakultas', 'asc'],
                ['program_studi', 'asc'],
                ['nama_dosen', 'asc'],
            ])
            ->values()
            ->map(function (array $row, int $index): array {
                $row['no'] = $index + 1;

                return $row;
            })
            ->values();

        return [
            'table_code' => '3.a.4',
            'table_name' => 'Dosen Tidak Tetap',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'left'],
                ['key' => 'nama_dosen', 'label' => 'Nama Dosen', 'align' => 'left'],
                ['key' => 'nidn', 'label' => 'NIDN/NIDK', 'align' => 'left'],
                ['key' => 'program_studi', 'label' => 'Program Studi (Penugasan)', 'align' => 'left'],
                ['key' => 'pendidikan_pasca_sarjana', 'label' => 'Pendidikan Pasca Sarjana', 'align' => 'left'],
                ['key' => 'bidang_keahlian', 'label' => 'Bidang Keahlian', 'align' => 'left'],
                ['key' => 'jabatan_akademik', 'label' => 'Jabatan Akademik', 'align' => 'left'],
                ['key' => 'sertifikat_pendidik', 'label' => 'Sertifikat Pendidik Profesional', 'align' => 'left'],
                ['key' => 'sertifikat_kompetensi', 'label' => 'Sertifikat Kompetensi/Profesi/Industri', 'align' => 'left'],
                ['key' => 'mk_diakreditasi', 'label' => 'Mata Kuliah yang Diampu pada PS yang Diakreditasi', 'align' => 'left'],
                ['key' => 'kesesuaian_mk', 'label' => 'Kesesuaian Bidang Keahlian dengan Mata Kuliah yang Diampu', 'align' => 'left'],
            ],
            'rows' => $rows,
            'metrics' => $this->metrics($rows->all(), $ts),
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * Satu baris per pasangan dosen + prodi (lihat docblock kelas).
     *
     * @param  array<int, string>|null  $scopeProdiIds  null = semua prodi
     * @return array<int, array<string, mixed>>
     */
    private function roster(int $ts, ?array $scopeProdiIds): array
    {
        $cacheKey = 'sapto:dosen-tidak-tetap:v7:'.md5(json_encode([$ts, $scopeProdiIds]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($ts, $scopeProdiIds): array {
            $tetap = array_flip($this->dosenTetapIds($ts));
            $byPair = [];

            // 1) Penugasan berkode G di prodi pada TS, homebase 0 maupun 1.
            //    Kode G yang menentukan: selalu masuk, tidak disaring status tetap.
            $penugasan = DB::table('list_penugasan_dosen')
                ->select('id_dosen', 'nama_dosen', 'nidn', 'nuptk', 'jk', 'id_prodi', 'nama_program_studi')
                ->whereRaw('substr(nama_tahun_ajaran,1,4) = ?', [(string) $ts])
                ->whereIn('id_ikatan_kerja', self::IKATAN_TIDAK_TETAP)
                ->whereIn('id_prodi', $this->prodiValidSubquery());

            if ($scopeProdiIds !== null) {
                $penugasan->whereIn('id_prodi', $scopeProdiIds);
            }

            // Kecuali dosen yang pada TS yang sama JUGA punya penugasan berkode
            // tetap di prodi yang sama -> status tetap yang menang (mis. DEWI
            // PRATITA TS 2025: G hb0 -> N hb1 di S1 Pend. Ekonomi, diangkat
            // PPPK di tengah tahun). Tanpa ini ia tercatat ganda di 3.a.1 & 3.a.4.
            $tetapDiProdi = $this->pasanganTetap($ts);

            foreach ($penugasan->cursor() as $row) {
                if (isset($tetapDiProdi[(string) $row->id_dosen.'|'.(string) $row->id_prodi])) {
                    continue;
                }
                $this->tambahPasangan($byPair, [], (array) $row);
            }

            // 2) Mengajar kelas di prodi pada TS dan bukan dosen tetap (dosen
            //    tetap yang mengajar di PS lain masuk Tabel 3.a.1).
            $pengajar = DB::table('dosen_pengajar_kelas_kuliah')
                ->select('id_dosen', 'nama_dosen', 'nidn', 'nuptk', 'id_prodi')
                ->whereRaw('left(id_semester,4) = ?', [(string) $ts])
                ->whereNotNull('id_dosen')
                ->where('id_dosen', '<>', '')
                ->whereIn('id_prodi', $this->prodiValidSubquery());

            if ($scopeProdiIds !== null) {
                $pengajar->whereIn('id_prodi', $scopeProdiIds);
            }

            foreach ($pengajar->cursor() as $row) {
                $this->tambahPasangan($byPair, $tetap, (array) $row + ['jk' => null, 'nama_program_studi' => null]);
            }

            // Jenis kelamin untuk dosen yang hanya ditemukan lewat data mengajar.
            $tanpaJk = array_values(array_unique(array_map(
                fn (array $row): string => (string) $row['id_dosen'],
                array_filter($byPair, fn (array $row): bool => trim((string) ($row['jk'] ?? '')) === ''),
            )));

            if ($tanpaJk !== []) {
                $jk = DB::table('list_penugasan_dosen')
                    ->whereIn('id_dosen', $tanpaJk)
                    ->whereNotNull('jk')
                    ->where('jk', '<>', '')
                    ->pluck('jk', 'id_dosen')
                    ->all();

                foreach ($byPair as &$row) {
                    if (trim((string) ($row['jk'] ?? '')) === '') {
                        $row['jk'] = $jk[(string) $row['id_dosen']] ?? null;
                    }
                }
                unset($row);
            }

            return array_values($byPair);
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $byPair
     * @param  array<string, int>  $tetap
     * @param  array<string, mixed>  $row
     */
    private function tambahPasangan(array &$byPair, array $tetap, array $row): void
    {
        $dosenId = (string) ($row['id_dosen'] ?? '');
        $prodiId = (string) ($row['id_prodi'] ?? '');

        if ($dosenId === '' || $prodiId === '' || isset($tetap[$dosenId])) {
            return;
        }

        $key = "{$dosenId}|{$prodiId}";

        if (! isset($byPair[$key])) {
            $byPair[$key] = $row;

            return;
        }

        // Lengkapi kolom kosong dari sumber lain (mis. jk/NIDN dari penugasan).
        foreach ($row as $field => $value) {
            if (trim((string) ($byPair[$key][$field] ?? '')) === '' && trim((string) ($value ?? '')) !== '') {
                $byPair[$key][$field] = $value;
            }
        }
    }

    /**
     * id_dosen yang berstatus dosen tetap pada TS (definisi 3.a.1).
     *
     * @return array<int, string>
     */
    private function dosenTetapIds(int $ts): array
    {
        // Homebase 1 maupun 0: kode tetap (A/B/F/H/I/N) yang menentukan.
        // Dosen A + homebase 0 (mis. HERSYAMSI TS 2025) masuk 3.a.1, bukan sini.
        return DB::table('list_penugasan_dosen')
            ->whereIn('id_ikatan_kerja', self::IKATAN_TETAP)
            ->whereRaw('substr(nama_tahun_ajaran,1,4) = ?', [(string) $ts])
            ->whereNotNull('id_dosen')
            ->distinct()
            ->pluck('id_dosen')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    /**
     * Pasangan "id_dosen|id_prodi" yang punya penugasan berkode tetap pada TS
     * (homebase 1 maupun 0).
     *
     * @return array<string, true>
     */
    private function pasanganTetap(int $ts): array
    {
        $pairs = [];
        $rows = DB::table('list_penugasan_dosen')
            ->select('id_dosen', 'id_prodi')
            ->whereIn('id_ikatan_kerja', self::IKATAN_TETAP)
            ->whereRaw('substr(nama_tahun_ajaran,1,4) = ?', [(string) $ts])
            ->whereNotNull('id_dosen')
            ->cursor();

        foreach ($rows as $row) {
            $pairs[(string) $row->id_dosen.'|'.(string) $row->id_prodi] = true;
        }

        return $pairs;
    }

    /**
     * id_prodi yang benar-benar program studi (bukan biro/unit lain).
     */
    private function prodiValidSubquery(): \Closure
    {
        return function ($sub): void {
            $sub->select('id_prodi')
                ->from('program_studi')
                ->whereNotNull('nama_program_studi')
                ->where('nama_program_studi', '<>', '');
        };
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
        $l = count(array_filter($rows, fn (array $r): bool => $r['jenis_kelamin'] === 'Laki-laki'));
        $p = count(array_filter($rows, fn (array $r): bool => $r['jenis_kelamin'] === 'Perempuan'));

        return [
            ['label' => 'Total dosen tidak tetap', 'value' => count($rows)],
            ['label' => 'Laki-laki', 'value' => $l],
            ['label' => 'Perempuan', 'value' => $p],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:dosen-tidak-tetap:years:v2', $this->cacheTtl(), function (): array {
            $rows = DB::select("
                SELECT DISTINCT SUBSTR(nama_tahun_ajaran, 1, 4) AS thn
                FROM list_penugasan_dosen
                WHERE nama_tahun_ajaran IS NOT NULL
                  AND nama_tahun_ajaran <> ''
                ORDER BY thn DESC
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
            'name' => 'list_penugasan_dosen & dosen_pengajar_kelas_kuliah (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}