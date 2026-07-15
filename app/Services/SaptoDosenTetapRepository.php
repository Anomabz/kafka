<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.a.1) Dosen Tetap Perguruan Tinggi. Sumber: list_penugasan_dosen
 * (penugasan dosen hasil sinkronisasi). "Dosen tetap" diambil dari baris
 * homebase (a_sp_homebase = 1): prodi homebase itulah program studi tempat
 * dosen dihitung sebagai dosen tetap. Difilter menurut tahun ajaran terpilih
 * (TS = 4 digit awal nama_tahun_ajaran), satu baris per dosen.
 *
 * Pendidikan, jabatan akademik, dan sertifikat diambil dari riwayat_pendidikan_
 * dosen/riwayat_fungsional_dosen/riwayat_sertifikasi_dosen (sudah tersinkron).
 * Mata kuliah yang diampu masih belum bisa dipetakan: dosen_pengajar_kelas_
 * kuliah.id_substansi nyaris selalu kosong dan yang terisi pun tidak match ke
 * mata_kuliah.id_matkul manapun (terverifikasi 0 baris cocok) — jadi kolom
 * terkait mata kuliah dan "Kesesuaian dengan Kompetensi Inti PS" (penilaian
 * manual/subjektif) tetap tampil "-".
 */
class SaptoDosenTetapRepository
{
    use ResolvesSaptoScope;

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->latestYear();
        $programIndex = $programStudis->keyBy('id');

        $roster = $scopeProdiIds === [] ? [] : $this->roster($ts, $scopeProdiIds);
        $dosenIds = array_values(array_unique(array_map(fn (array $row): string => (string) $row['id_dosen'], $roster)));

