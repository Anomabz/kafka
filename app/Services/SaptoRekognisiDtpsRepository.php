<?php

namespace App\Services;

use App\Services\Concerns\ResolvesPublikasiDtpsRoster;
use App\Services\Concerns\ResolvesSaptoScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.b.1) Pengakuan/Rekognisi DTPS.
 *
 * Tabel LISTING (bukan pivot) untuk TS-2..TS, satu baris = satu bentuk
 * rekognisi. Roster DTPS memakai {@see ResolvesPublikasiDtpsRoster} --
 * keluarga SISTER yang sama dengan 3.b.4/3.b.7, karena seluruh sumber
 * rekognisi juga ber-id_sdm (bukan id_dosen feeder).
 *
 * Lima sumber digabung, masing-masing jadi satu bentuk rekognisi:
 *  - sister_list_pembicara          -> pembicara/narasumber pertemuan ilmiah
 *  - sister_list_orasi_ilmiah       -> orasi ilmiah di perguruan tinggi
 *  - sister_list_penghargaan        -> penghargaan
 *  - sister_list_anggota_profesi    -> keanggotaan organisasi profesi
 *  - sister_list_visiting_scientist -> visiting scientist
 *
 * TINGKAT (kolom Wilayah/Nasional/Internasional) hanya bisa diturunkan dari
 * sister_list_pembicara: kolom `kategori_kegiatan`-nya memuat teks eksplisit
 * "(tingkat lokal|nasional|internasional)" pada 2.899 dari 2.900 baris.
 * Empat sumber lain TIDAK punya informasi tingkat sama sekali
 * (sister_detail_penghargaan.tingkat_penghargaan NULL untuk seluruh 476
 * baris, jenis_penghargaan seluruhnya "Lainnya"), jadi centangnya sengaja
 * DIKOSONGKAN, bukan ditebak -- pengusul mengisinya manual. Jumlah baris
 * yang perlu diisi manual dilaporkan lewat `notice` dan `metrics`.
 *
 * Bidang keahlian diambil dari sister_list_pendidikan_formal pada jenjang
 * tertinggi (S3 > S2 > S1); "*" adalah konvensi sumber untuk "tidak diisi"
 * sehingga diperlakukan kosong -- sama seperti Tabel 3.a.1.
 */
