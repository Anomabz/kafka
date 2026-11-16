<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoEwmpDosenRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tabel 3.a.3) Ekuivalen Waktu Mengajar Penuh (EWMP) Dosen Tetap
 * Perguruan Tinggi.
 *
 * Per program studi: membutuhkan prodi terpilih (scope fakultas/prodi).
 * Kolom EWMP breakdown per kategori (pendidikan, penelitian, PkM, tugas
 * tambahan) dalam satuan SKS.
 *
 * Kolom saat ini placeholder karena sister_detail_pengajaran dan
 * sister_detail_bimbingan_mahasiswa belum disinkronkan. Repository return
 * kolom yang benar; data numerik EWMP diisi setelah tabel sumber tersedia.
 */
class EwmpDosenController extends SaptoTableController
{
    public function summary(Request $request, SaptoEwmpDosenRepository $repository): JsonResponse
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
