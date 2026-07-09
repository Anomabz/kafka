<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Http\Controllers\Controller;
use App\Services\SaptoMahasiswaAsingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MahasiswaController extends Controller
{
    /**
     * FUNGSI 1: Rekapitulasi Matriks Angka (Sesuai 11 Kolom Borang Excel BAN-PT)
     * URL: http://10.8.5.67:8001/sapto/mahasiswa-asing
     */
    public function data_mahasiswa_asing(Request $request, SaptoMahasiswaAsingRepository $repository)
    {
        $summary = $repository->summary();

        return response()->json([
            'success' => true,
            'tahun_akademik' => $summary['tahun_akademik'],
            'total_prodi' => $summary['total_prodi'],
            'data' => $summary['data'],
        ]);
    }

    /**
     * FUNGSI 2: Detail List Mahasiswa Pas Diklik (Mendukung filter id_prodi ATAU nama_prodi)
     * URL Contoh 1: http://10.8.5.67:8001/sapto/mahasiswa-asing/detail?id_prodi=UUID_DISINI
     * URL Contoh 2: http://10.8.5.67:8001/sapto/mahasiswa-asing/detail?nama_prodi=Administrasi Publik&tahun=2025
     */
    public function detail_mahasiswa_asing(Request $request)
    {
        $idProdi   = $request->query('id_prodi');   // Menangkap parameter ID (UUID)
        $namaProdi = $request->query('nama_prodi'); // Menangkap parameter Nama Prodi
        $tahun     = $request->query('tahun');      // Menangkap parameter Tahun Angkatan (Opsional)

        // Validasi awal: Harus mengirimkan salah satu parameter identitas prodi
        if (!$idProdi && !$namaProdi) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal, salah satu parameter id_prodi atau nama_prodi wajib dikirim!'
            ], 400);
        }

        $query = "
            SELECT
                lrpm.nim,
                lrpm.nama_mahasiswa AS nama,
                CONCAT(ps.nama_jenjang_pendidikan, ' ', ps.nama_program_studi) AS program_studi,
                SUBSTRING(lrpm.id_periode_masuk, 1, 4) AS angkatan,
                CASE
                    WHEN lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' THEN 'Aktif'
                    ELSE COALESCE(lrpm.keterangan_keluar, 'Keluar')
                END AS status,
                IFNULL(n.nama_negara, 'Belum Diisi') AS kewarganegaraan
            FROM list_riwayat_pendidikan_mahasiswa lrpm
            LEFT JOIN program_studi ps ON ps.id_prodi = lrpm.id_prodi
            LEFT JOIN biodata_mahasiswa bm ON bm.id_mahasiswa = lrpm.id_mahasiswa
            LEFT JOIN negara n ON n.id_negara = bm.id_negara
            WHERE 1=1
        ";

        $params = [];

        // Pilihan Filter 1: Jika menggunakan ID Prodi (UUID)
        if ($idProdi) {
            $query .= " AND lrpm.id_prodi = ? ";
            $params[] = $idProdi;
        }
        // Pilihan Filter 2: Jika menggunakan Nama Prodi (Menggunakan klausa LIKE agar fleksibel)
        elseif ($namaProdi) {
            $query .= " AND CONCAT(ps.nama_jenjang_pendidikan, ' ', ps.nama_program_studi) LIKE ? ";
            $params[] = '%' . $namaProdi . '%';
        }

        // Spesifik per tahun TS / TS-1 / TS-2 yang diklik. "Aktif pada tahun X"
        // harus sama persis dengan logika di data_mahasiswa_asing() supaya
        // jumlah baris di sini selalu cocok dengan angka yang diklik: sudah
        // terdaftar sebelum/di tahun X, dan belum keluar sebelum tahun X
        // berakhir. Tidak dibatasi ke angkatan 2023-2025 seperti sebelumnya,
        // karena mahasiswa angkatan lama yang masih aktif di tahun X tetap
        // harus muncul.
        if ($tahun) {
            $query .= " AND SUBSTRING(lrpm.id_periode_masuk, 1, 4) <= ? AND (lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' OR SUBSTRING(lrpm.id_periode_keluar, 1, 4) > ?) ";
            $params[] = $tahun;
            $params[] = $tahun;
        }

        /* * NOTE UNTUK TESTING LOKAL:
         * Aturan pembimbing mewajibkan penyaringan mahasiswa asing murni mumpuni lewat ID Negara:
         * "AND n.id_negara IS NOT NULL AND n.id_negara <> 'ID'"
         * * Jika saat ini data tabel biodata_mahasiswa lu masih kosong dan lu ingin menguji kelancaran 
         * visual data mahasiswa lokal Indonesia terlebih dahulu, silakan beri tanda komentar (--) 
         * pada dua baris filter negara di bawah ini.
         */
        $query .= " AND n.id_negara IS NOT NULL ";
        $query .= " AND n.id_negara <> 'ID' ";

        $query .= " ORDER BY lrpm.id_periode_masuk DESC, lrpm.nama_mahasiswa ASC ";
        
        $data = DB::select($query, $params);

        return response()->json([
            'success' => true,
            'total_mahasiswa' => count($data),
            'filter_digunakan' => [
                'id_prodi' => $idProdi,
                'nama_prodi' => $namaProdi,
                'tahun' => $tahun
            ],
            'data' => $data
        ]);
    }
}