class SaptoRekognisiDtpsRepository
{
    use ResolvesPublikasiDtpsRoster;
    use ResolvesSaptoScope;

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'nama_dosen', 'label' => 'Nama Dosen', 'align' => 'left'],
        ['key' => 'bidang_keahlian', 'label' => 'Bidang Keahlian', 'align' => 'left'],
        ['key' => 'rekognisi', 'label' => 'Rekognisi dan Bukti Pendukung', 'align' => 'left'],
        ['key' => 'tingkat_wilayah', 'label' => 'Wilayah', 'align' => 'left'],
        ['key' => 'tingkat_nasional', 'label' => 'Nasional', 'align' => 'left'],
        ['key' => 'tingkat_internasional', 'label' => 'Internasional', 'align' => 'left'],
        ['key' => 'tahun', 'label' => 'Tahun (YYYY)', 'align' => 'left'],
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
        private readonly SaptoTsResolver $ts,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts->ts();

        if ($programStudi === null) {
            return $this->envelope($ts, $fakultas, $programStudi, [], [],
                'Tabel 3.b.1 disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan daftar rekognisi DTPS.');
        }

        $years = $this->ts->tigaTahun($ts);
        $jenjang = ['S1', 'S2', 'S3', 'D3', 'D4', 'Sarjana Terapan', 'Magister Terapan', 'Doktor Terapan'];

        $dtpsIdsByYear = [];
        foreach ($years as $year) {
            $dtpsIdsByYear[$year] = $scopeProdiIds === [] ? [] : array_flip($this->publikasiDtpsIds($year, $scopeProdiIds, $jenjang));
        }

        $rekognisiByYear = $this->rekognisiRows($years);

        $items = [];
        foreach ($years as $year) {
            foreach ($rekognisiByYear[$year] ?? [] as $row) {
                if (! isset($dtpsIdsByYear[$year][$row['id_sdm']])) {
                    continue;
                }

                $items[] = $row + ['tahun' => $year];
            }
        }

        usort($items, fn (array $a, array $b): int => $b['tahun'] <=> $a['tahun']
            ?: strcmp($a['nama_dosen'], $b['nama_dosen'])
            ?: strcmp($a['rekognisi'], $b['rekognisi']));

        $idSdms = array_values(array_unique(array_column($items, 'id_sdm')));
        $keahlian = $this->bidangKeahlian($idSdms);

        $rows = [];
        foreach ($items as $index => $row) {
            $rows[] = [
                'no' => $index + 1,
                'nama_dosen' => $row['nama_dosen'],
                'bidang_keahlian' => $keahlian[$row['id_sdm']] ?? '-',
                'rekognisi' => $row['rekognisi'],
                'tingkat_wilayah' => $row['tingkat'] === 'wilayah' ? '✓' : '',
                'tingkat_nasional' => $row['tingkat'] === 'nasional' ? '✓' : '',
                'tingkat_internasional' => $row['tingkat'] === 'internasional' ? '✓' : '',
                'tahun' => $row['tahun'],
            ];
        }

        $tanpaTingkat = count(array_filter($items, fn (array $row): bool => $row['tingkat'] === null));

        return $this->envelope($ts, $fakultas, $programStudi, $rows, $this->metrics($rows, $items, $ts),
            $tanpaTingkat > 0
                ? "{$tanpaTingkat} dari ".count($items).' baris belum punya data tingkat di sumber (hanya kegiatan pembicara yang mencantumkannya), jadi kolom Wilayah/Nasional/Internasional dikosongkan dan perlu diisi manual.'
                : null);
    }

    /**
     * Semua bentuk rekognisi TS-2..TS, dikelompokkan per tahun, belum
     * disaring roster DTPS. Di-cache karena lima sumber ini kecil dan
     * dipakai berulang lintas scope.
     *
     * @param  array<int, int>  $years
     * @return array<int, array<int, array{id_sdm: string, nama_dosen: string, rekognisi: string, tingkat: ?string}>>
     */
    private function rekognisiRows(array $years): array
    {
        $cacheKey = 'sapto:rekognisi-dtps:rows:v1:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $nama = $this->namaDosen();
            $byYear = array_fill_keys($years, []);
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);

            $tambah = function (?string $idSdm, ?string $tahun, string $teks, ?string $tingkat) use (&$byYear, $nama, $years): void {
                $tahun = (int) substr((string) $tahun, 0, 4);
                $idSdm = (string) $idSdm;

                if ($idSdm === '' || ! in_array($tahun, $years, true) || trim($teks) === '') {
                    return;
                }

                $byYear[$tahun][] = [
                    'id_sdm' => $idSdm,
                    'nama_dosen' => $nama[$idSdm] ?? '-',
                    'rekognisi' => $teks,
                    'tingkat' => $tingkat,
                ];
            };

            foreach (DB::table('sister_list_pembicara')
                ->select('id_sdm', 'kategori_kegiatan', 'judul_makalah', 'nama_pertemuan', 'penyelenggara', 'tanggal_pelaksanaan')
                ->whereRaw('substr(tanggal_pelaksanaan,1,4) in ('.implode(',', array_fill(0, count($yearStrings), '?')).')', $yearStrings)
                ->cursor() as $row) {
                $tambah($row->id_sdm, $row->tanggal_pelaksanaan, $this->gabung([
                    'Pembicara/narasumber: '.trim((string) $row->judul_makalah),
                    trim((string) $row->nama_pertemuan),
                    trim((string) $row->penyelenggara),
                ]), $this->tingkatDariKategori($row->kategori_kegiatan));
            }

            foreach (DB::table('sister_list_orasi_ilmiah')
                ->select('id_sdm', 'judul_makalah', 'nama_pertemuan', 'penyelenggara', 'tanggal_pelaksanaan')
                ->whereRaw('substr(tanggal_pelaksanaan,1,4) in ('.implode(',', array_fill(0, count($yearStrings), '?')).')', $yearStrings)
                ->cursor() as $row) {
                $tambah($row->id_sdm, $row->tanggal_pelaksanaan, $this->gabung([
                    'Orasi ilmiah: '.trim((string) $row->judul_makalah),
                    trim((string) $row->nama_pertemuan),
                    trim((string) $row->penyelenggara),
                ]), null);
            }

            foreach (DB::table('sister_list_penghargaan')
                ->select('id_sdm', 'nama', 'jenis_penghargaan', 'instansi_pemberi', 'tahun')
                ->whereIn('tahun', $yearStrings)
                ->cursor() as $row) {
                $tambah($row->id_sdm, $row->tahun, $this->gabung([
                    'Penghargaan: '.trim((string) $row->nama),
                    trim((string) $row->instansi_pemberi),
                ]), null);
            }

            foreach (DB::table('sister_list_anggota_profesi')
                ->select('id_sdm', 'nama_organisasi', 'peran', 'instansi_profesi', 'tanggal_mulai_keanggotaan')
                ->whereRaw('substr(tanggal_mulai_keanggotaan,1,4) in ('.implode(',', array_fill(0, count($yearStrings), '?')).')', $yearStrings)
                ->cursor() as $row) {
                $tambah($row->id_sdm, $row->tanggal_mulai_keanggotaan, $this->gabung([
                    'Anggota organisasi profesi: '.trim((string) $row->nama_organisasi),
                    trim((string) $row->peran),
                    trim((string) $row->instansi_profesi),
                ]), null);
            }

            foreach (DB::table('sister_list_visiting_scientist')
                ->select('id_sdm', 'perguruan_tinggi', 'lama_kegiatan', 'tanggal')
                ->whereRaw('substr(tanggal,1,4) in ('.implode(',', array_fill(0, count($yearStrings), '?')).')', $yearStrings)
                ->cursor() as $row) {
                $tambah($row->id_sdm, $row->tanggal, $this->gabung([
                    'Visiting scientist: '.trim((string) ($row->perguruan_tinggi ?? '')),
                    trim((string) $row->lama_kegiatan),
                ]), null);
            }

            return $byYear;
        });
    }

    /**
     * "(tingkat lokal|nasional|internasional)" di kategori_kegiatan
     * sister_list_pembicara. Nilai lain -> null (dikosongkan, bukan ditebak).
     */
    private function tingkatDariKategori(?string $kategori): ?string
    {
        $kategori = mb_strtolower(trim((string) $kategori));

        return match (true) {
            str_contains($kategori, 'internasional') => 'internasional',
            str_contains($kategori, 'nasional') => 'nasional',
            str_contains($kategori, 'lokal'), str_contains($kategori, 'wilayah') => 'wilayah',
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $bagian
     */
    private function gabung(array $bagian): string
    {
        $bersih = array_filter(
            array_map(fn (string $x): string => rtrim(trim($x), ':'), $bagian),
            fn (string $x): bool => $x !== '' && $x !== '-',
        );

        return implode(' - ', $bersih);
    }

    /**
     * @return array<string, string> id_sdm => nama
     */
    private function namaDosen(): array
    {
        return Cache::remember('sapto:rekognisi-dtps:nama:v1', $this->cacheTtl(), function (): array {
            return DB::table('sister_sdm')
                ->whereNotNull('nama_sdm')
                ->where('nama_sdm', '<>', '')
                ->pluck('nama_sdm', 'id_sdm')
                ->map(fn (mixed $nama): string => (string) $nama)
                ->all();
        });
    }

    /**
     * Bidang studi jenjang tertinggi per dosen; "*" = tidak diisi di sumber.
     *
     * @param  array<int, string>  $idSdms
     * @return array<string, string>
     */
    private function bidangKeahlian(array $idSdms): array
    {
        if ($idSdms === []) {
            return [];
        }

        $urutan = ['S1' => 1, 'D4' => 1, 'Sp-1' => 2, 'S2' => 2, 'Sp-2' => 3, 'S3' => 3];
        $terbaik = [];
        $hasil = [];

        foreach (DB::table('sister_list_pendidikan_formal')
            ->select('id_sdm', 'jenjang_pendidikan', 'bidang_studi')
            ->whereIn('id_sdm', $idSdms)
            ->cursor() as $row) {
            $bidang = trim((string) $row->bidang_studi);

            if ($bidang === '' || $bidang === '*') {
                continue;
            }

            $id = (string) $row->id_sdm;
            $peringkat = $urutan[trim((string) $row->jenjang_pendidikan)] ?? 0;

            if (! isset($terbaik[$id]) || $peringkat > $terbaik[$id]) {
                $terbaik[$id] = $peringkat;
                $hasil[$id] = $bidang;
            }
        }

        return $hasil;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, array $items, int $ts): array
    {
        return [
            ['label' => 'Total rekognisi (TS-2..TS)', 'value' => count($rows)],
            ['label' => 'Internasional', 'value' => count(array_filter($rows, fn (array $r): bool => $r['tingkat_internasional'] === '✓'))],
            ['label' => 'Nasional', 'value' => count(array_filter($rows, fn (array $r): bool => $r['tingkat_nasional'] === '✓'))],
            ['label' => 'Wilayah', 'value' => count(array_filter($rows, fn (array $r): bool => $r['tingkat_wilayah'] === '✓'))],
            ['label' => 'Perlu isi tingkat manual', 'value' => count(array_filter($items, fn (array $r): bool => $r['tingkat'] === null))],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $metrics
     */
    private function envelope(int $ts, ?array $fakultas, ?array $programStudi, array $rows, array $metrics, ?string $notice): array
    {
        return [
            'table_code' => '3.b.1',
            'table_name' => 'Pengakuan/Rekognisi DTPS',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => $rows === [] && $notice !== null && $programStudi === null ? [] : self::COLUMNS,
            'rows' => $rows,
            'metrics' => $metrics,
            'notice' => $notice,
            'source' => [
                'name' => 'sister_list_pembicara, orasi_ilmiah, penghargaan, anggota_profesi, visiting_scientist & sister_list_penugasan (sinkronisasi lokal)',
                'partial' => true,
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:rekognisi-dtps:years:v1', $this->cacheTtl(), function (): array {
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
}
