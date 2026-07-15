<?php

namespace Database\Seeders;

use App\Models\Korprodi;
use Illuminate\Database\Seeder;
use RuntimeException;

class KorprodiSeeder extends Seeder
{
    public function run(): void
    {
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

            Korprodi::query()->updateOrCreate(
                [
                    'nip' => trim($record['nip']),
                    'kode_program_studi' => trim($record['kode_program_studi']),
                ],
                [
                    'nama' => trim($record['nama'] ?? ''),
                    'confidence' => trim($record['confidence'] ?? '') ?: 'high',
                ],
            );
        }

        fclose($handle);
    }
}
