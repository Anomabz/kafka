<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoProdukJasaDtpsRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tabel 3.b.6) Produk/Jasa DTPS yang Diadopsi oleh Industri/Masyarakat.
 *
 * Per program studi: membutuhkan prodi terpilih (scope fakultas/prodi).
 * Mengambil HKI non-paten (hak cipta, desain produk, karya seni, dll.)
 * dari sister_detail_kekayaan_intelektual. DTPS ditentukan lewat
 * sister_list_penugasan (unit_kerja homebase per id_sdm).
 *
 * Kolom "Bukti" dan "Deskripsi" berasal dari kolom keterangan di
 * sumber; nilai sebenarnya perlu diverifikasi manual.
 */
class ProdukJasaDtpsController extends SaptoTableController
{
    public function summary(Request $request, SaptoProdukJasaDtpsRepository $repository): JsonResponse
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
