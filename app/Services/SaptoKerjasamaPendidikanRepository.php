<?php

namespace App\Services;

/**
 * Tabel 1 Kerjasama Tridharma - Pendidikan. Lihat SaptoKerjasamaRepository
 * untuk logika bersama (join daftar_mou + daftar_pks, klasifikasi Tingkat,
 * kolom Manfaat bagi PS yang selalu kosong, dst.).
 */
class SaptoKerjasamaPendidikanRepository extends SaptoKerjasamaRepository
{
    protected function tableCode(): string
    {
        return '1';
    }

    protected function tableName(): string
    {
        return 'Kerjasama Tridharma - Pendidikan';
    }

    protected function bidangLabel(): string
    {
        return 'pendidikan';
    }

    protected function cachePrefix(): string
    {
        return 'kerjasama-pendidikan';
    }

    protected function mouBidangPatterns(): array
    {
        // 'endidikan' (bukan 'pendidikan') supaya turut menangkap salah ketik
        // "Pendidian" yang ditemukan di data sumber.
        return ['%endidikan%', '%endidian%'];
    }

    protected function pksBidangKeywords(): array
    {
        return [
            'pendidikan', 'pelatihan', 'pembelajaran', 'kurikulum', 'pengajaran',
            'magang', 'praktik kerja', 'pkl', 'workshop', 'akademik', 'kuliah',
            'sertifikasi kompetensi', 'bimbingan', 'pengembangan sdm', 'beasiswa',
            'pertukaran mahasiswa', 'pertukaran dosen', 'dosen tamu', 'studi lanjut',
        ];
    }
}
