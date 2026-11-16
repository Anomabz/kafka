<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoSitasiDtpsRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tabel 3.b.5) Karya Ilmiah DTPS yang Disitasi.
 *
 * Per program studi: membutuhkan prodi terpilih (scope fakultas/prodi).
 * Mengambil artikel jurnal/prosiding dengan DOI atau ISSN dari
 * sister_detail_publikasi. DTPS ditentukan lewat roster
 * sister_list_penugasan (homebase per id_sdm).
 *
 * Kolom "Jumlah Sitasi" belum tersedia di SISTER -- ditampilkan 0
 * sampai integrasi CrossRef/Scopus API tersedia.
 */
class SitasiDtpsController extends SaptoTableController
{
    public function summary(Request $request, SaptoSitasiDtpsRepository $repository): JsonResponse
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
