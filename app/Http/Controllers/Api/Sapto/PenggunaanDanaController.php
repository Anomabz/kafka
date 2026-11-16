<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoPenggunaanDanaRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tabel 4) Penggunaan Dana.
 *
 * Institution-wide: data tidak perlu discope fakultas/prodi. Matriks
 * penggunaan dana per jenis (operasional pendidikan, kemahasiswaan, penelitian,
 * PkM, investasi SDM/sarana/prasarana) dengan breakdown TS-2/TS-1/TS dan
 * rata-rata untuk Unit Pengelola PS dan Program Studi.
 *
 * Data keuangan belum tersedia di database. Repository return kolom +
 * struktur placeholder; data real diisi setelah tabel keuangan tersedia.
 */
class PenggunaanDanaController extends SaptoTableController
{
    public function summary(Request $request, SaptoPenggunaanDanaRepository $repository): JsonResponse
    {
        $ts = $this->validateTs($request);

        return response()->json($repository->summary($ts));
    }
}
