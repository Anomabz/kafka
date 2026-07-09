<?php

namespace App\Services;

use Throwable;

/**
 * Ringkasan lintas 5 tabel BAN-PT yang sudah punya data nyata (dari 46 total),
 * untuk landing page dashboard. Tiap kartu memanggil repository tabel yang
 * sudah ada (satu sumber kebenaran per domain, tidak menduplikasi hitungan)
 * dan membungkusnya jadi {id, table_code, title, metrics, trend, notice,
 * error}. Kegagalan satu domain (exception repository) tidak menjatuhkan
 * kartu lain — kartu itu saja dapat error:true, metrics/trend kosong.
 *
 * Mahasiswa Asing beda dari 4 domain lain: tidak menerima $ts (backend-nya
 * hardcode TS-2/TS-1/TS 2023/2024/2025 — lihat SaptoMahasiswaAsingRepository).
 */
class SaptoDashboardRepository
{
    public function __construct(
        private readonly SaptoDosenTetapRepository $dosenTetap,
        private readonly SaptoDosenPembimbingTaRepository $dosenPembimbingTa,
        private readonly SaptoIpkLulusanRepository $ipkLulusan,
        private readonly SaptoMasaStudiRepository $masaStudi,
        private readonly SaptoMahasiswaAsingRepository $mahasiswaAsing,
    ) {}

