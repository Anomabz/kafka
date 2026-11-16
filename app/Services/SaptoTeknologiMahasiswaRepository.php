<?php

namespace App\Services;

/**
 * Tabel 8.f.4) Bagian-3 Teknologi Tepat Guna, Produk, Karya Seni, Rekayasa
 * Sosial yang dihasilkan mahasiswa. Jenis sama dengan Tabel 3.b.7 Bagian-3
 * (SaptoTeknologiDtpsRepository): kategori capaian luaran "Teknologi Tepat
 * Guna" (1) atau id_jenis_publikasi 29 (penemuan teknologi) dan 51-55
 * (karya seni/desain), kecuali paten/hak cipta (41-44) yang sudah dihitung
 * di Bagian-1/Bagian-2. Lihat SaptoLuaranMahasiswaRepository.
 */
class SaptoTeknologiMahasiswaRepository extends SaptoLuaranMahasiswaRepository
{
    protected function partName(): string
    {
        return 'Teknologi Tepat Guna, Produk, Karya Seni, Rekayasa Sosial';
    }

    protected function slug(): string
    {
        return 'teknologi';
    }

    protected function sourceTable(): string
    {
        return 'sister_detail_kekayaan_intelektual';
    }

    protected function filterJenis($query): void
    {
        $query->where(function ($q): void {
            $q->where('id_kategori_capaian_luaran', '1')
                ->orWhereIn('id_jenis_publikasi', ['51', '52', '53', '54', '55', '29']);
        })
            // Paten/hak cipta sudah masuk Bagian-1/Bagian-2: jangan dihitung dua kali
            // walau kategorinya "Teknologi Tepat Guna".
            ->whereNotIn('id_jenis_publikasi', ['41', '42', '43', '44']);
    }

    protected function keterangan(object $row): string
    {
        return trim((string) ($row->jenis_publikasi ?? '')) ?: (trim((string) ($row->kategori_capaian_luaran ?? '')) ?: '-');
    }
}
