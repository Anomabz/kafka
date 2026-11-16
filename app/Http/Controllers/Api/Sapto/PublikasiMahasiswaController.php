<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoPublikasiIlmiahMahasiswaRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tabel 8.f.1) Publikasi Ilmiah Mahasiswa (program akademik). Lihat SaptoPublikasiMahasiswaRepository.
 */
class PublikasiMahasiswaController extends SaptoTableController
{
    public function summary(Request $request, SaptoPublikasiIlmiahMahasiswaRepository $repository): JsonResponse
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
