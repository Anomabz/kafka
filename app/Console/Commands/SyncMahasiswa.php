<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\ListMahasiswa;

class SyncMahasiswa extends Command
{
    // Ini perintah yang akan diketik di terminal nanti
    protected $signature = 'sync:mahasiswa';
    protected $description = 'Ambil data mahasiswa dari API Pangkalan Data dan simpan ke MySQL lokal';

    public function handle()
    {
        $this->info('Memulai sinkronisasi data mahasiswa...');

        // 1. Ambil URL API dari environment variabel docker-compose kamu
        $apiUrl = env('PANGKALAN_DATA_BASE_URL') . '/api/v1/mahasiswa'; // sesuaikan endpoint aslinya

        // 2. Tembak API (tambahkan bearer token jika API-nya butuh autentikasi)
        $response = Http::get($apiUrl);

        if ($response->failed()) {
            $this->error('Gagal mengambil data dari API!');
            return Command::FAILURE;
        }

        $mahasiswaList = $response->json(); // Asumsi API mengembalikan format Array JSON

        // 3. Looping data dan simpan ke database lokal
        foreach ($mahasiswaList as $data) {
            // updateOrCreate berfungsi: kalau ID sudah ada, dia UPDATE. Kalau belum ada, dia INSERT baru.
            ListMahasiswa::updateOrCreate(
                ['id_mahasiswa' => $data['id_mahasiswa']], // Kunci penentu keunikan data
                [
                    'nama_mahasiswa'          => $data['nama_mahasiswa'] ?? null,
                    'jenis_kelamin'           => $data['jenis_kelamin'] ?? null,
                    'tanggal_lahir'           => $data['tanggal_lahir'] ?? null,
                    'id_perguruan_tinggi'     => $data['id_perguruan_tinggi'] ?? null,
                    'nipd'                    => $data['nipd'] ?? null,
                    'ipk'                     => $data['ipk'] ?? null,
                    'total_sks'               => $data['total_sks'] ?? null,
                    'id_sms'                  => $data['id_sms'] ?? null,
                    'id_agama'                => $data['id_agama'] ?? null,
                    'nama_agama'              => $data['nama_agama'] ?? null,
                    'id_prodi'                => $data['id_prodi'] ?? null,
                    'nama_program_studi'      => $data['nama_program_studi'] ?? null,
                    'id_status_mahasiswa'     => $data['id_status_mahasiswa'] ?? null,
                    'nama_status_mahasiswa'   => $data['nama_status_mahasiswa'] ?? null,
                    'nim'                     => $data['nim'] ?? null,
                    'id_periode'              => $data['id_periode'] ?? null,
                    'nama_periode_masuk'      => $data['nama_periode_masuk'] ?? null,
                    'id_registrasi_mahasiswa' => $data['id_registrasi_mahasiswa'] ?? null,
                    'id_periode_keluar'       => $data['id_periode_keluar'] ?? null,
                    'tanggal_keluar'          => $data['tanggal_keluar'] ?? null,
                    'last_update'             => $data['last_update'] ?? null,
                    'tgl_create'              => $data['tgl_create'] ?? null,
                    'status_sync'             => 'sukses',
                ]
            );
        }

        $this->info('Sinkronisasi sukses! Semua data tersimpan di MySQL.');
        return Command::SUCCESS;
    }
}
