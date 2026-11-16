<?php

namespace App\Http\Controllers\Api\Sapto;

/**
 * Tabel 8.f.2) Karya Ilmiah Mahasiswa yang Disitasi.
 * Placeholder — data sitasi belum tersedia.
 */
class SitasiMahasiswaController extends SaptoPlaceholderController
{
    protected string $tableCode = '8.f.2';
    protected string $tableName = 'Karya Ilmiah Mahasiswa yang Disitasi';
    protected string $unit = 'artikel disitasi';
    protected string $notice = 'Data sitasi karya ilmiah mahasiswa belum tersedia. Kolom Nama Mahasiswa, Judul Artikel, dan Jumlah Sitasi perlu diisi manual.';

    protected function buildColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'align' => 'left'],
            ['key' => 'nama_mahasiswa', 'label' => 'Nama Mahasiswa', 'align' => 'left'],
            ['key' => 'judul_artikel', 'label' => 'Judul Artikel yang Disitasi', 'align' => 'left'],
            ['key' => 'jumlah_sitasi', 'label' => 'Jumlah Sitasi', 'align' => 'num'],
        ];
    }

    protected function buildRows(int $ts): array
    {
        return [];
    }
}
