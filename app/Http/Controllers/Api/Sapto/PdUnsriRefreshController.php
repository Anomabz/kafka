<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Http\Controllers\Controller;
use App\Services\SaptoReportRefresher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/sapto/pd-unsri/refresh — tombol "Segarkan laporan" di popover
 * badge API. Lingkup diambil dari SaptoUserContext (tidak dari input), jadi
 * pengguna hanya bisa menyegarkan laporan lingkupnya sendiri.
 */
class PdUnsriRefreshController extends Controller
{
    public function __invoke(Request $request, SaptoReportRefresher $refresher): JsonResponse
    {
        return response()->json($refresher->start($request), 202)->header('Cache-Control', 'no-store');
    }
}
