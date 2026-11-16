<?php

namespace App\Services;

/**
 * Tabel 8.b.2) Prestasi Non-akademik Mahasiswa. Seluruh perhitungan dan
 * aturan klasifikasi akademik/non-akademik ada di {@see SaptoPrestasiRepository}.
 */
class SaptoPrestasiNonAkademikRepository extends SaptoPrestasiRepository
{
    protected function tableCode(): string
    {
        return '8.b.2';
    }

    protected function tableName(): string
    {
        return 'Prestasi Non-akademik Mahasiswa';
    }

    protected function kategori(): string
    {
        return self::NON_AKADEMIK;
    }
}