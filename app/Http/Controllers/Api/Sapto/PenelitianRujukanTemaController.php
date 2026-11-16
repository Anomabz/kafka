<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoPenelitianRujukanTemaRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tabel 6.b) Penelitian DTPS yang Menjadi Rujukan Tema Tesis/Disertasi.
 *
 * Per program studi: membutuhkan prodi terpilih (scope fakultas/prodi).
 * Mengambil data bimbingan skripsi/tesis/disertasi dari list_bimbing_mahasiswa.
 * DTPS ditentukan lewat roster homebase list_penugasan_dosen.
 *
 * "Tema penelitian sesuai roadmap" menggunakan nama kategori bimbingan
 * sebagai proksi (roadmap tidak tersedia di database).
 */
class PenelitianRujukanTemaController extends SaptoTableController
{
    public function summary(Request $request, SaptoPenelitianRujukanTemaRepository $repository): JsonResponse
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
