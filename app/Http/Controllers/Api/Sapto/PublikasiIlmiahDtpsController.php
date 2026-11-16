<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoPublikasiIlmiahDtpsRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublikasiIlmiahDtpsController extends SaptoTableController
{
    public function detail(Request $request, SaptoPublikasiIlmiahDtpsRepository $repository): JsonResponse
    {
        return $this->detailFor($request, $repository, ['all', 'jurnal_tidak_terakreditasi', 'jurnal_nasional_terakreditasi', 'jurnal_internasional', 'jurnal_internasional_bereputasi', 'seminar_wilayah', 'seminar_nasional', 'seminar_internasional', 'media_wilayah', 'media_nasional', 'media_internasional']);
    }

    public function summary(Request $request, SaptoPublikasiIlmiahDtpsRepository $repository): JsonResponse
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
