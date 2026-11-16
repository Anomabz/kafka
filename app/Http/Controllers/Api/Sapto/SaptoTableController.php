<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Http\Controllers\Controller;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Basis controller untuk tabel-tabel SAPTO yang memakai satu tahun (TS) opsional
 * plus scope role (fakultas/prodi) yang diselesaikan lewat SaptoUserContext.
 */
abstract class SaptoTableController extends Controller
{
    protected function detailFor(Request $request, object $repository, array $categories): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'category' => ['required', 'in:'.implode(',', $categories)],
        ]);
        $context = SaptoUserContext::fromRequest($request);

        return response()->json(['items' => $repository->detail(
            (int) $data['year'], $data['category'],
            $context->resolveFakultasId($request),
            $context->resolveProgramStudiId($request),
        )]);
    }

    /**
     * TS opsional: null/''/'all' -> repository memakai tahun terbaru di database.
     */
    protected function validateTs(Request $request): ?int
    {
        $ts = $request->query('ts');

        if ($ts === null || $ts === '' || $ts === 'all') {
            return null;
        }

        if (! is_scalar($ts) || preg_match('/^[0-9]{4}$/', (string) $ts) !== 1) {
            throw new HttpException(400, 'Parameter TS tidak valid.');
        }

        $ts = (int) $ts;

        if ($ts < 2000 || $ts > 2100) {
            throw new HttpException(400, 'Parameter TS di luar rentang yang didukung.');
        }

        return $ts;
    }
}
