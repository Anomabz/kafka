<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoMahasiswaAsingRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MahasiswaController extends SaptoTableController
{
    /**
     * FUNGSI 1: Rekapitulasi Matriks Angka (Sesuai 11 Kolom Borang Excel BAN-PT)
     * URL: http://10.8.5.67:8001/sapto/mahasiswa-asing
     */
    public function data_mahasiswa_asing(Request $request, SaptoMahasiswaAsingRepository $repository)
    {
        $ts = $this->validateTs($request);
        $context = SaptoUserContext::fromRequest($request);
        $summary = $repository->summary($context->resolveFakultasId($request), $context->resolveProgramStudiId($request), $ts);

        return response()->json([
            'success' => true,
            'ts' => $summary['ts'],
            'available_years' => $summary['available_years'],
            'tahun_akademik' => $summary['tahun_akademik'],
            'total_prodi' => $summary['total_prodi'],
            'data' => $summary['data'],
        ]);
    }

    /**
     * FUNGSI 2: Detail List Mahasiswa Pas Diklik
     * URL: http://10.8.5.67:8001/sapto/mahasiswa-asing/detail?tahun=2025
     *
     * Prodi diambil dari SaptoUserContext (akun yang login), bukan dari
     * parameter klien — dulu endpoint ini menerima id_prodi/nama_prodi
     * mentah dari request tanpa validasi sama sekali, jadi siapapun yang
     * login bisa lihat data prodi manapun (termasuk lewat pencarian bebas
     * nama_prodi). Lihat SaptoUserContext::fromRequest().
     */
    public function detail_mahasiswa_asing(Request $request)
    {
        $context = SaptoUserContext::fromRequest($request);
        $idProdi = $context->resolveProgramStudiId($request);
        $fakultasId = $context->resolveFakultasId($request);
        $tahun   = $request->query('tahun');

        $query = "
            SELECT
                lrpm.nim,
                lrpm.nama_mahasiswa AS nama,
                ps.nama_jenjang_pendidikan AS jenjang,
                ps.nama_program_studi AS nama_prodi,
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

        if ($idProdi !== null) {
            $query .= " AND lrpm.id_prodi = ? ";
            $params[] = $idProdi;
        } elseif ($fakultasId !== null) {
            // Dekan tanpa prodi terpilih: semua prodi di fakultasnya saja.
            // Tanpa cabang ini $idProdi null berarti TANPA saringan -- dekan
            // mendapat daftar mahasiswa asing se-universitas.
            $prodiFakultas = app(\App\Services\SaptoProgramStudiRepository::class)->programStudis()
                ->where('fakultas_id', $fakultasId)
                ->pluck('id')
                ->all();

            if ($prodiFakultas === []) {
                return response()->json(['success' => true, 'total_mahasiswa' => 0, 'data' => []]);
            }

            $query .= ' AND lrpm.id_prodi IN ('.implode(',', array_fill(0, count($prodiFakultas), '?')).') ';
            array_push($params, ...$prodiFakultas);
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
        
        $data = array_map(function (object $row): object {
            // Label prodi bersih (tanpa "Profesi Profesi ..." / jenjang
            // placeholder), konsisten dengan tabel ringkasannya.
            $row->program_studi = \App\Services\SaptoProgramStudiRepository::composeLabel($row->jenjang ?? '', $row->nama_prodi ?? '');
            unset($row->jenjang, $row->nama_prodi);

            return $row;
        }, DB::select($query, $params));

        return response()->json([
            'success' => true,
            'total_mahasiswa' => count($data),
            'filter_digunakan' => [
                'id_prodi' => $idProdi,
                'tahun' => $tahun,
            ],
            'data' => $data
        ]);
    }
}
