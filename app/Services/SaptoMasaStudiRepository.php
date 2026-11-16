<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 8.c) Masa Studi Lulusan (format BAN-PT). Tabel ini diisi per program
 * studi, jadi hanya ditampilkan bila satu program studi dipilih. Untuk tiap
 * angkatan (tahun masuk) ditampilkan: jumlah mahasiswa diterima, jumlah lulus
 * pada akhir tiap TS, total lulusan s.d. TS, dan rata-rata masa studi.
 *
 * Sumber: satu tabel, list_mahasiswa (roster lengkap -- aktif, lulus, DO
 * sekaligus), bukan gabungan list_riwayat_pendidikan_mahasiswa +
 * list_mahasiswa_lulus_do seperti sebelumnya. Alasan ganti (diverifikasi
 * lewat query manual sebelum implementasi):
 * - "Diterima" dari list_riwayat_pendidikan_mahasiswa (jenis daftar "Peserta
 *   didik baru" saja) melewatkan mahasiswa pindahan/jalur lain -- di data
 *   nyata, undercount ~1-9 mahasiswa/tahun pada kohort terbaru dibanding
 *   hitung langsung dari id_periode di list_mahasiswa.
 * - Rata-rata masa studi berbasis tanggal (tgl_masuk_sp -> tgl_sk_yudisium)
 *   diam-diam membuang baris dengan tanggal kosong/tidak valid (~5% pada satu
 *   angkatan yang dicek). Dihitung dari selisih id_periode (indeks
 *   semester/2) malah mencakup semua lulusan yang id_periode-nya valid, jauh
 *   lebih jarang kosong daripada kolom tanggal.
 * - Angka "Lulus" per tahun sendiri sudah cocok persis antara kedua sumber
 *   (list_mahasiswa vs list_mahasiswa_lulus_do) untuk kohort yang dicek --
 *   bukan itu yang salah, cuma sumbernya disatukan.
 *
 * id_periode_keluar kosong pada ~18% baris "Lulus" (sama seperti gap yang
 * didokumentasikan di ComputesGraduationYear untuk list_mahasiswa_lulus_do,
 * kebanyakan lulusan lama) -- tahun lulus fallback ke tanggal_keluar bila
 * id_periode_keluar tidak bisa dipakai, supaya baris itu tetap terhitung di
 * kolom "Lulus Akhir TS-x"; hanya rata-rata masa studi (butuh presisi
 * semester) yang melewati baris itu bila fallback-nya juga gagal.
 *
 * Jumlah kolom "lulus pada akhir TS-x" dan angkatan yang ditampilkan mengikuti
 * pola jenjang (diploma tiga / sarjana / magister / doktor).
 */
class SaptoMasaStudiRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();

        // Tabel 8.c bersifat per-program-studi (mengikuti pola jenjang). Bila
        // belum ada prodi terpilih, minta pengguna memilih satu prodi.
        if ($programStudi === null) {
            return $this->noticeEnvelope($ts, $fakultas, $programStudi);
        }

        [$cohortsBack, $gradColsBack] = $this->jenjangConfig((string) ($programStudi['jenjang'] ?? ''));

        $gradYears = array_map(fn (int $g): int => $ts - $g, $gradColsBack);
        $cohortYears = array_map(fn (int $b): int => $ts - $b, $cohortsBack);

        $data = $this->cohortData((string) $programStudi['id'], $cohortYears, $ts);

        $columns = [
            ['key' => 'no', 'label' => 'No', 'align' => 'left'],
            ['key' => 'tahun_masuk', 'label' => 'Tahun Masuk', 'align' => 'left'],
            ['key' => 'diterima', 'label' => 'Jumlah Diterima', 'align' => 'num'],
        ];
        foreach ($gradColsBack as $g) {
            $columns[] = [
                'key' => 'g_'.$g,
                'label' => $g === 0 ? 'Lulus Akhir TS' : 'Lulus Akhir TS-'.$g,
                'align' => 'num',
            ];
        }
        $columns[] = ['key' => 'lulus_sd_ts', 'label' => 'Jumlah Lulusan s.d. TS', 'align' => 'num'];
        $columns[] = ['key' => 'masa_studi_rata', 'label' => 'Rata-rata Masa Studi (tahun)', 'align' => 'num'];

        $rows = collect($cohortsBack)
            ->map(function (int $back, int $index) use ($ts, $gradYears, $data): array {
                $tahunMasuk = $ts - $back;
                $cohort = $data['cohorts'][$tahunMasuk] ?? ['grad' => [], 'masa_total' => 0.0, 'masa_n' => 0];

                $row = [
                    'no' => $index + 1,
                    'tahun_masuk' => $tahunMasuk.' (TS-'.$back.')',
                    'tahun_masuk_tahun' => $tahunMasuk,
                    'diterima' => (int) ($data['diterima'][$tahunMasuk] ?? 0),
                ];

                $lulusSdTs = 0;
                foreach ($gradYears as $j => $gradYear) {
                    $g = $ts - $gradYear;
                    // Lulus hanya mungkin setelah masuk; sel sebelum tahun masuk 0.
                    $count = $gradYear >= $tahunMasuk ? (int) ($cohort['grad'][$gradYear] ?? 0) : 0;
                    $row['g_'.$g] = $count;
                    $row['tahun_lulus_g_'.$g] = $gradYear;
                    $lulusSdTs += $count;
                }

                $row['lulus_sd_ts'] = $lulusSdTs;
                $row['tahun_lulus_sd_ts'] = $ts;
                $row['masa_studi_rata'] = $cohort['masa_n'] > 0
                    ? round($cohort['masa_total'] / $cohort['masa_n'], 2)
                    : null;

                return $row;
            })
            ->values();

        return [
            'table_code' => '8.c',
            'table_name' => 'Masa Studi Lulusan',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => $columns,
            'rows' => $rows,
            'metrics' => $this->metrics($rows->all(), $ts),
            'notice' => null,
            'source' => $this->sourcePayload(),
        ];
    }

    /** Detail mahasiswa pada angka jumlah di Tabel 8.c. */
    public function detail(int $ts, int $angkatan, ?int $tahunLulus, string $jenis, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi] = $this->validateScope($programStudis, $fakultasId, $programStudiId);

        if ($programStudi === null) {
            return [];
        }

        [$cohortsBack, $gradColsBack] = $this->jenjangConfig((string) ($programStudi['jenjang'] ?? ''));
        $cohortYears = array_map(fn (int $back): int => $ts - $back, $cohortsBack);
        $gradYears = array_map(fn (int $back): int => $ts - $back, $gradColsBack);

        if (! in_array($angkatan, $cohortYears, true)
            || ! in_array($jenis, ['diterima', 'lulus'], true)
            || ($tahunLulus !== null && ! in_array($tahunLulus, $gradYears, true))
            || ($jenis === 'diterima' && $tahunLulus !== null)) {
            return [];
        }

        $query = DB::table('list_mahasiswa')
            ->select(['nim', 'nama_mahasiswa as nama', 'nama_program_studi as program_studi', 'id_periode', 'id_periode_keluar', 'tanggal_keluar', 'ipk', 'nama_status_mahasiswa'])
            ->where('id_prodi', (string) $programStudi['id'])
            ->whereRaw('substr(id_periode,1,4) = ?', [(string) $angkatan]);

        if ($jenis === 'lulus') {
            $query->where('nama_status_mahasiswa', 'Lulus');
        }

        $items = [];
        foreach ($query->orderBy('nama_mahasiswa')->cursor() as $row) {
            $gradYear = null;
            $gradPeriod = (string) ($row->id_periode_keluar ?? '');
            $gradIndex = $this->periodeIndex($gradPeriod);
            $gradYear = $gradIndex !== null ? (int) substr($gradPeriod, 0, 4) : null;

            if ($jenis === 'lulus') {
                if ($gradYear === null) {
                    $date = $this->parseDate((string) ($row->tanggal_keluar ?? ''));
                    $gradYear = $date?->format('Y') !== null ? (int) $date->format('Y') : null;
                }

                if ($gradYear === null
                    || $gradYear < $angkatan
                    || ! in_array($gradYear, $gradYears, true)
                    || ($tahunLulus !== null && $gradYear !== $tahunLulus)) {
                    continue;
                }
            }

            $items[] = [
                'nim' => $row->nim,
                'nama' => $row->nama,
                'program_studi' => $row->program_studi,
                'angkatan' => $angkatan,
                'tahun_lulus' => $gradYear,
                'ipk' => $row->ipk,
                'status' => $row->nama_status_mahasiswa,
            ];
        }

        return $items;
    }

    /**
     * Ambil data lulusan per angkatan (grad per tahun + masa studi) dan jumlah
     * diterima per angkatan untuk satu prodi -- satu pass atas list_mahasiswa,
     * satu baris per mahasiswa (lihat docblock kelas untuk alasan pindah
     * sumber).
     *
     * @param  array<int, int>  $cohortYears
     * @return array{cohorts: array<int, array{grad: array<int, int>, masa_total: float, masa_n: int}>, diterima: array<int, int>}
     */
    private function cohortData(string $prodiId, array $cohortYears, int $ts): array
    {
        $cacheKey = 'sapto:masa-studi:cohort:v2:'.md5(json_encode([$prodiId, $cohortYears, $ts]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($prodiId, $cohortYears): array {
            $cohortStrings = array_map(fn (int $y): string => (string) $y, $cohortYears);

            // substr, bukan LEFT()/REGEXP -- portabel MySQL & SQLite (sama
            // seperti ComputesGraduationYear). id_periode yang terlalu pendek
            // untuk dicocokkan (~0.08% baris di data nyata) otomatis tidak
            // masuk IN karena substr-nya tidak akan match salah satu tahun kohort.
            $students = DB::table('list_mahasiswa')
                ->select('id_periode', 'id_periode_keluar', 'tanggal_keluar', 'nama_status_mahasiswa')
                ->where('id_prodi', $prodiId)
                ->whereIn(DB::raw('substr(id_periode,1,4)'), $cohortStrings)
                ->cursor();

            $cohorts = [];
            $diterima = [];

            foreach ($students as $row) {
                $periodeMasuk = (string) $row->id_periode;
                $angkatan = (int) substr($periodeMasuk, 0, 4);

                $diterima[$angkatan] = ($diterima[$angkatan] ?? 0) + 1;

                if ((string) $row->nama_status_mahasiswa !== 'Lulus') {
                    continue;
                }

                $periodeKeluar = (string) ($row->id_periode_keluar ?? '');
                $idxLulus = $this->periodeIndex($periodeKeluar);
                $gradYear = $idxLulus !== null ? (int) substr($periodeKeluar, 0, 4) : null;

                // id_periode_keluar kosong/tidak valid pada ~18% baris Lulus
                // (kebanyakan lulusan lama) -- pulihkan tahun dari tanggal_keluar
                // supaya baris ini tetap terhitung di "Lulus Akhir TS-x", sama
                // seperti fallback di ComputesGraduationYear untuk tabel lain.
                if ($gradYear === null) {
                    $date = $this->parseDate((string) ($row->tanggal_keluar ?? ''));
                    $gradYear = $date?->format('Y') !== null ? (int) $date->format('Y') : null;
                }

                if ($gradYear === null || $gradYear < 2000) {
                    continue;
                }

                $cohorts[$angkatan] ??= ['grad' => [], 'masa_total' => 0.0, 'masa_n' => 0];
                $cohorts[$angkatan]['grad'][$gradYear] = ($cohorts[$angkatan]['grad'][$gradYear] ?? 0) + 1;

                // Rata-rata masa studi butuh presisi semester (idx masuk & lulus
                // dua-duanya valid) -- baris yang cuma lolos lewat fallback
                // tanggal_keluar tetap terhitung sebagai lulusan, tapi dilewati
                // di sini karena idx_lulus-nya tidak ada.
                $idxMasuk = $this->periodeIndex($periodeMasuk);
                if ($idxMasuk !== null && $idxLulus !== null) {
                    $cohorts[$angkatan]['masa_total'] += ($idxLulus - $idxMasuk + 1) / 2.0;
                    $cohorts[$angkatan]['masa_n']++;
                }
            }

            return ['cohorts' => $cohorts, 'diterima' => $diterima];
        });
    }

    /**
     * Indeks semester dari id_periode berformat YYYYS (S: 1=ganjil, 2=genap),
     * mis. "20221" -> 2022*2+0 = 4044, "20222" -> 2022*2+1 = 4045 -- selisih
     * dua indeks ini, dibagi 2, memberi lama studi dalam tahun dengan presisi
     * semester tanpa perlu tanggal kalender.
     */
    private function periodeIndex(string $periode): ?int
    {
        if (preg_match('/^([0-9]{4})([0-9])$/', $periode, $m) !== 1) {
            return null;
        }

        return ((int) $m[1]) * 2 + ((int) $m[2] === 2 ? 1 : 0);
    }

    /**
     * @return array{0: array<int,int>, 1: array<int,int>}  [cohortsBack, gradColsBack]
     */
    private function jenjangConfig(string $jenjang): array
    {
        $j = strtoupper($jenjang);

        if (str_contains($j, 'D3') || str_contains($j, 'D-3') || str_contains($j, 'DIPLOMA TIGA') || str_contains($j, 'DIPLOMA 3')) {
            return [[4, 3, 2], [4, 3, 2, 1, 0]];
        }

        if (str_contains($j, 'S2') || str_contains($j, 'MAGISTER')) {
            return [[3, 2, 1], [3, 2, 1, 0]];
        }

        if (str_contains($j, 'S3') || str_contains($j, 'DOKTOR')) {
            return [[6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 1, 0]];
        }

        // S1, D4/Sarjana Terapan, Profesi, dan lainnya -> pola sarjana.
        return [[6, 5, 4, 3], [6, 5, 4, 3, 2, 1, 0]];
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach (['d-m-Y', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date !== false) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, int $ts): array
    {
        $diterima = array_sum(array_map(fn (array $r): int => (int) $r['diterima'], $rows));
        $lulus = array_sum(array_map(fn (array $r): int => (int) $r['lulus_sd_ts'], $rows));

        $sum = 0.0;
        $n = 0;
        foreach ($rows as $r) {
            if ($r['masa_studi_rata'] !== null) {
                $sum += (float) $r['masa_studi_rata'] * (int) $r['lulus_sd_ts'];
                $n += (int) $r['lulus_sd_ts'];
            }
        }

        return [
            ['label' => 'Total diterima (kohort)', 'value' => $diterima],
            ['label' => 'Total lulusan (kohort)', 'value' => $lulus],
            ['label' => 'Rata-rata masa studi (thn)', 'value' => $n > 0 ? round($sum / $n, 2) : 0],
            ['label' => 'TS', 'value' => $ts],
        ];
    }

    /**
     * Envelope saat belum ada prodi terpilih: tabel kosong + pesan pengarah.
     */
    private function noticeEnvelope(int $ts, ?array $fakultas, ?array $programStudi): array
    {
        return [
            'table_code' => '8.c',
            'table_name' => 'Masa Studi Lulusan',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [],
            'rows' => [],
            'metrics' => [],
            'notice' => 'Tabel 8.c disusun per program studi (mengikuti pola jenjang). Pilih satu program studi pada filter di atas untuk menampilkan matriks masa studi per angkatan.',
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:masa-studi:years:v2', $this->cacheTtl(), function (): array {
            $rows = DB::select("
                select distinct substr(id_periode_keluar,1,4) as thn
                from list_mahasiswa
                where nama_status_mahasiswa = 'Lulus'
                  and id_periode_keluar is not null
                  and id_periode_keluar <> ''
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
            'name' => 'list_mahasiswa (sinkronisasi lokal)',
            'partial' => false,
        ];
    }
}
