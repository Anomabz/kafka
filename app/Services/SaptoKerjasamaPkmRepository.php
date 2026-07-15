<?php

namespace App\Services;

/**
 * Tabel 1 Kerjasama Tridharma - Pengabdian kepada Masyarakat. Lihat
 * SaptoKerjasamaRepository untuk logika bersama (join daftar_mou + daftar_pks,
 * klasifikasi Tingkat, kolom Manfaat bagi PS yang selalu kosong, dst.).
 */
class SaptoKerjasamaPkmRepository extends SaptoKerjasamaRepository
{
    protected function tableCode(): string
    {
        return '1';
    }

    protected function tableName(): string
    {
        return 'Kerjasama Tridharma - Pengabdian kepada Masyarakat';
    }

    protected function bidangLabel(): string
    {
        return 'PkM';
    }

    protected function cachePrefix(): string
    {
        return 'kerjasama-pkm';
    }

    protected function mouBidangPatterns(): array
    {
        return ['%engabdian%'];
    }

    protected function pksBidangKeywords(): array
    {
        return [
            'pengabdian', 'pendampingan', 'konsultasi', 'konsultan', 'pemberdayaan',
            'penyuluhan', 'sosialisasi', 'pelayanan kesehatan', 'bakti sosial',
            'donasi', 'csr', 'tanggung jawab sosial',
        ];
    }
}
