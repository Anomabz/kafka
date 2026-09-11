<?php

namespace Database\Seeders;

use App\Models\Penugasan;
use Illuminate\Database\Seeder;
use RuntimeException;

class KorprodiSeeder extends Seeder
{
    /**
     * Mengisi roster korprodi awal dari CSV kampus ke tabel `penugasan`.
     *
     * Hanya untuk instalasi baru: begitu tabel sudah berisi korprodi, seeder
     * ini tidak menyentuh apa pun. Sejak ada /admin/peran, admin yang jadi
     * sumber kebenaran -- menjalankan seeder lagi tidak boleh menghidupkan
     * kembali akses yang sudah sengaja dicabut admin.
     */
    public function run(): void
    {
        if (Penugasan::query()->where('peran', 'korprodi')->exists()) {
            $this->command?->info('Penugasan korprodi sudah terisi; KorprodiSeeder dilewati.');

            return;
        }

        $path = database_path('data/korprodi.csv');

        if (! is_file($path)) {
            throw new RuntimeException("Data korprodi tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Data korprodi tidak dapat dibaca: {$path}");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return;
        }

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, $row);

            if (! is_array($record) || empty($record['nip']) || empty($record['kode_program_studi'])) {
                continue;
            }

            Penugasan::query()->firstOrCreate(
                [
                    'peran' => 'korprodi',
                    'nip' => trim($record['nip']),
                    'lingkup' => trim($record['kode_program_studi']),
                ],
                [
                    'nama' => trim($record['nama'] ?? ''),
                    'dibuat_oleh' => 'seeder korprodi.csv',
                ],
            );
        }

        fclose($handle);
    }
}
