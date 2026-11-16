<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoKepuasanPenggunaRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tabel 8.e.2) Kepuasan Pengguna Lulusan.
 *
 * Per program studi: membutuhkan prodi terpilih (scope fakultas/prodi).
 * Mengukur kepuasan pengguna (mitra industri/institusi) terhadap
 * kompetensi lulusan berdasarkan 7 jenis kemampuan.
 *
 * Data tracer study alumni belum tersedia di database. Repository return
 * kolom + aspek placeholder; data real diisi setelah tracer study tersedia.
 */
class KepuasanPenggunaController extends SaptoTableController
{
    public function summary(Request $request, SaptoKepuasanPenggunaRepository $repository): JsonResponse
    {
        $ts = $this->validateTs($request);
        $context = SaptoUserContext::fromRequest($request);

        return response()->json($repository->summary(
            $ts,
            $context->resolveFakultasId($request),
            $context->resolveProgramStudiId($request),
        ));
    }
}
