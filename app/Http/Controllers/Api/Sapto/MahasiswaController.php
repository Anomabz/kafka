<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MahasiswaController extends Controller
{
    public function data_mahasiswa_asing(Request $request)
    {
        // Filter angkatan ganjil 2021 ke atas
        $minSemesterMasuk = '20211';

        $data = DB::select("
            SELECT
                lrpm.nim,
                lm.nama_mahasiswa AS nama,

                -- PRODI --
                ps.id_prodi,
                ps.nama_program_studi AS nama_prodi,
                ps.nama_jenjang_pendidikan AS jenjang,

                SUBSTRING(lrpm.id_periode_masuk, 1, 4) AS tahun_masuk,
                lrpm.id_periode_masuk AS semester_masuk,

                lm.ipk,
                lm.nama_status_mahasiswa AS status,
                jd.nama_jenis_daftar AS jenis_daftar,

                lmd.id_periode_keluar AS semester_lulus,
                lmd.tanggal_keluar,

                -- BIODATA --
                bm.id_negara,
                bm.kewarganegaraan,

                -- NEGARA --
                n.nama_negara

            FROM list_riwayat_pendidikan_mahasiswa lrpm

            LEFT JOIN list_mahasiswa lm
                ON lm.id_registrasi_mahasiswa = lrpm.id_registrasi_mahasiswa

            LEFT JOIN program_studi ps
                ON ps.id_prodi = lrpm.id_prodi

            LEFT JOIN jenis_daftar jd
                ON jd.id_jenis_daftar = lrpm.id_jenis_daftar

            LEFT JOIN list_mahasiswa_lulus_do lmd
                ON lmd.id_registrasi_mahasiswa = lrpm.id_registrasi_mahasiswa

            -- Menggunakan LEFT JOIN karena tabel core biodata_mahasiswa & negara di DB masih kosong
            LEFT JOIN biodata_mahasiswa bm
                ON bm.id_mahasiswa = lrpm.id_mahasiswa

            LEFT JOIN negara n
                ON n.id_negara = bm.id_negara

            WHERE lrpm.id_periode_masuk >= ?

            ORDER BY ps.nama_program_studi,
                     lrpm.id_periode_masuk DESC
        ", [$minSemesterMasuk]);

        return response()->json([
            'success' => true,
            'min_semester_masuk' => $minSemesterMasuk,
            'total' => count($data),
            'data' => $data
        ]);
    }
}
