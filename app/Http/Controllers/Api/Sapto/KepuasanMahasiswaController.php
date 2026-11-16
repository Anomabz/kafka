<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoKepuasanMahasiswaRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tabel 5.c) Kepuasan Mahasiswa.
 *
 * Per program studi: membutuhkan prodi terpilih (scope fakultas/prodi).
 * Mengukur kepuasan mahasiswa terhadap layanan perguruan tinggi menggunakan
 * 5 aspek SERVQUAL (Keandalan, Daya Tanggap, Kepastian, Empati, Tangible).
 *
 * Data survei kepuasan mahasiswa belum tersedia di database. Repository
 * return kolom + aspek placeholder; data real diisi setelah sumber tersedia.
 */
class KepuasanMahasiswaController extends SaptoTableController
{
    public function summary(Request $request, SaptoKepuasanMahasiswaRepository $repository): JsonResponse
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
