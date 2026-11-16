<?php

namespace App\Services;

/**
 * Tabel 8.b.1) Prestasi Akademik Mahasiswa. Seluruh perhitungan dan aturan
 * klasifikasi akademik/non-akademik ada di {@see SaptoPrestasiRepository}.
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

    protected function kategori(): string
    {
        return self::AKADEMIK;
    }
}