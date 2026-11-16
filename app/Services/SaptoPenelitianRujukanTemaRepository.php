<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 6.b) Penelitian DTPS yang Menjadi Rujukan Tema Tesis/Disertasi
 * (LKPS: diisi program Magister/Magister Terapan/Doktor/Doktor Terapan).
 *
 * Sumber:
 * - list_aktivitas_mahasiswa: aktivitas berjenis Tesis/Disertasi, beserta
 *   program studi (id_prodi), semester (id_semester) dan judulnya.
 * - dosen_pembimbing: pembimbing tiap aktivitas + nama mahasiswa.
 * - list_penugasan_dosen: DTPS = dosen yang homebase-nya (a_sp_homebase = 1)
 *   di prodi tesis itu pada tahun ajaran yang sama -- definisi yang sama
 *   dengan Tabel 3.a.1.
 *
 * Satu baris = satu (dosen, mahasiswa, judul). Satu tesis bisa tercatat di
 * beberapa semester (dan kadang dobel di dosen_pembimbing); tahunnya diambil
 * dari semester terakhir di jendela TS-2..TS.
 *
 * "Tema Penelitian sesuai Roadmap" tidak ada di PD UNSRI (roadmap penelitian
 * adalah dokumen prodi), jadi dikosongkan dan tabel ditandai sebagian.
 *
 * Versi sebelum 2026-09-30 membaca kolom id_prodi/nama_semester yang tidak
 * ada di list_bimbing_mahasiswa, sehingga tabel ini selalu "Gagal dimuat".
 */
class SaptoPenelitianRujukanTemaRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    private const TABLE_CODE = '6.b';

    private const TABLE_NAME = 'Penelitian DTPS yang Menjadi Rujukan Tema Tesis/Disertasi';

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'nama_dosen', 'label' => 'Nama Dosen', 'align' => 'left'],
        ['key' => 'tema_penelitian', 'label' => 'Tema Penelitian sesuai Roadmap', 'align' => 'left'],
        ['key' => 'nama_mahasiswa', 'label' => 'Nama Mahasiswa', 'align' => 'left'],
        ['key' => 'judul_tesis', 'label' => 'Judul Tesis/Disertasi', 'align' => 'left'],
        ['key' => 'tahun', 'label' => 'Tahun (YYYY)', 'align' => 'left'],
    ];

    private const SOURCE = 'list_aktivitas_mahasiswa + dosen_pembimbing + list_penugasan_dosen (sinkronisasi PD UNSRI)';

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();

        if ($programStudi === null) {
            return $this->envelope($ts, $fakultas, null, [],
                'Tabel 6.b disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan daftar penelitian yang menjadi rujukan tema tesis/disertasi.');
        }

        $years = [$ts - 2, $ts - 1, $ts];
        $rows = $this->loadRows($years, (string) $programStudi['id']);

        $numbered = [];
        foreach ($rows as $index => $row) {
            $numbered[] = ['no' => $index + 1] + $row;
        }

        return $this->envelope($ts, $fakultas, $programStudi, $numbered,
            'Kolom "Tema Penelitian sesuai Roadmap" tidak tersedia di PD UNSRI (roadmap penelitian adalah dokumen program studi) dan perlu diisi manual. Baris hanya memuat tesis/disertasi yang dibimbing DTPS, yaitu dosen dengan homebase di program studi ini pada tahun yang sama.');
    }

    /**
     * @param  list<int>  $years
     * @return list<array{nama_dosen: string, tema_penelitian: string, nama_mahasiswa: string, judul_tesis: string, tahun: int}>
     */
    private function loadRows(array $years, string $prodiId): array
    {
        $cacheKey = 'sapto:penelitian-rujukan-tema:v2:'.md5(json_encode([$years, $prodiId]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years, $prodiId): array {
            // Tahun di-bind sebagai teks: substr() menghasilkan teks, dan SQLite
            // (dipakai tes) tidak menyamakan teks dengan angka seperti MySQL.
            $yearStrings = array_map('strval', $years);
            $in = implode(',', array_fill(0, count($yearStrings), '?'));

            $raw = DB::select("
                select
                    dp.id_dosen,
                    dp.nama_dosen,
                    dp.id_registrasi_mahasiswa,
                    dp.nama_mahasiswa,
                    lam.judul,
                    substr(lam.id_semester, 1, 4) as tahun
                from list_aktivitas_mahasiswa lam
                inner join dosen_pembimbing dp on dp.id_aktivitas = lam.id_aktivitas
                where lam.id_prodi = ?
                  and (lower(lam.nama_jenis_aktivitas) like '%tesis%' or lower(lam.nama_jenis_aktivitas) like '%disertasi%')
                  and substr(lam.id_semester, 1, 4) in ({$in})
                  and exists (
                      select 1 from list_penugasan_dosen p
                      where p.id_dosen = dp.id_dosen
                        and p.a_sp_homebase = '1'
                        and p.id_prodi = lam.id_prodi
                        and substr(p.nama_tahun_ajaran, 1, 4) = substr(lam.id_semester, 1, 4)
                  )
            ", array_merge([$prodiId], $yearStrings));

            $unik = [];

            foreach ($raw as $row) {
                $judul = trim((string) ($row->judul ?? ''));
                $mahasiswa = trim((string) ($row->id_registrasi_mahasiswa ?? '')) ?: mb_strtolower(trim((string) ($row->nama_mahasiswa ?? '')));
                $kunci = implode('|', [(string) $row->id_dosen, $mahasiswa, mb_strtolower(preg_replace('/\s+/', ' ', $judul))]);
                $tahun = (int) $row->tahun;

                if (isset($unik[$kunci]) && $unik[$kunci]['tahun'] >= $tahun) {
                    continue;
                }

                $unik[$kunci] = [
                    'nama_dosen' => trim((string) ($row->nama_dosen ?? '')) ?: '-',
                    'tema_penelitian' => '-',
                    'nama_mahasiswa' => trim((string) ($row->nama_mahasiswa ?? '')) ?: '-',
                    'judul_tesis' => $judul !== '' ? $judul : '-',
                    'tahun' => $tahun,
                ];
            }

            $rows = array_values($unik);
            usort($rows, fn (array $a, array $b): int => [$b['tahun'], $a['nama_dosen'], $a['nama_mahasiswa']] <=> [$a['tahun'], $b['nama_dosen'], $b['nama_mahasiswa']]);

            return $rows;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function envelope(int $ts, ?array $fakultas, ?array $programStudi, array $rows, string $notice): array
    {
        $dosen = count(array_unique(array_column($rows, 'nama_dosen')));

        return [
            'table_code' => self::TABLE_CODE,
            'table_name' => self::TABLE_NAME,
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => $programStudi === null ? [] : self::COLUMNS,
            'rows' => $rows,
            'metrics' => $programStudi === null ? [] : [
                ['label' => 'Tesis/disertasi dibimbing DTPS (TS-2..TS)', 'value' => count($rows)],
                ['label' => 'Tesis/disertasi (TS)', 'value' => count(array_filter($rows, fn (array $r): bool => $r['tahun'] === $ts))],
                ['label' => 'DTPS pembimbing', 'value' => $dosen],
            ],
            'notice' => $notice,
            'source' => ['name' => self::SOURCE, 'partial' => true],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        return Cache::remember('sapto:penelitian-rujukan-tema:years:v2', $this->cacheTtl(), function (): array {
            $rows = DB::select("
                select distinct substr(id_semester, 1, 4) as thn
                from list_aktivitas_mahasiswa
                where (lower(nama_jenis_aktivitas) like '%tesis%' or lower(nama_jenis_aktivitas) like '%disertasi%')
                  and id_semester is not null and id_semester <> ''
                order by thn desc
            ");

            return array_values(array_filter(
                array_map(fn (object $row): int => (int) $row->thn, $rows),
                fn (int $year): bool => $year >= 2000 && $year <= 2100,
            ));
        });
    }
}
