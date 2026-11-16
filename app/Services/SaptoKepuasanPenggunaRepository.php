<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;

/**
 * Tabel 8.e.2) Kepuasan Pengguna Lulusan.
 *
 * Per program studi: membutuhkan prodi terpilih (scope fakultas/prodi).
 * Mengukur kepuasan pengguna (mitra industri/institusi) terhadap kompetensi
 * lulusan berdasarkan 7 jenis kemampuan.
 *
 * Data tracer study alumni belum tersedia di database. Repository return
 * kolom + aspek placeholder. Data real diisi setelah instrumen tracer study
 * tersedia dan hasilnya diimpor.
 */
class SaptoKepuasanPenggunaRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    private const GROUP_KEPUASAN = 'Tingkat Kepuasan Pengguna (%)';

    private const KEMAMPUAN = [
        'Etika',
        'Keahlian pada bidang ilmu',
        'Kemampuan berbahasa asing',
        'Penggunaan teknologi informasi',
        'Kemampuan berkomunikasi',
        'Kerjasama tim',
        'Pengembangan diri',
    ];

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'jenis_kemampuan', 'label' => 'Jenis Kemampuan', 'align' => 'left'],
        ['key' => 'sangat_baik', 'label' => 'Sangat Baik', 'align' => 'num', 'group' => self::GROUP_KEPUASAN],
        ['key' => 'baik', 'label' => 'Baik', 'align' => 'num', 'group' => self::GROUP_KEPUASAN],
        ['key' => 'cukup', 'label' => 'Cukup', 'align' => 'num', 'group' => self::GROUP_KEPUASAN],
        ['key' => 'kurang', 'label' => 'Kurang', 'align' => 'num', 'group' => self::GROUP_KEPUASAN],
        ['key' => 'rencana_tindak_lanjut', 'label' => 'Rencana Tindak Lanjut oleh UPPS/PS', 'align' => 'left'],
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();

        if ($programStudi === null) {
            return $this->noticeEnvelope($ts, $fakultas, $programStudi,
                'Tabel 8.e.2 disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan hasil kepuasan pengguna.');
        }

        $rows = [];
        $index = 0;
        foreach (self::KEMAMPUAN as $kemampuan) {
            $rows[] = [
                'no' => ++$index,
                'jenis_kemampuan' => $kemampuan,
                'sangat_baik' => 0,
                'baik' => 0,
                'cukup' => 0,
                'kurang' => 0,
                'rencana_tindak_lanjut' => '',
            ];
        }

        // Baris Jumlah
        $rows[] = [
            'no' => null,
            'jenis_kemampuan' => 'Jumlah',
            'sangat_baik' => 0,
            'baik' => 0,
            'cukup' => 0,
            'kurang' => 0,
            'rencana_tindak_lanjut' => '',
        ];

        return [
            'table_code' => '8.e.2',
            'table_name' => 'Kepuasan Pengguna Lulusan',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas ?? null,
            'program_studi' => $programStudi,
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'metrics' => $this->metrics($ts),
            'notice' => 'Data kepuasan pengguna lulusan belum tersedia. Instrumen tracer study alumni perlu dilaksanakan dan hasilnya diimpor ke database sebelum tabel ini bisa diisi dengan data nyata.',
            'source' => ['name' => 'Tracer study alumni (belum tersedia)', 'partial' => true, 'template' => true],
        ];
    }

    /**
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(int $ts): array
    {
        return [
            ['label' => 'Jumlah jenis kemampuan terukur', 'value' => count(self::KEMAMPUAN)],
            ['label' => 'Total tanggapan pengguna', 'value' => 0],
            ['label' => 'Tingkat kepuasan keseluruhan', 'value' => 0],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    private function noticeEnvelope(int $ts, ?array $fakultas, ?array $programStudi, string $notice): array
    {
        return [
            'table_code' => '8.e.2',
            'table_name' => 'Kepuasan Pengguna Lulusan',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas ?? null,
            'program_studi' => $programStudi,
            'columns' => [],
            'rows' => [],
            'metrics' => [],
            'notice' => $notice,
            'source' => ['name' => 'Tracer study alumni (belum tersedia)', 'partial' => true, 'template' => true],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        return Cache::remember('sapto:kepuasan-pengguna:years:v1', $this->cacheTtl(), function (): array {
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
