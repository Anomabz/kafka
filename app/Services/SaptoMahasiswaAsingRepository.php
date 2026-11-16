<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SaptoMahasiswaAsingRepository
{
    use ResolvesSaptoTs;

    public function __construct(
        private readonly SaptoProgramStudiRepository $client
    ) {}

    public function summary(
        ?string $fakultasId = null,
        ?string $programStudiId = null,
        ?int $ts = null
    ): array {
        $ts ??= $this->latestYear();

        $unscoped = Cache::remember(
            "sapto:mahasiswa-asing:v4:{$ts}",
            $this->cacheTtl(),
            fn (): array => $this->queryUnscoped($ts)
        );

        $rows = array_values(
            array_filter(
                $unscoped['rows'],
                function (array $row) use (
                    $fakultasId,
                    $programStudiId
                ): bool {
                    if (
                        $fakultasId !== null &&
                        $row['fakultas_id'] !== $fakultasId
                    ) {
                        return false;
                    }

                    if (
                        $programStudiId !== null &&
                        $row['id_prodi'] !== $programStudiId
                    ) {
                        return false;
                    }

                    return true;
                }
            )
        );

        return [
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'tahun_akademik' => $unscoped['tahun_akademik'],
            'total_prodi' => count($rows),
            'data' => array_map(
                fn (array $row): object => (object) $row,
                $rows
            ),
        ];
    }

    public function availableYears(): array
    {
        return Cache::remember(
            'sapto:mahasiswa-asing:years:v3',
            $this->cacheTtl(),
            function (): array {
                $earliest = (int) (
                    DB::table('list_mahasiswa')
                        ->whereNotNull('id_periode')
                        ->where('id_periode', '!=', '')
                        ->min(
                            DB::raw(
                                'SUBSTRING(id_periode, 1, 4)'
                            )
                        )
                    ?: $this->latestYear()
                );

                $latest = $this->latestYear();

                return range(
                    $latest,
                    max($earliest, $latest - 30),
                    -1
                );
            }
        );
    }

    private function latestYear(): int
    {
        return $this->ts();
    }

    private function queryUnscoped(
        int $latestYear
    ): array {
        $ts = $latestYear;
        $ts1 = $latestYear - 1;
        $ts2 = $latestYear - 2;

        $sql = "
            WITH target_years AS (
                SELECT
                    ? AS tahun,
                    'ts2' AS periode

                UNION ALL

                SELECT
                    ? AS tahun,
                    'ts1' AS periode

                UNION ALL

                SELECT
                    ? AS tahun,
                    'ts' AS periode
            ),

            ranked_mahasiswa AS (
                SELECT
                    lm.id_mahasiswa,
                    lm.id_prodi,
                    lm.id_periode,
                    lm.id_periode_keluar,
                    lm.tanggal_keluar,
                    lm.id_status_mahasiswa,
                    lm.nama_status_mahasiswa,

                    ty.tahun,
                    ty.periode,

                    ROW_NUMBER() OVER (
                        PARTITION BY
                            lm.id_mahasiswa,
                            ty.periode

                        ORDER BY
                            CAST(
                                lm.id_periode AS UNSIGNED
                            ) DESC
                    ) AS rn

                FROM target_years ty

                INNER JOIN list_mahasiswa lm
                    ON lm.id_periode IS NOT NULL
                    AND TRIM(lm.id_periode) <> ''
                    AND LEFT(
                        lm.id_periode,
                        4
                    ) <= ty.tahun
            ),

            active_mahasiswa AS (
                SELECT
                    id_mahasiswa,
                    id_prodi,
                    periode,
                    tahun
                FROM ranked_mahasiswa

                WHERE rn = 1

                  AND (
                        id_periode_keluar IS NULL

                        OR TRIM(
                            id_periode_keluar
                        ) = ''

                        OR LEFT(
                            id_periode_keluar,
                            4
                        ) > tahun
                  )
            )

            SELECT
                ps.id_prodi,

                ps.kode_program_studi,

                CONCAT(
                    ps.nama_jenjang_pendidikan,
                    ' ',
                    ps.nama_program_studi
                ) AS program_studi,

                SUM(
                    CASE
                        WHEN am.periode = 'ts2'
                        THEN 1
                        ELSE 0
                    END
                ) AS mhs_aktif_ts2,

                SUM(
                    CASE
                        WHEN am.periode = 'ts1'
                        THEN 1
                        ELSE 0
                    END
                ) AS mhs_aktif_ts1,

                SUM(
                    CASE
                        WHEN am.periode = 'ts'
                        THEN 1
                        ELSE 0
                    END
                ) AS mhs_aktif_ts,

                SUM(
                    CASE
                        WHEN am.periode = 'ts2'
                             AND n.id_negara IS NOT NULL
                             AND n.id_negara <> 'ID'
                        THEN 1
                        ELSE 0
                    END
                ) AS asing_ft_ts2,

                SUM(
                    CASE
                        WHEN am.periode = 'ts1'
                             AND n.id_negara IS NOT NULL
                             AND n.id_negara <> 'ID'
                        THEN 1
                        ELSE 0
                    END
                ) AS asing_ft_ts1,

                SUM(
                    CASE
                        WHEN am.periode = 'ts'
                             AND n.id_negara IS NOT NULL
                             AND n.id_negara <> 'ID'
                        THEN 1
                        ELSE 0
                    END
                ) AS asing_ft_ts,

                0 AS asing_pt_ts2,
                0 AS asing_pt_ts1,
                0 AS asing_pt_ts

            FROM active_mahasiswa am

            INNER JOIN program_studi ps
                ON ps.id_prodi = am.id_prodi

            LEFT JOIN biodata_mahasiswa bm
                ON bm.id_mahasiswa = am.id_mahasiswa

            LEFT JOIN negara n
                ON n.id_negara = bm.id_negara

            WHERE
                ps.nama_program_studi IS NOT NULL

            GROUP BY
                ps.id_prodi,
                ps.kode_program_studi,
                ps.nama_program_studi,
                ps.nama_jenjang_pendidikan

            HAVING
                asing_ft_ts2 > 0
                OR asing_ft_ts1 > 0
                OR asing_ft_ts > 0

            ORDER BY
                ps.nama_program_studi ASC
        ";

        $data = DB::select(
            $sql,
            [
                $ts2,
                $ts1,
                $ts,
            ]
        );

        $programIndex =
            $this->client
                ->programStudis()
                ->keyBy('id');

        $rows = array_map(
            function (object $row) use (
                $programIndex
            ): array {
                $row = (array) $row;

                $program =
                    $programIndex->get(
                        (string) $row['id_prodi']
                    );

                $row['fakultas'] =
                    $program['fakultas']
                    ?? 'Belum Dipetakan';

                $row['fakultas_id'] =
                    $program['fakultas_id']
                    ?? 'belum-dipetakan';

                $row['program_studi'] =
                    $program['label']
                    ?? $row['program_studi'];

                $row['mhs_aktif_ts2'] =
                    (int) $row['mhs_aktif_ts2'];

                $row['mhs_aktif_ts1'] =
                    (int) $row['mhs_aktif_ts1'];

                $row['mhs_aktif_ts'] =
                    (int) $row['mhs_aktif_ts'];

                $row['asing_ft_ts2'] =
                    (int) $row['asing_ft_ts2'];

                $row['asing_ft_ts1'] =
                    (int) $row['asing_ft_ts1'];

                $row['asing_ft_ts'] =
                    (int) $row['asing_ft_ts'];

                $row['asing_pt_ts2'] =
                    (int) $row['asing_pt_ts2'];

                $row['asing_pt_ts1'] =
                    (int) $row['asing_pt_ts1'];

                $row['asing_pt_ts'] =
                    (int) $row['asing_pt_ts'];

                return $row;
            },
            $data
        );

        return [
            'tahun_akademik' => [
                'ts_2' => (string) $ts2,
                'ts_1' => (string) $ts1,
                'ts' => (string) $ts,
            ],
            'rows' => $rows,
        ];
    }

    private function cacheTtl(): int
    {
        return max(
            1,
            (int) config(
                'services.pangkalan_data.cache_ttl',
                3600
            )
        );
    }
}