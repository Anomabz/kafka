<?php

namespace App\Services;

/**
 * Tabel 8.f.4) Bagian-4 Buku ber-ISBN, Book Chapter yang dihasilkan
 * mahasiswa. Jenis sama dengan Tabel 3.b.7 Bagian-4 (SaptoBukuDtpsRepository,
 * id_jenis_publikasi 11-15: monograf, buku referensi, buku lainnya, book
 * chapter nasional/internasional). Lihat SaptoLuaranMahasiswaRepository.
 */
class SaptoBukuMahasiswaRepository extends SaptoLuaranMahasiswaRepository
{
    protected function partName(): string
    {
        return 'Buku ber-ISBN, Book Chapter';
    }

    protected function slug(): string
    {
        return 'buku';
    }

    protected function sourceTable(): string
    {
        return 'sister_detail_publikasi';
    }

    protected function filterJenis($query): void
    {
        $query->whereIn('id_jenis_publikasi', ['11', '12', '13', '14', '15']);
    }

    protected function keterangan(object $row): string
    {
        $parts = [trim((string) ($row->jenis_publikasi ?? '')) ?: 'Buku'];

        if (trim((string) ($row->isbn ?? '')) !== '') {
            $parts[] = 'ISBN '.trim((string) $row->isbn);
        }

        if (trim((string) ($row->penerbit ?? '')) !== '') {
            $parts[] = trim((string) $row->penerbit);
        }

        return implode(' · ', $parts);
    }
}
