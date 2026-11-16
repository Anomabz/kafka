<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoMahasiswaAsingRepository;
use App\Services\SaptoProgramStudiRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MahasiswaController extends SaptoTableController
{
    public function data_mahasiswa_asing(
        Request $request,
        SaptoMahasiswaAsingRepository $repository
    ) {
        $ts = $this->validateTs($request);

        $context = SaptoUserContext::fromRequest($request);

        $summary = $repository->summary(
            $context->resolveFakultasId($request),
            $context->resolveProgramStudiId($request),
            $ts
        );

        return response()->json([
            'success' => true,
            'ts' => $summary['ts'],
            'available_years' => $summary['available_years'],
            'tahun_akademik' => $summary['tahun_akademik'],
            'total_prodi' => $summary['total_prodi'],
            'data' => $summary['data'],
        ]);
    }

    public function detail_mahasiswa_asing(Request $request)
    {
        $context = SaptoUserContext::fromRequest($request);

        $idProdi = $request->query('id_prodi')
            ?? $request->query('program_studi_id')
            ?? $context->resolveProgramStudiId($request);

        $fakultasId = $context->resolveFakultasId($request);

        $tahun = (int) $request->query('tahun');

        if ($tahun <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tahun wajib diisi.',
            ], 422);
        }

        $query = "
            SELECT
                x.id_mahasiswa,
                x.nim,
                x.nama,
                x.id_prodi,
                ps.nama_jenjang_pendidikan AS jenjang,
                ps.nama_program_studi AS nama_prodi,
                SUBSTRING(x.id_periode, 1, 4) AS angkatan,
                'Aktif' AS status,
                IFNULL(n.nama_negara, 'Belum Diisi') AS kewarganegaraan
            FROM (
                SELECT
                    lm.id_mahasiswa,
                    lm.nim,
                    lm.nama_mahasiswa AS nama,
                    lm.id_prodi,
                    lm.id_periode,
                    lm.id_periode_keluar,
                    lm.tanggal_keluar,
                    lm.id_status_mahasiswa,
                    lm.nama_status_mahasiswa,
                    ROW_NUMBER() OVER (
                        PARTITION BY lm.id_mahasiswa
                        ORDER BY
                            CAST(lm.id_periode AS UNSIGNED) DESC
                    ) AS rn
                FROM list_mahasiswa lm
                WHERE
                    lm.id_periode IS NOT NULL
                    AND TRIM(lm.id_periode) <> ''
                    AND LEFT(lm.id_periode, 4) <= ?
            ) x
            LEFT JOIN program_studi ps
                ON ps.id_prodi = x.id_prodi
            LEFT JOIN biodata_mahasiswa bm
                ON bm.id_mahasiswa = x.id_mahasiswa
            LEFT JOIN negara n
                ON n.id_negara = bm.id_negara
            WHERE
                x.rn = 1
                AND (
                    x.id_periode_keluar IS NULL
                    OR TRIM(x.id_periode_keluar) = ''
                    OR LEFT(x.id_periode_keluar, 4) > ?
                )
                AND n.id_negara IS NOT NULL
                AND n.id_negara <> 'ID'
        ";

        $params = [
            (string) $tahun,
            (string) $tahun,
        ];

        if ($idProdi !== null && $idProdi !== '') {
            $query .= " AND x.id_prodi = ? ";
            $params[] = $idProdi;
        } elseif ($fakultasId !== null) {
            $prodiFakultas = app(
                SaptoProgramStudiRepository::class
            )
                ->programStudis()
                ->where(
                    'fakultas_id',
                    $fakultasId
                )
                ->pluck('id')
                ->all();

            if ($prodiFakultas === []) {
                return response()->json([
                    'success' => true,
                    'total_mahasiswa' => 0,
                    'data' => [],
                ]);
            }

            $placeholders = implode(
                ',',
                array_fill(
                    0,
                    count($prodiFakultas),
                    '?'
                )
            );

            $query .= "
                AND x.id_prodi IN (
                    {$placeholders}
                )
            ";

            array_push(
                $params,
                ...$prodiFakultas
            );
        }

        $query .= "
            ORDER BY
                x.id_periode DESC,
                x.nama ASC
        ";

        $rows = DB::select(
            $query,
            $params
        );

        $data = array_map(
            function (object $row): object {
                $row->program_studi =
                    SaptoProgramStudiRepository::composeLabel(
                        $row->jenjang ?? '',
                        $row->nama_prodi ?? ''
                    );

                unset(
                    $row->jenjang,
                    $row->nama_prodi
                );

                return $row;
            },
            $rows
        );

        return response()->json([
            'success' => true,
            'total_mahasiswa' => count($data),
            'filter_digunakan' => [
                'id_prodi' => $idProdi,
                'tahun' => $tahun,
            ],
            'data' => $data,
        ]);
    }
}