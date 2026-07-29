<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Basis dua varian Tabel 8.b BAN-PT yang menghitung prestasi mahasiswa
 * dengan cara persis sama tapi jenis prestasi berbeda:
 *
 * - {@see SaptoPrestasiAkademikRepository} "8.b.1) Prestasi Akademik
 *   Mahasiswa" -- jenis_prestasi = "Sains" (lomba esai/riset/debat ilmiah).
 * - {@see SaptoPrestasiNonAkademikRepository} "8.b.2) Prestasi Non-akademik
 *   Mahasiswa" -- jenis_prestasi = "Olahraga", "Seni", dan "Lain-lain"
 *   (kategori residual: sample manual menunjukkan isinya campur -- ada
 *   Karya Tulis Ilmiah berdampingan dengan Finalis Bujang Gadis -- tapi
 *   klaim akademik harus eksplisit "Sains", jadi selain itu dianggap
 *   non-akademik).
 *
 * Daftar baris (bukan pivot per tahun) untuk TS-2/TS-1/TS, mengikuti format
 * BAN-PT: Nama Kegiatan, Waktu Perolehan, Tingkat (Lokal/Wilayah | Nasional
 * | Internasional -- tiga kolom centang, sama seperti Tabel 1 Kerjasama),
 * Prestasi yang Dicapai.
 *
 * Sumber: list_prestasi_mahasiswa (hasil sinkronisasi, 1.805 baris). Tabel
 * ini TIDAK punya id_prodi sendiri -- prodi tiap mahasiswa dicari lewat
 * id_mahasiswa ke list_mahasiswa, dan diambil baris ber-id_periode PALING
 * BARU sebagai prodi kanonik (bukan sembarang baris): ~8% mahasiswa
 * berprestasi punya lebih dari satu id_prodi berbeda di riwayatnya (pindah
 * prodi), jadi join naif bisa menghitung prestasi yang sama di dua prodi
 * atau salah prodi. Dilakukan di PHP (bukan SQL JOIN tunggal) karena
 * volumenya kecil (~2rb baris) dan aturan "menang" (periode terbaru) lebih
 * jelas ditulis sebagai perbandingan daripada subquery korelatif.
 *
 * Pemetaan `nama_tingkat_prestasi` (taksonomi sumber) ke 3 kolom Tingkat:
 * "Nasional" -> Nasional, "Internasional" -> Internasional. Semua nilai lain
 * (Sekolah, Propinsi, Kecamatan, Kab/kota, "Lainnya", atau nilai tak
 * dikenal) -> Lokal/Wilayah, dipakai sebagai kategori terendah/catch-all
 * -- perlu diverifikasi manual untuk baris "Lainnya".
 *
 * Kolom "Prestasi yang Dicapai" dibentuk dari `peringkat` (angka mentah,
 * tanpa teks di sumber) sebagai "Juara {n}"; peringkat kosong/0/tak valid
 * ditampilkan "-" (perlu diisi manual).
 *
 * Cache data mentah (per tahun) sengaja memakai kunci per-KELAS (bukan
 * dibagi lintas varian seperti SaptoPublikasiDtpsRepository): jenis
 * prestasi sudah difilter di query sumbernya, jadi isinya memang beda
 * antar varian, tidak ada untungnya berbagi.
 */
abstract class SaptoPrestasiRepository
{
    use ResolvesSaptoScope;

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    abstract protected function tableCode(): string;

    abstract protected function tableName(): string;

    /**
     * Nilai nama_jenis_prestasi yang termasuk varian ini.
     *
     * @return array<int, string>
     */
    abstract protected function jenisPrestasi(): array;

    abstract protected function cachePrefix(): string;

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->latestYear();
        $years = [$ts - 2, $ts - 1, $ts];

        $rows = $scopeProdiIds === []
            ? []
            : $this->prestasiRows($years)
                ->filter(fn (array $row): bool => $scopeProdiIds === null || in_array($row['id_prodi'], $scopeProdiIds, true))
                ->sortByDesc('tahun_sort')
                ->values()
                ->map(fn (array $row, int $index): array => [
                    'no' => $index + 1,
                    'nama_kegiatan' => $row['nama_kegiatan'],
                    'tahun_perolehan' => $row['tahun_perolehan'],
                    'tingkat_lokal' => $row['tingkat'] === 'wilayah' ? '✓' : '',
                    'tingkat_nasional' => $row['tingkat'] === 'nasional' ? '✓' : '',
                    'tingkat_internasional' => $row['tingkat'] === 'internasional' ? '✓' : '',
                    'prestasi_dicapai' => $row['prestasi_dicapai'],
                ])
                ->all();

