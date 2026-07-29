<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoPrestasiNonAkademikRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrestasiNonAkademikController extends SaptoTableController
{
    public function summary(Request $request, SaptoPrestasiNonAkademikRepository $repository): JsonResponse
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
