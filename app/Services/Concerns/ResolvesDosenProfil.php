<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Profil dosen untuk tabel keluarga 3.a (Dosen Tetap 3.a.1, Dosen Tidak Tetap
 * 3.a.4): pendidikan magister/doktor + bidang keahlian, jabatan akademik, dan
 * sertifikat pendidik/kompetensi. Diekstrak dari SaptoDosenTetapRepository
 * supaya kedua tabel memakai aturan yang sama persis.
 *
 * id_dosen (feeder) diperlakukan sama dengan id_sdm (SISTER) -- terverifikasi
 * cocok untuk >99% dosen tetap.
 */
trait ResolvesDosenProfil
{
    /**
     * Pendidikan magister/doktor + bidang keahlian per dosen dari
     * riwayat_pendidikan_dosen. Sp-1 disetarakan ke level magister, Sp-2 ke
     * level doktor (konvensi BAN-PT untuk prodi kesehatan/profesi). Bila
     * seorang dosen punya lebih dari satu baris di level yang sama, ambil
     * yang tahun_lulus-nya paling baru. "Bidang Keahlian" adalah heuristik:
     * bidang studi dari jenjang pendidikan tertinggi yang tercatat (tidak ada
     * kolom bidang keahlian eksplisit di sumber).
     *
     * @param  array<int, string>  $dosenIds
     * @return array<string, array{magister: ?string, doktor: ?string, bidang_keahlian: ?string}>
     */
    private function pendidikanByDosen(array $dosenIds): array
    {
        if ($dosenIds === []) {
            return [];
        }
        $magisterJenjang = ['S2', 'S2 Terapan', 'Sp-1'];
        $doktorJenjang = ['S3', 'S3 Terapan', 'Sp-2'];
        $rankOf = fn (string $jenjang): int => match (true) {
            in_array($jenjang, $doktorJenjang, true) => 3,
            in_array($jenjang, $magisterJenjang, true) => 2,
            $jenjang === 'S1' => 1,
            default => 0,
        };
        $rows = DB::table('riwayat_pendidikan_dosen')
            ->select('id_dosen', 'nama_jenjang_pendidikan', 'nama_bidang_studi', 'nama_perguruan_tinggi', 'tahun_lulus')
            ->whereIn('id_dosen', $dosenIds)
            ->whereIn('nama_jenjang_pendidikan', [...$magisterJenjang, ...$doktorJenjang, 'S1'])
            ->orderBy('tahun_lulus', 'desc')
            ->get();
        $byDosen = [];
        foreach ($rows as $row) {
            $dosenId = (string) $row->id_dosen;
            $jenjang = (string) $row->nama_jenjang_pendidikan;
            // "*" adalah konvensi feeder PDDIKTI untuk bidang studi "tidak diisi".
            $bidangStudi = ($row->nama_bidang_studi && $row->nama_bidang_studi !== '*') ? (string) $row->nama_bidang_studi : null;
            $perguruanTinggi = trim((string) ($row->nama_perguruan_tinggi ?? ''));
            // Hindari "- - Universitas X" saat bidang studi kosong: tampilkan
            // hanya yang ada (bidang, PT, atau "-" jika dua-duanya kosong).
            $label = trim(implode(' - ', array_filter([$bidangStudi, $perguruanTinggi ?: null]))) ?: '-';
            $byDosen[$dosenId] ??= ['magister' => null, 'doktor' => null, 'bidang_keahlian' => null, 'rank' => 0];
            if (in_array($jenjang, $magisterJenjang, true) && $byDosen[$dosenId]['magister'] === null) {
                $byDosen[$dosenId]['magister'] = $label;
            }
            if (in_array($jenjang, $doktorJenjang, true) && $byDosen[$dosenId]['doktor'] === null) {
                $byDosen[$dosenId]['doktor'] = $label;
            }
            $rank = $rankOf($jenjang);
            if ($bidangStudi !== null && $rank > $byDosen[$dosenId]['rank']) {
                $byDosen[$dosenId]['bidang_keahlian'] = $bidangStudi;
                $byDosen[$dosenId]['rank'] = $rank;
            }
        }
        return $byDosen;
    }

