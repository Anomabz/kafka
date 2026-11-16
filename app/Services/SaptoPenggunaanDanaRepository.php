<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;

/**
 * Tabel 4) Penggunaan Dana.
 *
 * Institution-wide: data tidak perlu discope fakultas/prodi. Matriks penggunaan
 * dana per jenis dengan breakdown TS-2/TS-1/TS dan rata-rata untuk Unit Pengelola
 * PS dan Program Studi.
 *
 * Struktur mengikuti format BAN-PT Tabel 4 dengan 7 kategori utama dan
 * sub-item untuk Biaya Operasional Pendidikan (a-d). Baris "Jumlah" muncul
 * setelah item utama.
 *
 * Data keuangan belum tersedia di database. Repository return kolom +
 * struktur placeholder. Data real diisi setelah tabel keuangan tersedia.
 */
class SaptoPenggunaanDanaRepository
{
    use ResolvesSaptoTs;

    private const GROUP_UNIT = 'Unit Pengelola Program Studi (Rupiah)';
    private const GROUP_PS = 'Program Studi (Rupiah)';

    private const KATEGORI = [
        // parent_key, label, is_jumlah (baris subtotal setelah ini)
        ['key' => '1a', 'parent' => '1', 'label' => 'a. Biaya Dosen (Gaji, Honor)', 'jumlah' => false],
        ['key' => '1b', 'parent' => '1', 'label' => 'b. Biaya Tenaga Kependidikan (Gaji, Honor)', 'jumlah' => false],
        ['key' => '1c', 'parent' => '1', 'label' => 'c. Biaya Operasional Pembelajaran (Bahan, Daya, jasa)', 'jumlah' => false],
        ['key' => '1d', 'parent' => '1', 'label' => 'd. Biaya Operasional Tidak Langsung', 'jumlah' => false],
        ['key' => '1', 'parent' => null, 'label' => '1. Biaya Operasional Pendidikan', 'jumlah' => true, 'sub' => ['1a', '1b', '1c', '1d']],
        ['key' => '2', 'parent' => null, 'label' => '2. Biaya Operasional Kemahasiswaan', 'jumlah' => true, 'sub' => null],
        ['key' => '3', 'parent' => null, 'label' => '3. Biaya Penelitian', 'jumlah' => false],
        ['key' => '4', 'parent' => null, 'label' => '4. Biaya PkM', 'jumlah' => true, 'sub' => null],
        ['key' => '5', 'parent' => null, 'label' => '5. Biaya Investasi SDM', 'jumlah' => false],
        ['key' => '6', 'parent' => null, 'label' => '6. Biaya Investasi Sarana', 'jumlah' => false],
        ['key' => '7', 'parent' => null, 'label' => '7. Biaya Investasi Prasarana', 'jumlah' => true, 'sub' => null],
    ];

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'jenis_penggunaan', 'label' => 'Jenis Penggunaan', 'align' => 'left'],
        ['key' => 'unit_ts2', 'label' => 'TS-2', 'align' => 'num', 'group' => self::GROUP_UNIT],
        ['key' => 'unit_ts1', 'label' => 'TS-1', 'align' => 'num', 'group' => self::GROUP_UNIT],
        ['key' => 'unit_ts', 'label' => 'TS', 'align' => 'num', 'group' => self::GROUP_UNIT],
        ['key' => 'unit_rata', 'label' => 'Rata-rata', 'align' => 'num', 'group' => self::GROUP_UNIT],
        ['key' => 'ps_ts2', 'label' => 'TS-2', 'align' => 'num', 'group' => self::GROUP_PS],
        ['key' => 'ps_ts1', 'label' => 'TS-1', 'align' => 'num', 'group' => self::GROUP_PS],
        ['key' => 'ps_ts', 'label' => 'TS', 'align' => 'num', 'group' => self::GROUP_PS],
        ['key' => 'ps_rata', 'label' => 'Rata-rata', 'align' => 'num', 'group' => self::GROUP_PS],
    ];

    public function summary(?int $ts): array
    {
        $ts ??= $this->ts();

        $rows = $this->buildRows();

        return [
            'table_code' => '4',
            'table_name' => 'Penggunaan Dana',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => null,
            'program_studi' => null,
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'metrics' => $this->metrics($ts),
            'notice' => 'Data penggunaan dana belum tersedia di database. Kolom Unit Pengelola PS dan Program Studi perlu diisi dari laporan keuangan perguruan tinggi setelah tabel sumber tersedia.',
            'source' => ['name' => 'Data keuangan PT (belum tersedia)', 'partial' => true, 'template' => true],
        ];
    }

    /**
     * @return array<int, array{no: int|null, jenis_penggunaan: string, unit_ts2: int, unit_ts1: int, unit_ts: int, unit_rata: int, ps_ts2: int, ps_ts1: int, ps_ts: int, ps_rata: int, is_parent: bool, is_sub: bool, is_jumlah: bool}>
     */
    private function buildRows(): array
    {
        $rows = [];
        $no = 1;

        foreach (self::KATEGORI as $kat) {
            $isSub = $kat['parent'] !== null;
            $isJumlah = $kat['jumlah'] ?? false;
            $isParent = ! $isSub;

            // Baris parent (nomor 1-7)
            if ($isParent) {
                $rows[] = [
                    'no' => $no++,
                    'jenis_penggunaan' => $kat['label'],
                    'unit_ts2' => 0, 'unit_ts1' => 0, 'unit_ts' => 0, 'unit_rata' => 0,
                    'ps_ts2' => 0, 'ps_ts1' => 0, 'ps_ts' => 0, 'ps_rata' => 0,
                    'is_parent' => true, 'is_sub' => false, 'is_jumlah' => $isJumlah,
                ];
            } else {
                // Baris sub (a, b, c, d)
                $rows[] = [
                    'no' => null,
                    'jenis_penggunaan' => $kat['label'],
                    'unit_ts2' => 0, 'unit_ts1' => 0, 'unit_ts' => 0, 'unit_rata' => 0,
                    'ps_ts2' => 0, 'ps_ts1' => 0, 'ps_ts' => 0, 'ps_rata' => 0,
                    'is_parent' => false, 'is_sub' => true, 'is_jumlah' => false,
                ];
            }

            // Baris Jumlah setelah kategori yang punya sub
            if ($isJumlah) {
                $rows[] = [
                    'no' => null,
                    'jenis_penggunaan' => 'Jumlah',
                    'unit_ts2' => 0, 'unit_ts1' => 0, 'unit_ts' => 0, 'unit_rata' => 0,
                    'ps_ts2' => 0, 'ps_ts1' => 0, 'ps_ts' => 0, 'ps_rata' => 0,
                    'is_parent' => false, 'is_sub' => false, 'is_jumlah' => true,
                ];
            }
        }

        // Baris Grand Total
        $rows[] = [
            'no' => null,
            'jenis_penggunaan' => 'Total',
            'unit_ts2' => 0, 'unit_ts1' => 0, 'unit_ts' => 0, 'unit_rata' => 0,
            'ps_ts2' => 0, 'ps_ts1' => 0, 'ps_ts' => 0, 'ps_rata' => 0,
            'is_parent' => false, 'is_sub' => false, 'is_jumlah' => true,
        ];

        return $rows;
    }

    /**
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(int $ts): array
    {
        return [
            ['label' => 'Total jenis penggunaan', 'value' => count(array_filter(self::KATEGORI, fn (array $k): bool => $k['parent'] === null))],
            ['label' => 'Sub-item (a-d)', 'value' => count(array_filter(self::KATEGORI, fn (array $k): bool => $k['parent'] !== null))],
            ['label' => 'Ruang lingkup', 'value' => 'Universitas Sriwijaya'],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        return Cache::remember('sapto:penggunaan-dana:years:v1', $this->cacheTtl(), function (): array {
            $rows = \Illuminate\Support\Facades\DB::select("
                SELECT DISTINCT CAST(SUBSTR(nama_tahun_ajaran, 1, 4) AS UNSIGNED) as thn
                FROM list_penugasan_dosen
                WHERE nama_tahun_ajaran IS NOT NULL
                  AND nama_tahun_ajaran <> ''
                ORDER BY thn DESC
                LIMIT 10
            ");

            return array_values(array_filter(
                array_map(fn (object $row): int => (int) $row->thn, $rows),
                fn (int $year): bool => $year >= 2000 && $year <= 2100
            ));
        });
    }

    private function cacheTtl(): int
    {
        return max(1, (int) config('services.pangkalan_data.cache_ttl', 3600));
    }
}
