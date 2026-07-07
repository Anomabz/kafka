<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Http\Controllers\Controller;
use App\Services\SaptoProgramStudiRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProgramStudiController extends Controller
{
    public function __invoke(Request $request, SaptoProgramStudiRepository $client): JsonResponse
    {
        $context = SaptoUserContext::fromRequest($request);
        $programStudis = $client->programStudis();
        $fakultasId = $context->resolveFakultasId($request);

        if ($fakultasId !== null) {
            $programStudis = $programStudis
                ->filter(fn (array $programStudi): bool => (string) $programStudi['fakultas_id'] === $fakultasId)
                ->values();

            if ($programStudis->isEmpty()) {
                throw new HttpException(403, 'Fakultas akun tidak ditemukan atau belum memiliki program studi.');
            }
        }

        if ($context->role === 'prodi' && $context->programStudiId !== null) {
            $programStudis = $programStudis
                ->filter(fn (array $programStudi): bool => (string) $programStudi['id'] === $context->programStudiId)
                ->values();

            if ($programStudis->isEmpty()) {
                throw new HttpException(403, 'Program studi akun tidak ditemukan.');
            }
        }

        return response()->json([
            'can_select_fakultas' => $context->canSelectFakultas(),
            'can_select_program_studi' => $context->canSelectProgramStudi(),
            'fakultas_id' => $fakultasId,
            'data' => $programStudis->values(),
        ]);
    }
}
