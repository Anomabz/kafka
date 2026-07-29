<?php

namespace App\Services;

/**
 * Tabel 8.b.1) Prestasi Akademik Mahasiswa. Seluruh perhitungan ada di
 * {@see SaptoPrestasiRepository}; di sini hanya metadata dan filter jenis
 * prestasi ("Sains" -- lomba esai/riset/debat ilmiah, lihat docblock basis
 * untuk alasan kenapa "Lain-lain" TIDAK ikut di sini meski sebagian isinya
 * juga karya tulis ilmiah).
 */
class SaptoPrestasiAkademikRepository extends SaptoPrestasiRepository
{
    protected function tableCode(): string
    {
        return '8.b.1';
    }

    protected function tableName(): string
    {
        return 'Prestasi Akademik Mahasiswa';
    }

    protected function jenisPrestasi(): array
    {
        return ['Sains'];
    }

    protected function cachePrefix(): string
    {
        return 'prestasi-akademik';
    }
}
