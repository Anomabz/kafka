<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoHkiHakCiptaMahasiswaRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tabel 8.f.4) Bagian-2 HKI (Hak Cipta, Desain Produk Industri, dll.) yang dihasilkan mahasiswa: karya SISTER dengan penulis
 * mahasiswa dari program studi di lingkup. Lihat SaptoLuaranMahasiswaRepository.
 */
class HkiHakCiptaMahasiswaController extends SaptoTableController
{
    public function summary(Request $request, SaptoHkiHakCiptaMahasiswaRepository $repository): JsonResponse
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
