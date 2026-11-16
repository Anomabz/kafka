<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Services\PangkalanDataApiClient;
use Illuminate\Support\Facades\DB;

#[Signature('app:sync-lppm-penelitian-dosen')]
#[Description('Command description')]
class SyncLppmPenelitianDosen extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PangkalanDataApiClient $client): int
    {
        $limit = 1000;
        $offset = 0;
        $rows = [];

        try {
            do {
                $response = $client->getRaw('api/v1/penelitian-dosen', [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

                if ($response->failed()) {
                    $this->error("API gagal dengan HTTP {$response->status()}.");

                    return self::FAILURE;
                }

                $payload = $response->json();
                $data = $payload['data'] ?? [];

                foreach ($data as $penelitian) {
                    $idKetua = trim((string) ($penelitian['id_ketua'] ?? ''));
                    $namaSkema = trim((string) ($penelitian['nama_skema'] ?? ''));
                    $judul = trim((string) ($penelitian['judul'] ?? ''));
                    $tahun = (int) ($penelitian['tahun'] ?? 0);

                    $anggotaNonDosen = $penelitian['anggota_non_dosen'] ?? [];

                    $rows[] = [
                        'id_ketua' => $idKetua,
                        'nama_skema' => $namaSkema,
                        'judul' => $judul,
                        'tahun' => $tahun,
                        'anggota_non_dosen' => json_encode(
                            $anggotaNonDosen,
                            JSON_UNESCAPED_UNICODE
                        ),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $jumlahDiterima = count($data);
                $offset += $jumlahDiterima;

                $this->info(
                    "Mengambil data: offset {$offset}, diterima {$jumlahDiterima} data."
                );

            } while ($jumlahDiterima > 0);

            if ($rows === []) {
                $this->warn('API tidak mengembalikan data penelitian; tabel tidak diubah.');

                return self::SUCCESS;
            }

            DB::transaction(function () use ($rows): void {
                DB::table('lppm_penelitian_dosen')->delete();

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('lppm_penelitian_dosen')->insert($chunk);
                }
            });

            $this->info(
                'Sinkronisasi selesai: ' . count($rows) . ' penelitian disimpan.'
            );

            return self::SUCCESS;

        } catch (\Throwable $exception) {
            report($exception);

            $this->error(
                'Sinkronisasi gagal: ' . $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
