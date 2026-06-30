<?php

namespace Database\Seeders;

use App\Models\ProgramStudi;
use Illuminate\Database\Seeder;
use RuntimeException;

class ProgramStudiSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/program_studi.csv');

        if (! is_file($path)) {
            throw new RuntimeException("Data program studi tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Data program studi tidak dapat dibaca: {$path}");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return;
        }

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, $row);

            if (! is_array($record) || empty($record['kode'])) {
                continue;
            }

            ProgramStudi::query()->updateOrCreate(
                ['kode' => trim($record['kode'])],
                [
                    'fakultas' => trim($record['fakultas'] ?? ''),
                    'nama' => trim($record['nama'] ?? ''),
                    'jenjang' => trim($record['jenjang'] ?? ''),
                ],
            );
        }

        fclose($handle);
    }
}
