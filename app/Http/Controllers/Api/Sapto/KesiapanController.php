<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoKesiapanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cincin kesiapan data akreditasi di Beranda mobile -- lihat SaptoKesiapanService.
 */
class KesiapanController extends SaptoTableController
{
    public function __invoke(Request $request, SaptoKesiapanService $service): JsonResponse
    {
        return response()->json($service->summary($request, $this->validateTs($request)));
    }
}
