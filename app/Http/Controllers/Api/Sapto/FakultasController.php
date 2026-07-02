<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Http\Controllers\Controller;
use App\Services\PangkalanDataApiClient;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FakultasController extends Controller
{
    public function __invoke(Request $request, PangkalanDataApiClient $client): JsonResponse
    {
        $context = SaptoUserContext::fromRequest($request);
        $fakultas = $client->faculties();

        if ($context->role === 'fakultas' && $context->fakultasId !== null) {
            $fakultas = $fakultas
                ->filter(fn (array $item): bool => (string) $item['id'] === $context->fakultasId)
                ->values();

            if ($fakultas->isEmpty()) {
                throw new HttpException(403, 'Fakultas akun tidak ditemukan.');
            }
        }

        if ($context->role === 'prodi' && $context->programStudiId !== null) {
            $programStudi = $client->programStudis()
                ->first(fn (array $item): bool => (string) $item['id'] === $context->programStudiId);

            if (! $programStudi) {
                throw new HttpException(403, 'Program studi akun tidak ditemukan.');
            }

            $fakultas = $fakultas
                ->filter(fn (array $item): bool => (string) $item['id'] === (string) $programStudi['fakultas_id'])
                ->values();
        }

        return response()->json([
            'can_select_fakultas' => $context->canSelectFakultas(),
            'data' => $fakultas->values(),
        ]);
    }
}
