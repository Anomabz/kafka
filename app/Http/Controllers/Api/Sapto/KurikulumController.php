<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoKurikulumRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KurikulumController extends SaptoTableController
{
    public function summary(Request $request, SaptoKurikulumRepository $repository): JsonResponse
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
