<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ProgramStudiSeeder is disabled: it expects a `program_studi` table
        // with (kode, fakultas, nama, jenjang) columns, but that table now
        // holds the Feeder-mirror schema (id_prodi, kode_program_studi, ...)
        // used live by MahasiswaController::data_mahasiswa_asing. Nothing
        // currently reads App\Models\ProgramStudi, so this is safe to skip
        // until that table is renamed to something collision-free.
        // $this->call(ProgramStudiSeeder::class);

        $this->call(KorprodiSeeder::class);
    }
}
