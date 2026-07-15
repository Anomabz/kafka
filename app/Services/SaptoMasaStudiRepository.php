<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 8.c) Masa Studi Lulusan (format BAN-PT). Tabel ini diisi per program
 * studi, jadi hanya ditampilkan bila satu program studi dipilih. Untuk tiap
 * angkatan (tahun masuk) ditampilkan: jumlah mahasiswa diterima, jumlah lulus
 * pada akhir tiap TS, total lulusan s.d. TS, dan rata-rata masa studi.
 *
 * Sumber:
 * - Lulusan & masa studi: list_mahasiswa_lulus_do (angkatan, tahun lulus dari
 *   id_periode_keluar, tanggal masuk->yudisium).
 * - Jumlah diterima: list_riwayat_pendidikan_mahasiswa (jenis daftar "Peserta
 *   didik baru", tahun masuk dari id_periode_masuk).
 *
 * Jumlah kolom "lulus pada akhir TS-x" dan angkatan yang ditampilkan mengikuti
 * pola jenjang (diploma tiga / sarjana / magister / doktor).
 */
class SaptoMasaStudiRepository
{
    use ResolvesSaptoScope;

    private const HARI_PER_TAHUN = 365.25;

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->latestYear();

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
                    'diterima' => (int) ($data['diterima'][$tahunMasuk] ?? 0),
                ];

                $lulusSdTs = 0;
                foreach ($gradYears as $j => $gradYear) {
                    $g = $ts - $gradYear;
                    // Lulus hanya mungkin setelah masuk; sel sebelum tahun masuk 0.
                    $count = $gradYear >= $tahunMasuk ? (int) ($cohort['grad'][$gradYear] ?? 0) : 0;
                    $row['g_'.$g] = $count;
                    $lulusSdTs += $count;
                }

                $row['lulus_sd_ts'] = $lulusSdTs;
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

    /**
     * Ambil data lulusan per angkatan (grad per tahun + masa studi) dan jumlah
     * diterima per angkatan untuk satu prodi.
     *
     * @param  array<int, int>  $cohortYears
     * @return array{cohorts: array<int, array{grad: array<int, int>, masa_total: float, masa_n: int}>, diterima: array<int, int>}
     */
    private function cohortData(string $prodiId, array $cohortYears, int $ts): array
    {
        $cacheKey = 'sapto:masa-studi:cohort:v1:'.md5(json_encode([$prodiId, $cohortYears, $ts]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($prodiId, $cohortYears): array {
            $cohortStrings = array_map(fn (int $y): string => (string) $y, $cohortYears);

            // Lulusan prodi ini yang angkatannya termasuk kohort yang dilacak.
            $graduates = DB::table('list_mahasiswa_lulus_do')
                ->select('angkatan', 'id_periode_keluar', 'tgl_masuk_sp', 'tgl_sk_yudisium', 'tanggal_keluar', 'tgl_keluar')
                ->where('nama_jenis_keluar', 'Lulus')
                ->where('id_prodi', $prodiId)
                ->whereIn('angkatan', $cohortStrings)
                ->cursor();

            $cohorts = [];
            foreach ($graduates as $row) {
                $angkatan = (int) $row->angkatan;
                $gradYear = $this->graduationYear($row);

                if ($gradYear === null || $gradYear < 2000) {
                    continue;
                }

                if (! isset($cohorts[$angkatan])) {
                    $cohorts[$angkatan] = ['grad' => [], 'masa_total' => 0.0, 'masa_n' => 0];
                }

                $cohorts[$angkatan]['grad'][$gradYear] = ($cohorts[$angkatan]['grad'][$gradYear] ?? 0) + 1;

                $years = $this->masaStudiTahun(
                    (string) $row->tgl_masuk_sp,
                    (string) ($row->tgl_sk_yudisium ?: ($row->tanggal_keluar ?: $row->tgl_keluar)),
                );
                if ($years !== null) {
                    $cohorts[$angkatan]['masa_total'] += $years;
                    $cohorts[$angkatan]['masa_n']++;
                }
            }

            // Jumlah mahasiswa baru (diterima) per tahun masuk.
            $diterimaRows = DB::table('list_riwayat_pendidikan_mahasiswa')
                ->selectRaw('substr(id_periode_masuk,1,4) as thn, count(*) as n')
                ->where('id_prodi', $prodiId)
                ->where('nama_jenis_daftar', 'Peserta didik baru')
                ->whereIn(DB::raw('substr(id_periode_masuk,1,4)'), $cohortStrings)
                ->groupBy(DB::raw('substr(id_periode_masuk,1,4)'))
                ->get();

            $diterima = [];
            foreach ($diterimaRows as $row) {
                $diterima[(int) $row->thn] = (int) $row->n;
            }

            return ['cohorts' => $cohorts, 'diterima' => $diterima];
        });
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

    /**
     * Tahun lulus sebuah baris lulusan. Utamakan id_periode_keluar (YYYYS);
     * bila kosong, pulihkan dari tanggal keluar/yudisium (DD-MM-YYYY atau
     * YYYY-MM-DD). Selaras dengan SaptoIpkLulusanRepository::graduationYearSql()
     * agar 8.a & 8.c menghitung lulusan yang sama.
     */
    private function graduationYear(object $row): ?int
    {
        $periode = (string) ($row->id_periode_keluar ?? '');

        if ($periode !== '') {
            return (int) substr($periode, 0, 4);
        }

        foreach ([$row->tanggal_keluar ?? null, $row->tgl_keluar ?? null, $row->tgl_sk_yudisium ?? null] as $tanggal) {
            $date = $this->parseDate((string) ($tanggal ?? ''));

            if ($date !== null) {
                return (int) $date->format('Y');
            }
        }

        return null;
    }

    private function masaStudiTahun(string $masuk, string $keluar): ?float
    {
        $start = $this->parseDate($masuk);
        $end = $this->parseDate($keluar);

        if ($start === null || $end === null) {
            return null;
        }

        $years = (($end->getTimestamp() - $start->getTimestamp()) / 86400) / self::HARI_PER_TAHUN;

        return ($years <= 0 || $years > 15) ? null : $years;
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
        return Cache::remember('sapto:masa-studi:years:v1', $this->cacheTtl(), function (): array {
            $rows = DB::select("
                select distinct substr(id_periode_keluar,1,4) as thn
                from list_mahasiswa_lulus_do
                where nama_jenis_keluar = 'Lulus'
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

    private function latestYear(): int
    {
        return $this->availableYears()[0] ?? (int) now()->year;
    }

    private function sourcePayload(): array
    {
        return [
            'name' => 'Pangkalan Data UNSRI (sinkronisasi lokal)',
            'partial' => false,
        ];
    }
}