        $pendidikan = $this->pendidikanByDosen($dosenIds);
        $jabatan = $this->jabatanAkademikByDosen($dosenIds);
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
                    'nidn' => (string) ($row['nidn'] ?? ''),
                    'nuptk' => (string) ($row['nuptk'] ?? ''),
                    'jenis_kelamin' => $this->jenisKelamin((string) ($row['jk'] ?? '')),
                    'program_studi' => SaptoProgramStudiRepository::cleanLabel($program['label'] ?? $row['nama_program_studi'] ?? 'Belum Dipetakan'),
                    'fakultas' => (string) ($program['fakultas'] ?? 'Belum Dipetakan'),
                    'pendidikan_magister' => $edu['magister'],
                    'pendidikan_doktor' => $edu['doktor'],
                    'bidang_keahlian' => $edu['bidang_keahlian'],
                    'jabatan_akademik' => $jabatan[$dosenId] ?? null,
                    'sertifikat_pendidik' => $sert['pendidik'],
                    'sertifikat_kompetensi' => $sert['kompetensi'],
                ];
            })
            ->sortBy([['fakultas', 'asc'], ['program_studi', 'asc'], ['nama_dosen', 'asc']])
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
            // Kolom mengikuti format BAN-PT Tabel 3.a.1. Kolom mata kuliah dan
            // "Kesesuaian dengan Kompetensi Inti PS" belum bisa diisi (lihat
            // docblock kelas) sehingga tampil "-" — generic renderer memetakan
            // key yang tidak ada menjadi "-".
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
     * Satu baris per dosen: baris homebase pada tahun ajaran terpilih. Bila
     * seorang dosen punya lebih dari satu baris homebase (mis. tercatat di dua
     * prodi), ambil yang pertama secara deterministik (id_prodi terkecil).
     *
     * @param  array<int, string>|null  $scopeProdiIds
     * @return array<int, array<string, mixed>>
     */
    private function roster(int $ts, ?array $scopeProdiIds): array
    {
        $cacheKey = 'sapto:dosen-tetap:v1:'.md5(json_encode([$ts, $scopeProdiIds]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($ts, $scopeProdiIds): array {
            $query = DB::table('list_penugasan_dosen')
                ->select('id_dosen', 'nama_dosen', 'nidn', 'nuptk', 'jk', 'id_prodi', 'nama_program_studi')
                ->where('a_sp_homebase', '1')
                ->whereRaw('substr(nama_tahun_ajaran,1,4) = ?', [(string) $ts]);

            if ($scopeProdiIds !== null) {
                $query->whereIn('id_prodi', $scopeProdiIds);
            }

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

            return array_map(fn (object $row): array => (array) $row, array_values($byDosen));
        });
    }

    /**
     * Pendidikan magister/doktor + bidang keahlian per dosen dari
     * riwayat_pendidikan_dosen. Sp-1 disetarakan ke level magister, Sp-2 ke
     * level doktor (konvensi BAN-PT untuk prodi kesehatan/profesi). Bila
     * seorang dosen punya lebih dari satu baris di level yang sama, ambil
     * yang tahun_lulus-nya paling baru. "Bidang Keahlian" adalah heuristik:
     * bidang studi dari jenjang pendidikan tertinggi yang tercatat (tidak ada
     * kolom bidang keahlian eksplisit di sumber).
     *
     * @param  array<int, string>  $dosenIds
     * @return array<string, array{magister: ?string, doktor: ?string, bidang_keahlian: ?string}>
     */
    private function pendidikanByDosen(array $dosenIds): array
    {
        if ($dosenIds === []) {
            return [];
        }

        $magisterJenjang = ['S2', 'S2 Terapan', 'Sp-1'];
        $doktorJenjang = ['S3', 'S3 Terapan', 'Sp-2'];
        $rankOf = fn (string $jenjang): int => match (true) {
            in_array($jenjang, $doktorJenjang, true) => 3,
            in_array($jenjang, $magisterJenjang, true) => 2,
            $jenjang === 'S1' => 1,
            default => 0,
        };

        $rows = DB::table('riwayat_pendidikan_dosen')
            ->select('id_dosen', 'nama_jenjang_pendidikan', 'nama_bidang_studi', 'nama_perguruan_tinggi', 'tahun_lulus')
            ->whereIn('id_dosen', $dosenIds)
            ->whereIn('nama_jenjang_pendidikan', [...$magisterJenjang, ...$doktorJenjang, 'S1'])
            ->orderBy('tahun_lulus', 'desc')
            ->get();

        $byDosen = [];

        foreach ($rows as $row) {
            $dosenId = (string) $row->id_dosen;
            $jenjang = (string) $row->nama_jenjang_pendidikan;
            // "*" adalah konvensi feeder PDDIKTI untuk bidang studi "tidak diisi".
            $bidangStudi = ($row->nama_bidang_studi && $row->nama_bidang_studi !== '*') ? (string) $row->nama_bidang_studi : null;
            $perguruanTinggi = trim((string) ($row->nama_perguruan_tinggi ?? ''));
            // Hindari "- - Universitas X" saat bidang studi kosong: tampilkan
            // hanya yang ada (bidang, PT, atau "-" jika dua-duanya kosong).
            $label = trim(implode(' - ', array_filter([$bidangStudi, $perguruanTinggi ?: null]))) ?: '-';

            $byDosen[$dosenId] ??= ['magister' => null, 'doktor' => null, 'bidang_keahlian' => null, 'rank' => 0];

            if (in_array($jenjang, $magisterJenjang, true) && $byDosen[$dosenId]['magister'] === null) {
                $byDosen[$dosenId]['magister'] = $label;
            }

            if (in_array($jenjang, $doktorJenjang, true) && $byDosen[$dosenId]['doktor'] === null) {
                $byDosen[$dosenId]['doktor'] = $label;
            }

            $rank = $rankOf($jenjang);

            if ($bidangStudi !== null && $rank > $byDosen[$dosenId]['rank']) {
                $byDosen[$dosenId]['bidang_keahlian'] = $bidangStudi;
                $byDosen[$dosenId]['rank'] = $rank;
            }
        }

        return $byDosen;
    }

    /**
     * Jabatan akademik terbaru (mulai_sk_jabatan) per dosen, dibatasi ke 5
     * jenjang akademik dosen yang dikenal BAN-PT — jabatan fungsional
     * non-akademik di sumber (pustakawan, arsiparis, dst) diabaikan.
     * "Profesor" di sumber == "Guru Besar" pada nomenklatur BAN-PT.
     *
     * @param  array<int, string>  $dosenIds
     * @return array<string, string>
     */
    private function jabatanAkademikByDosen(array $dosenIds): array
    {
        if ($dosenIds === []) {
            return [];
        }

        $academicRanks = ['Tenaga Pengajar', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Profesor'];

        $rows = DB::table('riwayat_fungsional_dosen')
            ->select('id_dosen', 'nama_jabatan_fungsional')
            ->whereIn('id_dosen', $dosenIds)
            ->whereIn('nama_jabatan_fungsional', $academicRanks)
            ->orderBy('mulai_sk_jabatan', 'desc')
            ->get();

        $byDosen = [];

        foreach ($rows as $row) {
            $dosenId = (string) $row->id_dosen;

            $byDosen[$dosenId] ??= $row->nama_jabatan_fungsional === 'Profesor' ? 'Guru Besar' : (string) $row->nama_jabatan_fungsional;
        }

        return $byDosen;
    }

    /**
     * Sertifikat pendidik profesional (Serdos) + kompetensi/profesi terbaru
     * per dosen dari riwayat_sertifikasi_dosen. "Sertifikasi Asesor BKD"
     * sengaja tidak dipetakan ke kolom manapun — itu sertifikasi penilai beban
     * kerja dosen, konsepnya beda dari sertifikat pendidik/kompetensi.
     *
     * @param  array<int, string>  $dosenIds
     * @return array<string, array{pendidik: ?string, kompetensi: ?string}>
     */
    private function sertifikatByDosen(array $dosenIds): array
    {
        if ($dosenIds === []) {
            return [];
        }

        $pendidikJenis = ['Sertifikasi Dosen', 'Sertifikasi Dosen Kemenag'];
        $kompetensiJenis = ['Sertifikasi Kompetensi', 'Sertifikasi Profesi'];

        $rows = DB::table('riwayat_sertifikasi_dosen')
            ->select('id_dosen', 'nama_jenis_sertifikasi', 'tahun_sertifikasi')
            ->whereIn('id_dosen', $dosenIds)
            ->whereIn('nama_jenis_sertifikasi', [...$pendidikJenis, ...$kompetensiJenis])
            ->orderBy('tahun_sertifikasi', 'desc')
            ->get();

        $byDosen = [];

        foreach ($rows as $row) {
            $dosenId = (string) $row->id_dosen;
            $jenis = (string) $row->nama_jenis_sertifikasi;
            $label = $row->tahun_sertifikasi ? "{$jenis} ({$row->tahun_sertifikasi})" : $jenis;

            $byDosen[$dosenId] ??= ['pendidik' => null, 'kompetensi' => null];

            if (in_array($jenis, $pendidikJenis, true) && $byDosen[$dosenId]['pendidik'] === null) {
                $byDosen[$dosenId]['pendidik'] = $label;
            }

            if (in_array($jenis, $kompetensiJenis, true) && $byDosen[$dosenId]['kompetensi'] === null) {
                $byDosen[$dosenId]['kompetensi'] = $label;
            }
        }

        return $byDosen;
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
        // = riwayat pendidikannya belum tercatat di sumber).
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

    private function latestYear(): int
    {
        return $this->availableYears()[0] ?? (int) now()->year;
    }

    private function sourcePayload(): array
    {
        return [
            'name' => 'Pangkalan Data UNSRI (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}
