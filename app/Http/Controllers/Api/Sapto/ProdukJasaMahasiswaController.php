<?php

namespace App\Http\Controllers\Api\Sapto;

/**
 * Tabel 8.f.3) Produk/Jasa DTPS yang Dihasilkan Mahasiswa yang Diadopsi
 * oleh Industri/Masyarakat.
 * Placeholder — data belum tersedia.
 */
class ProdukJasaMahasiswaController extends SaptoPlaceholderController
{
    protected string $tableCode = '8.f.3';
    protected string $tableName = 'Produk/Jasa yang Dihasilkan Mahasiswa dan Diadopsi';
    protected string $unit = 'produk/jasa';
    protected string $notice = 'Data produk/jasa mahasiswa yang diadopsi industri/masyarakat belum tersedia. Kolom Nama Mahasiswa, Nama Produk/Jasa, Deskripsi, Bukti, dan Tahun perlu diisi manual.';

    protected function buildColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'align' => 'left'],
            ['key' => 'nama_mahasiswa', 'label' => 'Nama Mahasiswa', 'align' => 'left'],
            ['key' => 'nama_produk', 'label' => 'Nama Produk/Jasa', 'align' => 'left'],
            ['key' => 'deskripsi', 'label' => 'Deskripsi', 'align' => 'left'],
            ['key' => 'bukti', 'label' => 'Bukti', 'align' => 'left'],
            ['key' => 'tahun', 'label' => 'Tahun', 'align' => 'left'],
        ];
    }

    protected function buildRows(int $ts): array
    {
        return [];
    }
}
