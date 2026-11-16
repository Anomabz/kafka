<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoMasaStudiRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasaStudiController extends SaptoTableController
{
    public function summary(Request $request, SaptoMasaStudiRepository $repository): JsonResponse
    {
        $ts = $this->validateTs($request);
        $context = SaptoUserContext::fromRequest($request);

        return response()->json($repository->summary(
            $ts,
            $context->resolveFakultasId($request),
            $context->resolveProgramStudiId($request),
        ));
    }

    public function detail(Request $request, SaptoMasaStudiRepository $repository): JsonResponse
    {
        $ts = $this->validateTs($request);
        $validated = $request->validate([
            'angkatan' => ['required', 'integer', 'between:2000,2100'],
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'jenis' => ['required', 'in:diterima,lulus'],
        ]);
        $context = SaptoUserContext::fromRequest($request);

        return response()->json([
            'success' => true,
            'tahun' => $validated['tahun'] ?? null,
            'angkatan' => (int) $validated['angkatan'],
            'jenis' => $validated['jenis'],
            'data' => $repository->detail(
                $ts,
                (int) $validated['angkatan'],
                isset($validated['tahun']) ? (int) $validated['tahun'] : null,
                $validated['jenis'],
                $context->resolveFakultasId($request),
                $context->resolveProgramStudiId($request),
            ),
        ]);
    }
}