        return [
            'table_code' => $this->tableCode(),
            'table_name' => $this->tableName(),
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'left'],
                ['key' => 'nama_kegiatan', 'label' => 'Nama Kegiatan', 'align' => 'left'],
                ['key' => 'tahun_perolehan', 'label' => 'Waktu Perolehan (YYYY)', 'align' => 'left'],
                ['key' => 'tingkat_lokal', 'label' => 'Lokal/Wilayah', 'align' => 'left'],
                ['key' => 'tingkat_nasional', 'label' => 'Nasional', 'align' => 'left'],
                ['key' => 'tingkat_internasional', 'label' => 'Internasional', 'align' => 'left'],
                ['key' => 'prestasi_dicapai', 'label' => 'Prestasi yang Dicapai', 'align' => 'left'],
            ],
            'rows' => $rows,
            'metrics' => $this->metrics($rows, $ts),
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * Baris prestasi TS-2..TS (sudah disaring jenisPrestasi()) dengan prodi
     * kanonik dilekatkan, belum disaring scope. Di-cache per kelas karena
     * jenisnya sudah beda per varian.
     *
     * @param  array<int, int>  $years
     * @return Collection<int, array{id_prodi: ?string, nama_kegiatan: string, tahun_perolehan: int, tahun_sort: int, tingkat: string, prestasi_dicapai: string}>
     */
    private function prestasiRows(array $years): Collection
    {
        $cacheKey = "sapto:{$this->cachePrefix()}:rows:v1:".md5(json_encode($years));

        $cached = Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);

            $prestasi = DB::table('list_prestasi_mahasiswa')
                ->select('id_prestasi', 'id_mahasiswa', 'nama_prestasi', 'nama_tingkat_prestasi', 'peringkat', 'tahun_prestasi')
                ->whereIn('tahun_prestasi', $yearStrings)
                ->whereIn('nama_jenis_prestasi', $this->jenisPrestasi())
                ->get();

            $prodiByMahasiswa = $this->canonicalProdiFor(
                $prestasi->pluck('id_mahasiswa')->unique()->filter()->values()->all()
            );

            return $prestasi
                ->map(function (object $row) use ($prodiByMahasiswa): array {
                    $tahun = (int) $row->tahun_prestasi;

                    return [
                        'id_prodi' => $prodiByMahasiswa[(string) $row->id_mahasiswa] ?? null,
                        'nama_kegiatan' => (string) $row->nama_prestasi !== '' ? (string) $row->nama_prestasi : '-',
                        'tahun_perolehan' => $tahun,
                        'tahun_sort' => $tahun,
                        'tingkat' => $this->classifyTingkat($row->nama_tingkat_prestasi),
                        'prestasi_dicapai' => $this->prestasiLabel($row->peringkat),
                    ];
                })
                ->values()
                ->all();
        });

        return collect($cached);
    }

    /**
     * Prodi kanonik per mahasiswa: baris list_mahasiswa dengan id_periode
     * paling baru menang (lihat docblock kelas). Dibatasi ke id_mahasiswa
     * yang benar-benar punya prestasi supaya tidak menyapu seluruh
     * list_mahasiswa (200rb+ baris).
     *
     * @param  array<int, string>  $mahasiswaIds
     * @return array<string, ?string> id_mahasiswa => id_prodi
     */
    private function canonicalProdiFor(array $mahasiswaIds): array
    {
        if ($mahasiswaIds === []) {
            return [];
        }

        $result = [];
        $latestPeriode = [];

        foreach (DB::table('list_mahasiswa')
            ->select('id_mahasiswa', 'id_prodi', 'id_periode')
            ->whereIn('id_mahasiswa', $mahasiswaIds)
            ->cursor() as $row) {
            $id = (string) $row->id_mahasiswa;
            $periode = (string) ($row->id_periode ?? '');

            if (! isset($latestPeriode[$id]) || $periode > $latestPeriode[$id]) {
                $latestPeriode[$id] = $periode;
                $result[$id] = (string) $row->id_prodi;
            }
        }

        return $result;
    }

    private function classifyTingkat(?string $nama): string
    {
        $nama = trim((string) $nama);

        if ($nama === 'Internasional') {
            return 'internasional';
        }

        if ($nama === 'Nasional') {
            return 'nasional';
        }

        // Sekolah/Propinsi/Kecamatan/Kab-kota/"Lainnya"/nilai tak dikenal.
        return 'wilayah';
    }

    private function prestasiLabel(?string $peringkat): string
    {
        $n = (int) $peringkat;

        return $peringkat !== null && $peringkat !== '' && $n > 0 ? "Juara {$n}" : '-';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, int $ts): array
    {
        $lokal = count(array_filter($rows, fn (array $r): bool => $r['tingkat_lokal'] === '✓'));
        $nasional = count(array_filter($rows, fn (array $r): bool => $r['tingkat_nasional'] === '✓'));
        $internasional = count(array_filter($rows, fn (array $r): bool => $r['tingkat_internasional'] === '✓'));

        return [
            ['label' => 'Total prestasi (TS-2..TS)', 'value' => count($rows)],
            ['label' => 'Internasional', 'value' => $internasional],
            ['label' => 'Nasional', 'value' => $nasional],
            ['label' => 'Lokal/Wilayah', 'value' => $lokal],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:prestasi:years:v1', $this->cacheTtl(), function (): array {
            return DB::table('list_prestasi_mahasiswa')
                ->whereNotNull('tahun_prestasi')
                ->where('tahun_prestasi', '<>', '')
                ->distinct()
                ->pluck('tahun_prestasi')
                ->map(fn (mixed $year): int => (int) $year)
                ->filter(fn (int $year): bool => $year >= 2000 && $year <= 2100)
                ->sortDesc()
                ->values()
                ->all();
        });
    }

    private function latestYear(): int
    {
        return $this->availableYears()[0] ?? (int) now()->year;
    }

    private function sourcePayload(): array
    {
        return [
            'name' => 'list_prestasi_mahasiswa & list_mahasiswa (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}
