<?php

namespace App\Services;

/**
 * Tabel 8.b.2) Prestasi Non-akademik Mahasiswa. Seluruh perhitungan ada di
 * {@see SaptoPrestasiRepository}; di sini hanya metadata dan filter jenis
 * prestasi: "Olahraga" & "Seni" (jelas non-akademik), plus "Lain-lain"
 * sebagai kategori residual -- klaim akademik harus eksplisit "Sains" (lihat
 * SaptoPrestasiAkademikRepository), jadi selain itu dianggap non-akademik
 * meski sample manual menunjukkan "Lain-lain" isinya campur (mis. ada Karya
 * Tulis Ilmiah berdampingan dengan Finalis Bujang Gadis) -- perlu
 * diverifikasi manual per baris sebelum dipakai di dokumen akreditasi resmi.
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

    protected function jenisPrestasi(): array
    {
        return ['Olahraga', 'Seni', 'Lain-lain'];
    }

    protected function cachePrefix(): string
    {
        return 'prestasi-non-akademik';
    }
}
