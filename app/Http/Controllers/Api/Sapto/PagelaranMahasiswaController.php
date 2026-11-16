<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoPagelaranMahasiswaRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tabel 8.f.1) Pagelaran/Pameran/Presentasi/Publikasi Ilmiah Mahasiswa (program terapan). Lihat SaptoPublikasiMahasiswaRepository.
 */
class PagelaranMahasiswaController extends SaptoTableController
{
    public function summary(Request $request, SaptoPagelaranMahasiswaRepository $repository): JsonResponse
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
