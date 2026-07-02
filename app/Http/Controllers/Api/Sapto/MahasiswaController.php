<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MahasiswaController extends Controller
{
    /**
     * FUNGSI 1: Rekapitulasi Matriks Angka (Sesuai 11 Kolom Borang Excel BAN-PT)
     * URL: http://10.8.5.67:8001/sapto/mahasiswa-asing
     */
    public function data_mahasiswa_asing(Request $request)
    {
        // Mengunci 3 Tahun Akademik Terakhir (TS=2025, TS-1=2024, TS-2=2023)
        $ts  = '2025'; 
        $ts1 = '2024'; 
        $ts2 = '2023'; 

        $data = DB::select("
            SELECT 
                ps.id_prodi, -- ID Prodi (UUID) dimunculkan sebagai modal link klik untuk Frontend
                
                -- KOLOM 2: Program Studi (Gabungan Jenjang + Nama)
                CONCAT(ps.nama_jenjang_pendidikan, ' ', ps.nama_program_studi) AS program_studi,
                
                -- KOLOM 3, 4, 5: Jumlah Mahasiswa Aktif per Angkatan (Total Lokal + Asing)
                COALESCE(SUM(CASE WHEN SUBSTRING(lrpm.id_periode_masuk, 1, 4) = '$ts2' THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts2,
                COALESCE(SUM(CASE WHEN SUBSTRING(lrpm.id_periode_masuk, 1, 4) = '$ts1' THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts1,
                COALESCE(SUM(CASE WHEN SUBSTRING(lrpm.id_periode_masuk, 1, 4) = '$ts'  THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts,

                -- KOLOM 6, 7, 8: Jumlah Mahasiswa Asing Penuh Waktu (Full-time) - Filter ID Negara Ketat
                COALESCE(SUM(CASE WHEN SUBSTRING(lrpm.id_periode_masuk, 1, 4) = '$ts2' AND n.id_negara IS NOT NULL AND n.id_negara <> 'ID' THEN 1 ELSE 0 END), 0) AS asing_ft_ts2,
                COALESCE(SUM(CASE WHEN SUBSTRING(lrpm.id_periode_masuk, 1, 4) = '$ts1' AND n.id_negara IS NOT NULL AND n.id_negara <> 'ID' THEN 1 ELSE 0 END), 0) AS asing_ft_ts1,
                COALESCE(SUM(CASE WHEN SUBSTRING(lrpm.id_periode_masuk, 1, 4) = '$ts'  AND n.id_negara IS NOT NULL AND n.id_negara <> 'ID' THEN 1 ELSE 0 END), 0) AS asing_ft_ts,

                -- KOLOM 9, 10, 11: Jumlah Mahasiswa Asing Paruh Waktu (Part-time)
                0 AS asing_pt_ts2,
                0 AS asing_pt_ts1,
                0 AS asing_pt_ts

            FROM program_studi ps
            LEFT JOIN list_riwayat_pendidikan_mahasiswa lrpm 
                ON lrpm.id_prodi = ps.id_prodi
            LEFT JOIN list_mahasiswa lm 
                ON lm.id_registrasi_mahasiswa = lrpm.id_registrasi_mahasiswa
            LEFT JOIN biodata_mahasiswa bm 
                ON bm.id_mahasiswa = lrpm.id_mahasiswa
            LEFT JOIN negara n 
                ON n.id_negara = bm.id_negara
            WHERE ps.nama_program_studi IS NOT NULL
            GROUP BY 
                ps.id_prodi,
                ps.nama_program_studi,
                ps.nama_jenjang_pendidikan
            ORDER BY 
                ps.nama_program_studi ASC
        ");

        return response()->json([
            'success' => true,
            'tahun_akademik' => [
                'ts_2' => $ts2,
                'ts_1' => $ts1,
                'ts'   => $ts
            ],
            'total_prodi' => count($data),
            'data' => $data
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
                lm.nama_mahasiswa AS nama,
                CONCAT(ps.nama_jenjang_pendidikan, ' ', ps.nama_program_studi) AS program_studi,
                SUBSTRING(lrpm.id_periode_masuk, 1, 4) AS angkatan,
                lm.nama_status_mahasiswa AS status,
                IFNULL(n.nama_negara, 'Belum Diisi') AS kewarganegaraan
            FROM list_riwayat_pendidikan_mahasiswa lrpm
            LEFT JOIN list_mahasiswa lm ON lm.id_registrasi_mahasiswa = lrpm.id_registrasi_mahasiswa
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

        // Jalankan filter 3 tahun terakhir (hanya memproses angkatan 2023, 2024, dan 2025)
        $query .= " AND SUBSTRING(lrpm.id_periode_masuk, 1, 4) IN ('2023', '2024', '2025') ";

        // Filter Tambahan: Spesifik per tahun tertentu jika diklik pada kolom TS / TS-1 / TS-2 tertentu
        if ($tahun) {
            $query .= " AND SUBSTRING(lrpm.id_periode_masuk, 1, 4) = ? ";
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

        $query .= " ORDER BY lrpm.id_periode_masuk DESC, lm.nama_mahasiswa ASC ";
        
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
