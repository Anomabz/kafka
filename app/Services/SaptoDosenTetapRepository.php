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
 * Catatan: kolom akreditasi lengkap (pendidikan, jabatan akademik, sertifikat
 * pendidik, bidang keahlian) belum tersedia karena tabel biodata/riwayat dosen
 * masih kosong di sumber, jadi roster ini menampilkan identitas + homebase.
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

        $rows = collect($roster)
            ->map(function (array $row) use ($programIndex): array {
                $program = $programIndex->get((string) $row['id_prodi']);

                return [
                    'dosen_id' => (string) $row['id_dosen'],
                    'nama_dosen' => (string) $row['nama_dosen'],
                    'nidn' => (string) ($row['nidn'] ?? ''),
                    'nuptk' => (string) ($row['nuptk'] ?? ''),
                    'jenis_kelamin' => $this->jenisKelamin((string) ($row['jk'] ?? '')),
                    'program_studi' => (string) ($program['label'] ?? $row['nama_program_studi'] ?? 'Belum Dipetakan'),
                    'fakultas' => (string) ($program['fakultas'] ?? 'Belum Dipetakan'),
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
            // Kolom mengikuti format BAN-PT Tabel 3.a.1. Kolom pendidikan pasca
            // sarjana, bidang keahlian, jabatan akademik, sertifikat, dan mata
            // kuliah belum bisa diisi (tabel biodata/riwayat/kelas dosen masih
            // kosong di sumber) sehingga tampil "-" — generic renderer memetakan
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
            ['label' => 'Total dosen tetap', 'value' => count($rows)],
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
