<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Http\Controllers\Controller;
use App\Services\PdUnsriHealthCheck;
use App\Services\SaptoReportRefresher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/sapto/pd-unsri/status — sumber badge "API PD UNSRI" di topbar
 * (semua peran) dan kartu status di /admin/sync-pd-unsri.
 *
 * Status API di-cache sama untuk semua orang; blok `refresh` (tombol
 * "Segarkan laporan") dihitung per request karena lingkupnya per pengguna.
 */
class PdUnsriStatusController extends Controller
{
    public function __invoke(Request $request, PdUnsriHealthCheck $health, SaptoReportRefresher $refresher): JsonResponse
    {
        return response()
            ->json($health->current() + ['refresh' => $refresher->payloadFor($request)])
            ->header('Cache-Control', 'no-store');
    }
}
