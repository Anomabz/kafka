<?php

namespace App\Http\Controllers\Api\Sapto;

/**
 * Tabel 5.b) Integrasi Kegiatan Penelitian/PkM dalam Pembelajaran.
 * Placeholder — data belum tersedia.
 */
class IntegrasiPembelajaranController extends SaptoPlaceholderController
{
    protected string $tableCode = '5.b';
    protected string $tableName = 'Integrasi Kegiatan Penelitian/PkM dalam Pembelajaran';
    protected string $unit = 'integrasi';
    protected string $notice = 'Data integrasi penelitian/PkM dalam pembelajaran belum tersedia. Kolom Judul Penelitian/PkM, Nama Dosen, Mata Kuliah, Bentuk Integrasi, dan Tahun perlu diisi manual.';

    protected function buildColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'align' => 'left'],
            ['key' => 'judul_penelitian', 'label' => 'Judul Penelitian/PkM', 'align' => 'left'],
            ['key' => 'nama_dosen', 'label' => 'Nama Dosen', 'align' => 'left'],
            ['key' => 'mata_kuliah', 'label' => 'Mata Kuliah', 'align' => 'left'],
            ['key' => 'bentuk_integrasi', 'label' => 'Bentuk Integrasi', 'align' => 'left'],
            ['key' => 'tahun', 'label' => 'Tahun (YYYY)', 'align' => 'left'],
        ];
    }

    protected function buildRows(int $ts): array
    {
        return [];
    }
}
