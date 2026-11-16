<?php

namespace App\Services;

/**
 * Tabel 8.f.4) Bagian-1 HKI (Paten, Paten Sederhana) yang dihasilkan
 * mahasiswa. Jenis sama dengan Tabel 3.b.7 Bagian-1 (id_jenis_publikasi
 * 41 paten nasional, 42 paten internasional). Lihat SaptoLuaranMahasiswaRepository.
 */
class SaptoHkiPatenMahasiswaRepository extends SaptoLuaranMahasiswaRepository
{
    protected function partName(): string
    {
        return 'HKI (Paten, Paten Sederhana)';
    }

    protected function slug(): string
    {
        return 'paten';
    }

    protected function sourceTable(): string
    {
        return 'sister_detail_kekayaan_intelektual';
    }

    protected function filterJenis($query): void
    {
        $query->whereIn('id_jenis_publikasi', ['41', '42']);
    }

    protected function keterangan(object $row): string
    {
        $parts = [(string) $row->id_jenis_publikasi === '42' ? 'Paten internasional' : 'Paten nasional'];

        if (trim((string) ($row->nomor_paten ?? '')) !== '') {
            $parts[] = 'No. '.trim((string) $row->nomor_paten);
        }

        if (trim((string) ($row->pemberi_paten ?? '')) !== '') {
            $parts[] = trim((string) $row->pemberi_paten);
        }

        return implode(' · ', $parts);
    }
}
