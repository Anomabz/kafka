<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoTempatKerjaRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TempatKerjaController extends SaptoTableController
{
    public function summary(Request $request, SaptoTempatKerjaRepository $repository): JsonResponse
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
