<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Http\Controllers\Controller;
use App\Services\SaptoDosenPembimbingTaRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DosenPembimbingTaController extends Controller
{
    public function summary(Request $request, SaptoDosenPembimbingTaRepository $repository): JsonResponse
    {
        $ts = $this->validateTs($request);
        $context = SaptoUserContext::fromRequest($request);
        $fakultasId = $context->resolveFakultasId($request);
        $programStudiId = $context->resolveProgramStudiId($request);

        return response()->json($repository->summary($ts, $fakultasId, $programStudiId));
    }

    public function detail(Request $request, SaptoDosenPembimbingTaRepository $repository): JsonResponse
    {
        $ts = $this->validateTs($request);
        $context = SaptoUserContext::fromRequest($request);
        $fakultasId = $context->resolveFakultasId($request);
        $programStudiId = $context->resolveProgramStudiId($request);
        $yearKey = $this->validateEnum($request->query('year_key'), ['ts_2', 'ts_1', 'ts'], 'year_key');
        $scope = $this->validateEnum($request->query('scope'), ['ps_diakreditasi', 'ps_lain_pt'], 'scope');
        $dosenId = $request->query('dosen_id');

        if (! is_scalar($dosenId) || trim((string) $dosenId) === '') {
            throw new HttpException(400, 'Parameter dosen_id tidak valid.');
        }

        return response()->json($repository->detail($ts, $fakultasId, $programStudiId, (string) $dosenId, $scope, $yearKey));
    }

    private function validateTs(Request $request): int
    {
        $ts = $request->query('ts', now()->year);

        if (! is_scalar($ts) || preg_match('/^[0-9]{4}$/', (string) $ts) !== 1) {
            throw new HttpException(400, 'Parameter TS tidak valid.');
        }

        $ts = (int) $ts;

        if ($ts < 2000 || $ts > 2100) {
            throw new HttpException(400, 'Parameter TS di luar rentang yang didukung.');
        }

        return $ts;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function validateEnum(mixed $value, array $allowed, string $field): string
    {
        if (! is_scalar($value) || ! in_array((string) $value, $allowed, true)) {
            throw new HttpException(400, "Parameter {$field} tidak valid.");
        }

        return (string) $value;
    }
}
