<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoDashboardRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends SaptoTableController
{
    public function summary(Request $request, SaptoDashboardRepository $repository): JsonResponse
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
