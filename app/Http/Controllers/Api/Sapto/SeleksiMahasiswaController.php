<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoSeleksiMahasiswaRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tabel 2.a) Seleksi Mahasiswa Baru.
 *
 * Mengikuti scope filter seperti tabel lain: tanpa filter = seluruh
 * universitas, fakultas = semua prodi di fakultas itu, prodi = satu prodi.
 * Fakultas/prodi diselesaikan lewat SaptoUserContext sehingga hak akses akun
 * tetap berlaku (dekan dikunci ke fakultasnya, korprodi ke prodinya).
 *
 * Kolom: Daya Tampung, Pendaftar, Lulus, Mahasiswa Baru, dan Mahasiswa Aktif
 * per angkatan TS-4..TS. Semua hitungan dihitung per id_registrasi_mahasiswa
 * dari list_mahasiswa -- lihat SaptoSeleksiMahasiswaRepository.
 */
class SeleksiMahasiswaController extends SaptoTableController
{
    public function summary(Request $request, SaptoSeleksiMahasiswaRepository $repository): JsonResponse
    {
        $ts = $this->validateTs($request);
        $context = SaptoUserContext::fromRequest($request);

        return response()->json($repository->summary(
            $ts,
            $context->resolveFakultasId($request),
            $context->resolveProgramStudiId($request),
        ));
    }

    public function detail(Request $request, SaptoSeleksiMahasiswaRepository $repository): JsonResponse
    {
        $validated = $request->validate([
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'kategori' => ['required', 'in:calon_lulus,mahasiswa_baru,mahasiswa_aktif'],
            'jenis_daftar' => ['sometimes', 'nullable', 'in:reguler,transfer'],
            'per_page' => ['sometimes', 'integer', 'in:10,20,50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $context = SaptoUserContext::fromRequest($request);

        return response()->json($repository->detail(
            (int) $validated['tahun'],
            (string) $validated['kategori'],
            (int) ($validated['per_page'] ?? 10),
            (int) ($validated['page'] ?? 1),
            $context->resolveFakultasId($request),
            $context->resolveProgramStudiId($request),
            $validated['jenis_daftar'] ?? null,
        ));
    }
}
