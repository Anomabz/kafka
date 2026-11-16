<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;

/**
 * Tabel 5.c) Kepuasan Mahasiswa.
 *
 * Per program studi: membutuhkan prodi terpilih (scope fakultas/prodi).
 * Mengukur kepuasan mahasiswa terhadap layanan perguruan tinggi dengan 5 aspek
 * SERVQUAL: Keandalan, Daya Tanggap, Kepastian, Empati, Tangible.
 *
 * Data survei kepuasan mahasiswa belum tersedia di database mana pun. Repository
 * return kolom + aspek placeholder dengan baris kosong. Data real diisi setelah
 * instrumen survei tersedia.
 */
class SaptoKepuasanMahasiswaRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    private const GROUP_KEPUASAN = 'Tingkat Kepuasan Mahasiswa (%)';

    private const ASPEK = [
        'Keandalan (reliability)' => 'Keandalan menunjukkan kemampuan untuk memberikan '.
            'jasa yang dijanjikan dengan tepat dan terukur.',
        'Daya tanggap (responsiveness)' => 'Keinginan para staff untuk membantu '.
            'mahasiswa dan memberikan jasa dengan cepat.',
        'Kepastian (assurance)' => 'Pengetahuan dan kesopanan para staff untuk '.
            'membangun rasa percaya mahasiswa.',
        'Empati (empathy)' => 'Perhatian individual untuk setiap mahasiswa.',
        'Tangible' => 'Penilaian terhadap penampilan, kelengkapan, dan kenyamanan '.
            'sarana fisik.',
    ];

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'aspek', 'label' => 'Aspek yang Diukur', 'align' => 'left'],
        ['key' => 'sangat_baik', 'label' => 'Sangat Baik', 'align' => 'num', 'group' => self::GROUP_KEPUASAN],
        ['key' => 'baik', 'label' => 'Baik', 'align' => 'num', 'group' => self::GROUP_KEPUASAN],
        ['key' => 'cukup', 'label' => 'Cukup', 'align' => 'num', 'group' => self::GROUP_KEPUASAN],
        ['key' => 'kurang', 'label' => 'Kurang', 'align' => 'num', 'group' => self::GROUP_KEPUASAN],
        ['key' => 'rencana_tindak_lanjut', 'label' => 'Rencana Tindak Lanjut oleh UPPS/PS', 'align' => 'left'],
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultId, $programStudiId);
        $this->scopeProdiIds($programStudis, $fakultId, $programStudiId);

        $ts ??= $this->ts();

        if ($programStudi === null) {
            return $this->noticeEnvelope($ts, $fakultas, $programStudi,
                'Tabel 5.c disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan hasil kepuasan mahasiswa.');
        }

        $rows = [];
        $index = 0;
        foreach (self::ASPEK as $aspek => $desc) {
            $rows[] = [
                'no' => ++$index,
                'aspek' => $aspek,
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
            'aspek' => 'Jumlah',
            'sangat_baik' => 0,
            'baik' => 0,
            'cukup' => 0,
            'kurang' => 0,
            'rencana_tindak_lanjut' => '',
        ];

        return [
            'table_code' => '5.c',
            'table_name' => 'Kepuasan Mahasiswa',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'metrics' => $this->metrics($ts),
            'notice' => 'Data kepuasan mahasiswa belum tersedia. Instrumen survei perlu dirancang dan hasilnya diimpor ke database sebelum tabel ini bisa diisi dengan data nyata.',
            'source' => ['name' => 'Survei kepuasan mahasiswa (belum tersedia)', 'partial' => true, 'template' => true],
        ];
    }

    /**
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(int $ts): array
    {
        return [
            ['label' => 'Jumlah aspek terukur', 'value' => count(self::ASPEK)],
            ['label' => 'Responden survei', 'value' => 0],
            ['label' => 'Tingkat kepuasan keseluruhan', 'value' => 0],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    private function noticeEnvelope(int $ts, ?array $fakultas, ?array $programStudi, string $notice): array
    {
        return [
            'table_code' => '5.c',
            'table_name' => 'Kepuasan Mahasiswa',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [],
            'rows' => [],
            'metrics' => [],
            'notice' => $notice,
            'source' => ['name' => 'Survei kepuasan mahasiswa (belum tersedia)', 'partial' => true, 'template' => true],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        return Cache::remember('sapto:kepuasan-mahasiswa:years:v1', $this->cacheTtl(), function (): array {
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
