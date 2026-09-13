<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 5.a) Kurikulum, Capaian Pembelajaran, dan Rencana Pembelajaran
 * (format BAN-PT). Satu kurikulum dimiliki satu program studi, jadi tabel ini
 * hanya tampil bila satu prodi dipilih -- sama seperti Tabel 8.c.
 *
 * Sumber: matkul_kurikulum (daftar mata kuliah per kurikulum, beserta sks dan
 * semester), list_kurikulum (versi kurikulum + kapan mulai berlaku), dan
 * rencana_pembelajaran (RPS per pertemuan) untuk menandai mata kuliah yang
 * sudah punya dokumen rencana pembelajaran.
 *
 * Versi kurikulum yang ditampilkan: yang paling baru mulai berlaku PALING
 * LAMBAT pada tahun akademik TS. id_semester berformat YYYYS (20251 = ganjil
 * 2025/2026, 20252 = genap), jadi batasnya TS*10+2. Prodi memang punya
 * beberapa versi kurikulum (503 versi untuk 136 prodi di data nyata) dan
 * dokumen akreditasi hanya menanyakan yang berlaku pada TS.
 *
 * Tiga kelompok kolom LKPS SENGAJA dikosongkan karena tidak ada sumbernya di
 * PD UNSRI, dan angka akreditasi tidak boleh dikarang:
 * - "Konversi Kredit ke Jam": tidak ada field jam di feeder.
 * - Capaian Pembelajaran (Sikap/Pengetahuan/Keterampilan Umum/Khusus):
 *   pemetaan CPL per mata kuliah tidak dikirim feeder.
 * - "Seminar" pada bobot kredit: feeder hanya punya sks tatap muka, praktek,
 *   praktek lapangan, dan simulasi -- tidak ada kolom seminar. Simulasi tidak
 *   disamakan dengan seminar karena artinya berbeda.
 *
 * "Mata Kuliah Kompetensi" diturunkan dari apakah_wajib (mata kuliah wajib
 * prodi); itu pendekatan terdekat yang tersedia, bukan field kompetensi
 * tersendiri -- disebutkan di notice supaya tidak dikira data resmi.
 */
class SaptoKurikulumRepository
{
    use ResolvesSaptoScope;

    private const TANDA = '✓';

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
        private readonly SaptoTsResolver $tsResolver,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->tsResolver->ts();

        if ($programStudi === null) {
            return $this->envelope($ts, $fakultas, $programStudi, [], [],
                'Tabel 5.a disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan kurikulum yang berlaku pada TS.');
        }

        $kurikulum = $this->kurikulumBerlaku((string) $programStudi['id'], $ts);

        if ($kurikulum === null) {
            return $this->envelope($ts, $fakultas, $programStudi, [], [],
                'Belum ada kurikulum yang tercatat mulai berlaku sampai tahun akademik '.$ts.' untuk program studi ini.');
        }

        $rows = $this->mataKuliah((string) $kurikulum['id_kurikulum']);