    /**
     * @return array{ts: int|null, cards: array<int, array<string, mixed>>}
     */
    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        return [
            'ts' => $ts,
            'cards' => [
                $this->dosenTetapCard($ts, $fakultasId, $programStudiId),
                $this->dosenPembimbingTaCard($ts, $fakultasId, $programStudiId),
                $this->ipkLulusanCard($ts, $fakultasId, $programStudiId),
                $this->masaStudiCard($ts, $fakultasId, $programStudiId),
                $this->mahasiswaAsingCard($fakultasId, $programStudiId),
            ],
        ];
    }

    private function dosenTetapCard(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        return $this->safeCard('dosen-tetap-pt', '3.a.1', 'Dosen Tetap Perguruan Tinggi', function () use ($ts, $fakultasId, $programStudiId): array {
            $summary = $this->dosenTetap->summary($ts, $fakultasId, $programStudiId);
            $byLabel = collect($summary['metrics'])->pluck('value', 'label');

            return [
                'metrics' => [
                    ['label' => 'Total dosen tetap', 'value' => $byLabel['Total dosen tetap'] ?? 0],
                    ['label' => 'Laki-laki', 'value' => $byLabel['Laki-laki'] ?? 0],
                    ['label' => 'Perempuan', 'value' => $byLabel['Perempuan'] ?? 0],
                ],
                'trend' => null,
                'notice' => null,
            ];
        });
    }

    private function dosenPembimbingTaCard(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        return $this->safeCard('dosen-pembimbing-ta', '3.a.2', 'Dosen Pembimbing Utama Tugas Akhir', function () use ($ts, $fakultasId, $programStudiId): array {
            $summary = $this->dosenPembimbingTa->summary($ts, $fakultasId, $programStudiId);
            $totals = $summary['totals'];
            $years = $summary['year_columns'];

            return [
                'metrics' => [
                    ['label' => 'Total dosen pembimbing', 'value' => count($summary['rows'])],
                    ['label' => 'Mhs bimbingan PS diakreditasi (TS)', 'value' => $totals['ps_diakreditasi']['ts']],
                    ['label' => 'Mhs bimbingan PS lain di PT (TS)', 'value' => $totals['ps_lain_pt']['ts']],
                ],
                'trend' => [
                    'labels' => [(string) $years['ts_2'], (string) $years['ts_1'], (string) $years['ts']],
                    'values' => [
                        (float) $totals['ps_diakreditasi']['ts_2'],
                        (float) $totals['ps_diakreditasi']['ts_1'],
                        (float) $totals['ps_diakreditasi']['ts'],
                    ],
                ],
                'notice' => null,
            ];
        });
    }

    private function ipkLulusanCard(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        return $this->safeCard('ipk-lulusan', '8.a', 'IPK Lulusan', function () use ($ts, $fakultasId, $programStudiId): array {
            $summary = $this->ipkLulusan->summary($ts, $fakultasId, $programStudiId);
            $byLabel = collect($summary['metrics'])->pluck('value', 'label');
            $rows = collect($summary['rows']);

            return [
                'metrics' => [
                    ['label' => 'Total lulusan (TS-2..TS)', 'value' => $byLabel['Total lulusan (TS-2..TS)'] ?? 0],
                    ['label' => 'Jumlah lulusan (TS)', 'value' => $byLabel['Jumlah lulusan (TS)'] ?? 0],
                    ['label' => 'IPK rata-rata (TS)', 'value' => $byLabel['IPK rata-rata (TS)'] ?? 0],
                ],
                'trend' => [
                    'labels' => $rows->map(fn (array $row): string => explode(' ', (string) $row['tahun_lulus'])[0])->all(),
                    'values' => $rows->map(fn (array $row): float => $this->trendPoint($row['ipk_rata']))->all(),
                ],
                'notice' => null,
            ];
        });
    }

    private function masaStudiCard(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        return $this->safeCard('masa-studi', '8.c', 'Masa Studi Lulusan', function () use ($ts, $fakultasId, $programStudiId): array {
            $summary = $this->masaStudi->summary($ts, $fakultasId, $programStudiId);

            if ($summary['notice'] !== null) {
                return ['metrics' => [], 'trend' => null, 'notice' => $summary['notice']];
            }

            $byLabel = collect($summary['metrics'])->pluck('value', 'label');
            $recentRows = collect($summary['rows'])->slice(-3)->values();

            return [
                'metrics' => [
                    ['label' => 'Rata-rata masa studi (thn)', 'value' => $byLabel['Rata-rata masa studi (thn)'] ?? 0],
                    ['label' => 'Total lulusan (kohort)', 'value' => $byLabel['Total lulusan (kohort)'] ?? 0],
                    ['label' => 'Total diterima (kohort)', 'value' => $byLabel['Total diterima (kohort)'] ?? 0],
                ],
                'trend' => [
                    'labels' => $recentRows->map(fn (array $row): string => explode(' ', (string) $row['tahun_masuk'])[0])->all(),
                    'values' => $recentRows->map(fn (array $row): float => $this->trendPoint($row['masa_studi_rata']))->all(),
                ],
                'notice' => null,
            ];
        });
    }

    private function mahasiswaAsingCard(?string $fakultasId, ?string $programStudiId): array
    {
        return $this->safeCard('mahasiswa-asing', '2.b', 'Mahasiswa Asing', function () use ($fakultasId, $programStudiId): array {
            $summary = $this->mahasiswaAsing->summary($fakultasId, $programStudiId);
            $rows = $summary['data'];

            $ft = array_sum(array_map(fn (object $row): int => (int) $row->asing_ft_ts, $rows));
            $ft1 = array_sum(array_map(fn (object $row): int => (int) $row->asing_ft_ts1, $rows));
            $ft2 = array_sum(array_map(fn (object $row): int => (int) $row->asing_ft_ts2, $rows));
            $pt = array_sum(array_map(fn (object $row): int => (int) $row->asing_pt_ts, $rows));

            return [
                'metrics' => [
                    ['label' => 'Total program studi', 'value' => count($rows)],
                    ['label' => 'Mahasiswa asing aktif (TS)', 'value' => $ft + $pt],
                    ['label' => 'Penuh waktu (TS)', 'value' => $ft],
                ],
                'trend' => [
                    'labels' => [
                        $summary['tahun_akademik']['ts_2'],
                        $summary['tahun_akademik']['ts_1'],
                        $summary['tahun_akademik']['ts'],
                    ],
                    'values' => [(float) $ft2, (float) $ft1, (float) $ft],
                ],
                'notice' => null,
            ];
        });
    }

    /**
     * @param  callable(): array{metrics: array, trend: ?array, notice: ?string}  $resolve
     */
    private function safeCard(string $id, string $tableCode, string $title, callable $resolve): array
    {
        $base = ['id' => $id, 'table_code' => $tableCode, 'title' => $title, 'error' => false];

        try {
            return array_merge($base, $resolve());
        } catch (Throwable $e) {
            report($e);

            return array_merge($base, ['metrics' => [], 'trend' => null, 'notice' => null, 'error' => true]);
        }
    }

    private function trendPoint(int|float|null $value): float
    {
        return $value ?? 0.0;
    }
}
