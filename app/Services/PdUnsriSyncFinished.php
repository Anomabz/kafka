<?php

namespace App\Services;

use App\Jobs\RefreshSaptoCacheJob;
use Illuminate\Bus\Batch;

/**
 * Callback `finally` batch sinkronisasi PD UNSRI (lihat
 * PdUnsriSyncDispatcher::dispatch). Kelas invokable tanpa state, bukan
 * closure: callback batch diserialisasi ke tabel job_batches, dan closure di
 * dalam method instance ikut membawa `$this` ke dalam serialisasi. Objek ini
 * pasti aman diserialisasi dan bisa dites langsung.
 */
class PdUnsriSyncFinished
{
    public function __invoke(Batch $batch): void
    {
        RefreshSaptoCacheJob::dispatch();
        PdUnsriSyncDispatcher::announceBatchFinished($batch);
    }
}
