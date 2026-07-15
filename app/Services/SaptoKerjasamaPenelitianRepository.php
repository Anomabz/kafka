<?php

namespace App\Services;

/**
 * Tabel 1 Kerjasama Tridharma - Penelitian. Lihat SaptoKerjasamaRepository
 * untuk logika bersama (join daftar_mou + daftar_pks, klasifikasi Tingkat,
 * kolom Manfaat bagi PS yang selalu kosong, dst.).
 */
class SaptoKerjasamaPenelitianRepository extends SaptoKerjasamaRepository
{
    protected function tableCode(): string
    {
        return '1';
    }

    protected function tableName(): string
    {
        return 'Kerjasama Tridharma - Penelitian';
    }

    protected function bidangLabel(): string
    {
        return 'penelitian';
    }

    protected function cachePrefix(): string
    {
        return 'kerjasama-penelitian';
    }

    protected function mouBidangPatterns(): array
    {
        return ['%enelitian%', '%riset%'];
    }

    protected function pksBidangKeywords(): array
    {
        return [
            'penelitian', 'riset', 'kajian', 'publikasi', 'jurnal',
            'bantuan penelitian', 'inovasi produk', 'pengembangan produk',
            'purwarupa', 'prototipe', 'paten', 'research',
        ];
    }
}
