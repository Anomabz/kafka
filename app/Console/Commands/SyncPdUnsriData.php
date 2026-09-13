<?php

namespace App\Console\Commands;

use App\Services\PdUnsriSyncDispatcher;
use Illuminate\Console\Command;

/**
 * Sinkronisasi mingguan seluruh tabel data akademik dari PD UNSRI (lewat
 * Gravitee) -- lihat config/pd_unsri_sync_tables.php untuk daftar tabelnya dan
 * SyncPdUnsriTableJob untuk mekanisme staging+swap per tabel.
 *
 * Command ini cuma memulai batch job lalu keluar; pekerjaan sebenarnya
 * jalan di queue worker (lihat docker/entrypoint.sh). Dijadwalkan mingguan
 * di routes/console.php, dan tombol admin "Sinkronisasi Sekarang" memanggil
 * dispatcher yang sama lewat PdUnsriSyncController.
 */
class SyncPdUnsriData extends Command
{
    protected $signature = 'sapto:sync-pd-unsri {--only= : Tarik tabel tertentu saja, dipisah koma (mis. list_kurikulum,detail_kurikulum)}';

    protected $description = 'Mulai sinkronisasi mingguan seluruh tabel data akademik dari PD UNSRI';

    public function handle(PdUnsriSyncDispatcher $dispatcher): int
    {
        if ($dispatcher->isRunning()) {
            $this->warn('Sinkronisasi PD UNSRI sebelumnya masih berjalan, dilewati.');

            return self::SUCCESS;
        }

        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));
        $batch = $dispatcher->dispatch($only === [] ? 'jadwal' : 'manual (--only)', $only);

        $this->info("Sinkronisasi PD UNSRI dimulai: batch {$batch->id}, {$batch->totalJobs} tabel.");

        return self::SUCCESS;
    }
}
