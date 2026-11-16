<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoIpkLulusanRepository;
use App\Services\SaptoTracerLulusanRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IpkLulusanController extends SaptoTableController
{
    public function summary(Request $request, SaptoIpkLulusanRepository $repository): JsonResponse
    {
        $ts = $this->validateTs($request);
        $context = SaptoUserContext::fromRequest($request);

        return response()->json($repository->summary(
            $ts,
            $context->resolveFakultasId($request),
            $context->resolveProgramStudiId($request),
        ));
    }

    public function detail(Request $request, SaptoIpkLulusanRepository $repository, SaptoTracerLulusanRepository $tracerRepository): JsonResponse
    {
        $validated = $request->validate([
            'tahun' => ['required', 'string', 'regex:/^[0-9]{4}(,[0-9]{4}){0,2}$/'],
            'filter' => ['sometimes', 'required', 'string', 'in:jumlah_terlacak,wt_1,wt_2,wt_3'],
        ]);
        $years = array_map('intval', explode(',', $validated['tahun']));
        if (collect($years)->contains(fn (int $year): bool => $year < 2000 || $year > 2100)) {
            abort(422, 'Tahun lulus harus berada pada rentang 2000 sampai 2100.');
        }

        if (! isset($validated['filter']) && count($years) !== 1) {
            abort(422, 'Detail lulusan hanya menerima satu tahun.');
        }

        $context = SaptoUserContext::fromRequest($request);

        if (isset($validated['filter'])) {
            return response()->json([
                'success' => true,
                'tahun' => $years,
                'data' => $tracerRepository->detail(
                    $years,
                    $validated['filter'],
                    $context->resolveFakultasId($request),
                    $context->resolveProgramStudiId($request),
                ),
            ]);
        }

        return response()->json([
            'success' => true,
            'tahun' => $years[0],
            'data' => $repository->detail(
                $years[0],
                $context->resolveFakultasId($request),
                $context->resolveProgramStudiId($request),
            ),
        ]);
    }
}
