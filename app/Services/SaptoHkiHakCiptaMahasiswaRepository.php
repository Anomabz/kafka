<?php

namespace App\Services;

/**
 * Tabel 8.f.4) Bagian-2 HKI (Hak Cipta, Desain Produk Industri, dll.) yang
 * dihasilkan mahasiswa. Jenis sama dengan Tabel 3.b.7 Bagian-2
 * (id_jenis_publikasi 43 hak cipta nasional, 44 internasional). Lihat
 * SaptoLuaranMahasiswaRepository.
 */
class SaptoHkiHakCiptaMahasiswaRepository extends SaptoLuaranMahasiswaRepository
{
    protected function partName(): string
    {
        return 'HKI (Hak Cipta, Desain Produk Industri, dll.)';
    }

    protected function slug(): string
    {
        return 'hak-cipta';
    }

    protected function sourceTable(): string
    {
        return 'sister_detail_kekayaan_intelektual';
    }

    protected function filterJenis($query): void
    {
        $query->whereIn('id_jenis_publikasi', ['43', '44']);
    }

    protected function keterangan(object $row): string
    {
        $parts = [(string) $row->id_jenis_publikasi === '44' ? 'Hak cipta internasional' : 'Hak cipta nasional'];

        if (trim((string) ($row->nomor_paten ?? '')) !== '') {
            $parts[] = 'No. '.trim((string) $row->nomor_paten);
        }

        return implode(' · ', $parts);
    }
}