        return $this->envelope($ts, $fakultas, $programStudi, $this->columns(), $rows, $this->notice($kurikulum), $kurikulum);
    }

    /**
     * Versi kurikulum terbaru yang sudah berlaku pada tahun akademik TS.
     * Dikembalikan sebagai array, bukan objek hasil query: cache file
     * menyerialisasi isinya dan stdClass hasil unserialize bisa datang
     * kembali sebagai incomplete object.
     *
     * @return array<string, mixed>|null
     */
    private function kurikulumBerlaku(string $prodiId, int $ts): ?array
    {
        return Cache::remember(
            'sapto:kurikulum:versi:v2:'.md5($prodiId.'|'.$ts),
            $this->cacheTtl(),
            function () use ($prodiId, $ts): ?array {
                $row = DB::table('list_kurikulum')
                    ->select('id_kurikulum', 'nama_kurikulum', 'id_semester', 'semester_mulai_berlaku', 'jumlah_sks_lulus')
                    ->where('id_prodi', $prodiId)
                    ->whereRaw('cast(id_semester as unsigned) <= ?', [$ts * 10 + 2])
                    ->orderByRaw('cast(id_semester as unsigned) desc')
                    ->first();

                return $row === null ? null : (array) $row;
            },
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function mataKuliah(string $kurikulumId): array
    {
        return Cache::remember(
            'sapto:kurikulum:matkul:v1:'.md5($kurikulumId),
            $this->cacheTtl(),
            function () use ($kurikulumId): array {
                // RPS dihitung sekali sebagai subquery agregat; menghitung per
                // baris (correlated) memaksa scan rencana_pembelajaran 93rb
                // baris berulang kali untuk tiap mata kuliah.
                $rps = DB::table('rencana_pembelajaran')
                    ->selectRaw('id_matkul, count(*) as pertemuan')
                    ->groupBy('id_matkul');

                $rows = DB::table('matkul_kurikulum as mk')
                    ->leftJoinSub($rps, 'rps', fn ($join) => $join->on('rps.id_matkul', '=', 'mk.id_matkul'))
                    ->where('mk.id_kurikulum', $kurikulumId)
                    ->orderByRaw('cast(mk.semester as unsigned)')
                    ->orderBy('mk.kode_mata_kuliah')
                    ->get([
                        'mk.semester', 'mk.kode_mata_kuliah', 'mk.nama_mata_kuliah', 'mk.nama_program_studi',
                        'mk.sks_mata_kuliah', 'mk.sks_tatap_muka', 'mk.sks_praktek', 'mk.sks_praktek_lapangan',
                        'mk.apakah_wajib', 'rps.pertemuan',
                    ]);

                return $rows->values()->map(function (object $row, int $i): array {
                    $praktikum = $this->angka($row->sks_praktek) + $this->angka($row->sks_praktek_lapangan);

                    return [
                        'no' => $i + 1,
                        'semester' => (string) $row->semester,
                        'kode_mata_kuliah' => (string) $row->kode_mata_kuliah,
                        'nama_mata_kuliah' => (string) $row->nama_mata_kuliah,
                        'mk_kompetensi' => (string) $row->apakah_wajib === '1' ? self::TANDA : '',
                        'sks_kuliah' => $this->angka($row->sks_tatap_muka),
                        'sks_seminar' => null,
                        'sks_praktikum' => $praktikum,
                        'sks_total' => $this->angka($row->sks_mata_kuliah),
                        'konversi_jam' => null,
                        'cpl_sikap' => null,
                        'cpl_pengetahuan' => null,
                        'cpl_ket_umum' => null,
                        'cpl_ket_khusus' => null,
                        'dok_rps' => (int) ($row->pertemuan ?? 0) > 0 ? self::TANDA : '',
                        'unit_penyelenggara' => (string) $row->nama_program_studi,
                    ];
                })->all();
            },
        );
    }

    /** @return array<int, array<string, string>> */
    private function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'align' => 'left'],
            ['key' => 'semester', 'label' => 'Semester', 'align' => 'left'],
            ['key' => 'kode_mata_kuliah', 'label' => 'Kode Mata Kuliah', 'align' => 'left'],
            ['key' => 'nama_mata_kuliah', 'label' => 'Nama Mata Kuliah', 'align' => 'left'],
            ['key' => 'mk_kompetensi', 'label' => 'Mata Kuliah Kompetensi', 'align' => 'left'],
            ['key' => 'sks_kuliah', 'label' => 'sks Kuliah/Responsi/Tutorial', 'align' => 'num'],
            ['key' => 'sks_seminar', 'label' => 'sks Seminar', 'align' => 'num'],
            ['key' => 'sks_praktikum', 'label' => 'sks Praktikum/Praktik/Praktik Lapangan', 'align' => 'num'],
            ['key' => 'sks_total', 'label' => 'Bobot Kredit (sks)', 'align' => 'num'],
            ['key' => 'konversi_jam', 'label' => 'Konversi Kredit ke Jam', 'align' => 'num'],
            ['key' => 'cpl_sikap', 'label' => 'CPL Sikap', 'align' => 'left'],
            ['key' => 'cpl_pengetahuan', 'label' => 'CPL Pengetahuan', 'align' => 'left'],
            ['key' => 'cpl_ket_umum', 'label' => 'CPL Keterampilan Umum', 'align' => 'left'],
            ['key' => 'cpl_ket_khusus', 'label' => 'CPL Keterampilan Khusus', 'align' => 'left'],
            ['key' => 'dok_rps', 'label' => 'Dokumen Rencana Pembelajaran', 'align' => 'left'],
            ['key' => 'unit_penyelenggara', 'label' => 'Unit Penyelenggara', 'align' => 'left'],
        ];
    }

    /** @param array<string, mixed> $kurikulum */
    private function notice(array $kurikulum): string
    {
        return sprintf(
            'Kurikulum yang ditampilkan: %s (mulai berlaku %s). Kolom Konversi Kredit ke Jam, keempat kolom '
            .'Capaian Pembelajaran, dan sks Seminar dikosongkan karena tidak ada sumbernya di PD UNSRI. '
            .'Kolom Mata Kuliah Kompetensi diturunkan dari status mata kuliah wajib. Dokumen Rencana '
            .'Pembelajaran ditandai bila RPS mata kuliah itu sudah masuk ke PD UNSRI.',
            $kurikulum['nama_kurikulum'],
            $kurikulum['semester_mulai_berlaku'],
        );
    }

    /**
     * @param  array<int, array<string, string>>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function envelope(int $ts, ?array $fakultas, ?array $programStudi, array $columns, array $rows, string $notice, ?array $kurikulum = null): array
    {
        return [
            'table_code' => '5.a',
            'table_name' => 'Kurikulum, Capaian Pembelajaran, dan Rencana Pembelajaran',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => $columns,
            'rows' => $rows,
            'metrics' => $this->metrics($rows, $kurikulum),
            'notice' => $notice,
            'source' => [
                'name' => 'matkul_kurikulum + list_kurikulum + rencana_pembelajaran (sinkronisasi lokal)',
                'partial' => true,
            ],
        ];
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function metrics(array $rows, ?array $kurikulum): array
    {
        if ($rows === []) {
            return [];
        }

        return [
            ['label' => 'Jumlah mata kuliah', 'value' => count($rows)],
            ['label' => 'Total sks kurikulum', 'value' => round(array_sum(array_column($rows, 'sks_total')), 2)],
            ['label' => 'sks lulus (kurikulum)', 'value' => $kurikulum ? (float) $kurikulum['jumlah_sks_lulus'] : null],
            ['label' => 'Mata kuliah ber-RPS', 'value' => count(array_filter($rows, fn (array $r): bool => $r['dok_rps'] !== ''))],
        ];
    }

    /** @return array<int, int> */
    public function availableYears(): array
    {
        return Cache::remember('sapto:kurikulum:years:v1', $this->cacheTtl(), function (): array {
            $years = DB::table('list_kurikulum')
                ->selectRaw('distinct substr(id_semester,1,4) as thn')
                ->whereRaw("id_semester <> ''")
                ->pluck('thn')
                ->map(fn (mixed $t): int => (int) $t)
                ->filter(fn (int $t): bool => $t >= 2000 && $t <= (int) date('Y') + 1)
                ->sortDesc()
                ->values()
                ->all();

            return $years;
        });
    }

    private function angka(mixed $nilai): float
    {
        return round((float) str_replace(',', '.', (string) $nilai), 2);
    }
}
