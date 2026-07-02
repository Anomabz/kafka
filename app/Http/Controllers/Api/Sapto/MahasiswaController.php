<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        // "Aktif pada tahun X" = sudah terdaftar (id_periode_masuk <= X) dan
        // belum keluar sebelum tahun X berakhir (id_periode_keluar kosong,
        // atau keluarnya di tahun setelah X). Dulu kolom ini cuma mengecek
        // id_periode_masuk = X, yang sebenarnya menghitung "mahasiswa baru
        // angkatan X", bukan total mahasiswa aktif di prodi pada tahun itu -
        // angkanya jauh lebih kecil dari yang seharusnya (lihat histori commit
        // untuk perbandingan angka sebelum/sesudah perbaikan ini).
        $aktifTs2 = "SUBSTRING(lrpm.id_periode_masuk, 1, 4) <= '$ts2' AND (lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' OR SUBSTRING(lrpm.id_periode_keluar, 1, 4) > '$ts2')";
        $aktifTs1 = "SUBSTRING(lrpm.id_periode_masuk, 1, 4) <= '$ts1' AND (lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' OR SUBSTRING(lrpm.id_periode_keluar, 1, 4) > '$ts1')";
        $aktifTs  = "SUBSTRING(lrpm.id_periode_masuk, 1, 4) <= '$ts'  AND (lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' OR SUBSTRING(lrpm.id_periode_keluar, 1, 4) > '$ts')";

        $data = DB::select("
            SELECT
                ps.id_prodi, -- ID Prodi (UUID) dimunculkan sebagai modal link klik untuk Frontend
                ps.kode_program_studi, -- Dipakai untuk memetakan prodi -> fakultas (lihat facultyByProdiCode())

                -- KOLOM 2: Program Studi (Gabungan Jenjang + Nama)
                CONCAT(ps.nama_jenjang_pendidikan, ' ', ps.nama_program_studi) AS program_studi,

                -- KOLOM 3, 4, 5: Jumlah Mahasiswa Aktif pada TS-2/TS-1/TS (Total Lokal + Asing)
                COALESCE(SUM(CASE WHEN $aktifTs2 THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts2,
                COALESCE(SUM(CASE WHEN $aktifTs1 THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts1,
                COALESCE(SUM(CASE WHEN $aktifTs  THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts,

                -- KOLOM 6, 7, 8: Jumlah Mahasiswa Asing Penuh Waktu (Full-time) - aktif pada tahun terkait + Filter ID Negara Ketat
                COALESCE(SUM(CASE WHEN $aktifTs2 AND n.id_negara IS NOT NULL AND n.id_negara <> 'ID' THEN 1 ELSE 0 END), 0) AS asing_ft_ts2,
                COALESCE(SUM(CASE WHEN $aktifTs1 AND n.id_negara IS NOT NULL AND n.id_negara <> 'ID' THEN 1 ELSE 0 END), 0) AS asing_ft_ts1,
                COALESCE(SUM(CASE WHEN $aktifTs  AND n.id_negara IS NOT NULL AND n.id_negara <> 'ID' THEN 1 ELSE 0 END), 0) AS asing_ft_ts,

                -- KOLOM 9, 10, 11: Jumlah Mahasiswa Asing Paruh Waktu (Part-time)
                0 AS asing_pt_ts2,
                0 AS asing_pt_ts1,
                0 AS asing_pt_ts

            FROM program_studi ps
            LEFT JOIN list_riwayat_pendidikan_mahasiswa lrpm
                ON lrpm.id_prodi = ps.id_prodi
            LEFT JOIN biodata_mahasiswa bm
                ON bm.id_mahasiswa = lrpm.id_mahasiswa
            LEFT JOIN negara n
                ON n.id_negara = bm.id_negara
            WHERE ps.nama_program_studi IS NOT NULL
            GROUP BY
                ps.id_prodi,
                ps.kode_program_studi,
                ps.nama_program_studi,
                ps.nama_jenjang_pendidikan
            -- Ini Tabel 2.b MAHASISWA ASING: hanya tampilkan program studi yang
            -- benar-benar punya mahasiswa asing (full-time/part-time) aktif pada
            -- salah satu dari TS-2/TS-1/TS. Kolom 'Jumlah Mahasiswa Aktif' tetap
            -- total (lokal + asing) sebagai denominator sesuai format borang.
            HAVING (asing_ft_ts2 + asing_ft_ts1 + asing_ft_ts
                    + asing_pt_ts2 + asing_pt_ts1 + asing_pt_ts) > 0
            ORDER BY
                ps.nama_program_studi ASC
        ");

        // Lengkapi tiap baris dengan fakultas. Tabel program_studi (skema
        // Feeder) tidak punya kolom fakultas, jadi dipetakan lewat
        // kode_program_studi -> fakultas dari database/data/program_studi.csv.
        // Dipakai frontend untuk filter role Pengelola Fakultas/Program Studi.
        $facultyMap = $this->facultyByProdiCode();

        $rows = array_map(function ($row) use ($facultyMap) {
            $faculty = $facultyMap[$row->kode_program_studi] ?? 'Belum Dipetakan';
            $row->fakultas = $faculty;
            $row->fakultas_id = Str::slug($faculty) ?: 'belum-dipetakan';

            return $row;
        }, $data);

        return response()->json([
            'success' => true,
            'tahun_akademik' => [
                'ts_2' => $ts2,
                'ts_1' => $ts1,
                'ts'   => $ts
            ],
            'total_prodi' => count($rows),
            'data' => $rows
        ]);
    }

    /**
     * Peta kode_program_studi -> nama fakultas, dibaca dari
     * database/data/program_studi.csv (satu-satunya sumber pemetaan
     * fakultas yang tersedia lokal; tabel program_studi tidak menyimpannya).
     *
     * @return array<string, string>
     */
    private function facultyByProdiCode(): array
    {
        $path = database_path('data/program_studi.csv');

        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        // $escape = '' eksplisit: hindari deprecation PHP 8.4+ sekaligus
        // matikan penanganan escape backslash yang tak dipakai di CSV ini.
        $headers = fgetcsv($handle, 0, ',', '"', '') ?: [];
        $map = [];

        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($headers) !== count($values)) {
                continue;
            }

            $record = array_combine($headers, $values);
            $code = trim((string) ($record['kode'] ?? ''));

            if ($code === '') {
                continue;
            }

            $map[$code] = $this->normalizeFaculty((string) ($record['fakultas'] ?? ''));
        }

        fclose($handle);

        return $map;
    }

    /**
     * Rapikan nama fakultas mentah dari CSV menjadi label yang konsisten,
     * mis. "KEDOKTERANM" -> "Fakultas Kedokteran", "PASCASARJANA" ->
     * "Pascasarjana". Selaras dengan PangkalanDataApiClient::normalizeFaculty().
     */
    private function normalizeFaculty(string $faculty): string
    {
        $faculty = trim((string) preg_replace('/\s+/', ' ', Str::upper(Str::ascii($faculty))));

        if ($faculty === 'KEDOKTERANM') {
            $faculty = 'KEDOKTERAN';
        }

        if ($faculty === '') {
            return 'Belum Dipetakan';
        }

        if ($faculty === 'PASCASARJANA') {
            return 'Pascasarjana';
        }

        if (str_starts_with($faculty, 'FAKULTAS ')) {
            $faculty = trim(substr($faculty, 9));
        }

        return 'Fakultas '.Str::title(Str::lower($faculty));
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
