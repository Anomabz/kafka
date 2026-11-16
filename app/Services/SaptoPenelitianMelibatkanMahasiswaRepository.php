<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 6.a: kegiatan penelitian LPPM yang melibatkan mahasiswa.
 *
 * Ketua dari data LPPM dicocokkan dengan NIDN/NIP/NUPTK SISTER (-> id_sdm).
 * Penelitian masuk tabel prodi bila, pada TAHUN penelitian itu, ketuanya:
 *  - ber-homebase di prodi lingkup (list_penugasan_dosen, a_sp_homebase = 1)
 *    -> kolom DTPS ✓, ATAU
 *  - MENGAJAR kelas prodi lingkup (dosen_pengajar_kelas_kuliah, semester
 *    ganjil/genap/pendek tahun itu) -> kolom DTPS kosong
 * (keputusan pengguna, pola sama dengan 3.a.1 / EWMP / 3.b.x).
 * id_dosen feeder = id_sdm SISTER (terverifikasi >99%).
 */
class SaptoPenelitianMelibatkanMahasiswaRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    public function __construct(private readonly SaptoProgramStudiRepository $client) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();
        $years = [$ts - 2, $ts - 1, $ts];
        $items = $this->items($years, $scopeProdiIds);

        $rows = [];
        foreach ($items as $item) {
            $members = json_decode((string) $item['anggota_non_dosen'], true);
            if (! is_array($members)) {
                continue;
            }

            $studentNames = [];
            foreach ($members as $member) {
                if (! is_array($member)) {
                    continue;
                }

                $name = trim((string) ($member['nama'] ?? ''));
                if ($name !== '') {
                    $studentNames[] = $name;
                }
            }

            if ($studentNames === []) {
                continue;
            }

            $rows[] = [
                'nama_dosen' => trim((string) $item['nama_sdm']),
                'dtps' => $item['dtps'] ? '✓' : '',
                'tema' => trim((string) $item['nama_skema']),
                'nama_mahasiswa' => collect($studentNames)
                    ->values()
                    ->map(fn (string $name, int $index): string => ($index + 1).'. '.$name)
                    ->implode(', '),
                'judul_kegiatan' => trim((string) $item['judul']),
                'tahun' => (int) $item['tahun'],
            ];
        }

        // Tahun, lalu DTPS dulu, lalu judul dan nama dosen.
        usort($rows, static fn (array $a, array $b): int =>
            [$a['tahun'], $a['dtps'] === '✓' ? 0 : 1, $a['judul_kegiatan'], $a['nama_dosen']]
            <=> [$b['tahun'], $b['dtps'] === '✓' ? 0 : 1, $b['judul_kegiatan'], $b['nama_dosen']]
        );

        return [
            'table_code' => '6.a',
            'table_name' => 'Penelitian DTPS yang Melibatkan Mahasiswa',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [
                ['key' => 'nama_dosen', 'label' => 'Nama Dosen', 'align' => 'left'],
                ['key' => 'dtps', 'label' => 'DTPS', 'align' => 'left'],
                ['key' => 'tema', 'label' => 'Tema', 'align' => 'left'],
                ['key' => 'nama_mahasiswa', 'label' => 'Nama Mahasiswa', 'align' => 'left'],
                ['key' => 'judul_kegiatan', 'label' => 'Judul Kegiatan', 'align' => 'left'],
                ['key' => 'tahun', 'label' => 'Tahun', 'align' => 'left'],
            ],
            'rows' => $rows,
            'metrics' => [
                ['label' => 'Total judul (TS-2..TS)', 'value' => count($rows)],
                ['label' => 'Judul oleh DTPS', 'value' => count(array_filter($rows, fn (array $r): bool => $r['dtps'] === '✓'))],
                ['label' => 'Tahun (TS)', 'value' => $ts],
            ],
            'source' => ['name' => 'lppm_penelitian_dosen + sister_sdm + list_penugasan_dosen + dosen_pengajar_kelas_kuliah', 'partial' => false],
        ];
    }

    /**
     * @param  array<int, int>  $years
     * @param  array<int, string>|null  $scopeProdiIds
     * @return array<int, array<string, mixed>>
     */
    private function items(array $years, ?array $scopeProdiIds): array
    {
        if ($scopeProdiIds === []) {
            return [];
        }

        // 1) Penelitian LPPM + id_sdm ketua (tanpa syarat prodi dulu).
        $penelitian = DB::table('lppm_penelitian_dosen as l')
            ->join('sister_sdm as s', function ($join): void {
                $join->on(DB::raw('TRIM(s.nidn)'), '=', DB::raw('TRIM(l.id_ketua)'))
                    ->orOn(DB::raw('TRIM(s.nip)'), '=', DB::raw('TRIM(l.id_ketua)'))
                    ->orOn(DB::raw('TRIM(s.nuptk)'), '=', DB::raw('TRIM(l.id_ketua)'));
            })
            ->whereIn('l.tahun', $years)
            ->whereRaw("TRIM(COALESCE(l.id_ketua, '')) <> ''")
            ->select([
                'l.id_ketua', 'l.nama_skema', 'l.judul', 'l.tahun', 'l.anggota_non_dosen',
                's.nama_sdm', 's.id_sdm',
            ])
            ->distinct()
            ->get();

        if ($penelitian->isEmpty()) {
            return [];
        }

        $yearStrings = array_map('strval', $years);
        $prodiValid = function ($sub): void {
            $sub->select('id_prodi')
                ->from('program_studi')
                ->whereNotNull('nama_program_studi')
                ->where('nama_program_studi', '<>', '');
        };

        // 2) Homebase per tahun di lingkup (DTPS).
        $homebase = [];
        $hb = DB::table('list_penugasan_dosen')
            ->selectRaw('distinct id_dosen, substr(nama_tahun_ajaran, 1, 4) as tahun')
            ->where('a_sp_homebase', '1')
            ->whereRaw('substr(nama_tahun_ajaran, 1, 4) in ('.implode(',', array_fill(0, count($yearStrings), '?')).')', $yearStrings)
            ->whereIn('id_prodi', $prodiValid);
        if ($scopeProdiIds !== null) {
            $hb->whereIn('id_prodi', $scopeProdiIds);
        }
        foreach ($hb->cursor() as $row) {
            $homebase[(int) $row->tahun][(string) $row->id_dosen] = true;
        }

        // 3) Pengajar per tahun di lingkup.
        $pengajar = [];
        $pg = DB::table('dosen_pengajar_kelas_kuliah')
            ->selectRaw('distinct id_dosen, left(id_semester, 4) as tahun')
            ->whereRaw('left(id_semester, 4) in ('.implode(',', array_fill(0, count($yearStrings), '?')).')', $yearStrings)
            ->whereNotNull('id_dosen')
            ->where('id_dosen', '<>', '')
            ->whereIn('id_prodi', $prodiValid);
        if ($scopeProdiIds !== null) {
            $pg->whereIn('id_prodi', $scopeProdiIds);
        }
        foreach ($pg->cursor() as $row) {
            $pengajar[(int) $row->tahun][(string) $row->id_dosen] = true;
        }

        // 4) Saring: ketua homebase ATAU mengajar di lingkup pada tahun itu.
        $hasil = [];
        foreach ($penelitian as $row) {
            $tahun = (int) $row->tahun;
            $idSdm = (string) $row->id_sdm;
            $isDtps = isset($homebase[$tahun][$idSdm]);

            if (! $isDtps && ! isset($pengajar[$tahun][$idSdm])) {
                continue;
            }

            $hasil[] = [
                'nama_skema' => $row->nama_skema,
                'judul' => $row->judul,
                'tahun' => $tahun,
                'anggota_non_dosen' => $row->anggota_non_dosen,
                'nama_sdm' => $row->nama_sdm,
                'dtps' => $isDtps,
            ];
        }

        return $hasil;
    }

    /** @return array<int, int> */
    public function availableYears(): array
    {
        return DB::table('lppm_penelitian_dosen')
            ->select('tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->map(fn (mixed $year): int => (int) $year)
            ->filter(fn (int $year): bool => $year >= 2000 && $year <= 2100)
            ->values()
            ->all();
    }
}