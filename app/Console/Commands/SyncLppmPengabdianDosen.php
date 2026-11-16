<?php

namespace App\Console\Commands;

use App\Services\PangkalanDataApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:sync-lppm-pengabdian-dosen')]
#[Description('Sinkronisasi data pengabdian dosen dari Pangkalan Data')]
class SyncLppmPengabdianDosen extends Command
{
    public function handle(PangkalanDataApiClient $client): int
    {
        $limit = 1000;
        $offset = 0;
        $rows = [];

        try {
            do {
                $response = $client->getRaw('api/v1/pengabdian-dosen', [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

                if ($response->failed()) {
                    $this->error("API gagal dengan HTTP {$response->status()}.");

                    return self::FAILURE;
                }

                $payload = $response->json();
                $data = $payload['data'] ?? [];

                if (! is_array($data)) {
                    $this->error('Format respons API tidak valid: properti data bukan array.');

                    return self::FAILURE;
                }

                foreach ($data as $pengabdian) {
                    if (! is_array($pengabdian)) {
                        continue;
                    }

                    $anggota = $pengabdian['anggota_non_dosen'] ?? [];

                    $rows[] = [
                        'id_ketua' => trim((string) ($pengabdian['id_ketua'] ?? '')),
                        'nama_skema' => trim((string) ($pengabdian['nama_skema'] ?? '')),
                        'judul' => trim((string) ($pengabdian['judul'] ?? '')),
                        'tahun' => (int) ($pengabdian['tahun'] ?? 0),
                        'anggota_non_dosen' => json_encode(
                            is_array($anggota) ? $anggota : [],
                            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                        ),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $received = count($data);
                $offset += $received;
                $this->info("Mengambil data: offset {$offset}, diterima {$received} data.");
            } while ($received > 0);

            if ($rows === []) {
                $this->warn('API tidak mengembalikan data pengabdian; tabel tidak diubah.');

                return self::SUCCESS;
            }

            DB::transaction(function () use ($rows): void {
                DB::table('lppm_pengabdian_dosen')->delete();

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('lppm_pengabdian_dosen')->insert($chunk);
                }
            });

            $this->info('Sinkronisasi selesai: '.count($rows).' data pengabdian disimpan.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Sinkronisasi gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
