<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoPenelitianDtpsRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenelitianDtpsController extends SaptoTableController
{
    public function detail(Request $request, SaptoPenelitianDtpsRepository $repository): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'category' => ['required', 'in:all,pt_mandiri,dalam_negeri,luar_negeri'],
        ]);
        $context = SaptoUserContext::fromRequest($request);

        return response()->json(['items' => $repository->detail(
            (int) $data['year'], $data['category'],
            $context->resolveFakultasId($request),
            $context->resolveProgramStudiId($request),
        )]);
    }

    public function summary(Request $request, SaptoPenelitianDtpsRepository $repository): JsonResponse
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