    /**
     * Jabatan akademik terbaru yang berlaku sampai akhir TS.
     * id_dosen sama dengan id_sdm pada data SISTER.
     *
     * @param  array<int, string>  $dosenIds
     * @return array<string, string>
     */
    private function jabatanAkademikByDosen(array $dosenIds, int $ts): array
    {
        if ($dosenIds === []) {
            return [];
        }

        // Sesuaikan dengan tanggal acuan TS yang digunakan SAPTO.
        $tanggalAkhirTs = ($ts + 1).'-08-31';
        $academicRanks = ['Tenaga Pengajar', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Profesor', 'Guru Besar'];

        $rows = DB::table('sister_list_jabatan_fungsional')
            ->select('id_sdm', 'jabatan_fungsional', 'tanggal_mulai')
            ->whereIn('id_sdm', $dosenIds)
            ->whereIn('jabatan_fungsional', $academicRanks)
            ->whereNotNull('tanggal_mulai')
            ->where('tanggal_mulai', '<=', $tanggalAkhirTs)
            ->orderBy('tanggal_mulai', 'desc')
            ->get();

        $byDosen = [];

        foreach ($rows as $row) {
            $dosenId = (string) $row->id_sdm;

            if (isset($byDosen[$dosenId])) {
                continue;
            }

            $jabatan = trim((string) $row->jabatan_fungsional);
            $byDosen[$dosenId] = in_array($jabatan, ['Profesor', 'Guru Besar'], true)
                ? 'Guru Besar'
                : $jabatan;
        }

        return $byDosen;
    }

    /**
     * Sertifikat pendidik profesional (Serdos) + kompetensi/profesi terbaru
     * per dosen dari riwayat_sertifikasi_dosen. "Sertifikasi Asesor BKD"
     * sengaja tidak dipetakan ke kolom manapun — itu sertifikasi penilai beban
     * kerja dosen, konsepnya beda dari sertifikat pendidik/kompetensi.
     *
     * @param  array<int, string>  $dosenIds
     * @return array<string, array{pendidik: ?string, kompetensi: ?string}>
     */
    private function sertifikatByDosen(array $dosenIds): array
    {
        if ($dosenIds === []) {
            return [];
        }
        $pendidikJenis = ['Sertifikasi Dosen', 'Sertifikasi Dosen Kemenag'];
        $kompetensiJenis = ['Sertifikasi Kompetensi', 'Sertifikasi Profesi'];
        $rows = DB::table('riwayat_sertifikasi_dosen')
            ->select('id_dosen', 'nama_jenis_sertifikasi', 'tahun_sertifikasi')
            ->whereIn('id_dosen', $dosenIds)
            ->whereIn('nama_jenis_sertifikasi', [...$pendidikJenis, ...$kompetensiJenis])
            ->orderBy('tahun_sertifikasi', 'desc')
            ->get();
        $byDosen = [];
        foreach ($rows as $row) {
            $dosenId = (string) $row->id_dosen;
            $jenis = (string) $row->nama_jenis_sertifikasi;
            $label = $row->tahun_sertifikasi ? "{$jenis} ({$row->tahun_sertifikasi})" : $jenis;
            $byDosen[$dosenId] ??= ['pendidik' => null, 'kompetensi' => null];
            if (in_array($jenis, $pendidikJenis, true) && $byDosen[$dosenId]['pendidik'] === null) {
                $byDosen[$dosenId]['pendidik'] = $label;
            }
            if (in_array($jenis, $kompetensiJenis, true) && $byDosen[$dosenId]['kompetensi'] === null) {
                $byDosen[$dosenId]['kompetensi'] = $label;
            }
        }
        return $byDosen;
    }

    /**
     * NIDN; bila kosong, NUPTK (dosen baru yang hanya punya NUPTK).
     */
    private function nidnAtauNuptk(mixed $nidn, mixed $nuptk): string
    {
        $nidn = trim((string) ($nidn ?? ''));

        return $nidn !== '' ? $nidn : trim((string) ($nuptk ?? ''));
    }
}