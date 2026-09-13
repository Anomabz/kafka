<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Dijalankan setelah batch sinkronisasi PD UNSRI selesai (lihat
 * PdUnsriSyncDispatcher). Ringkasan laporan SAPTO di-cache 24 jam dengan
 * Cache::remember, dan `sapto:warm` hanya mengisi key yang kosong -- tanpa
 * cache:clear dulu, dashboard tetap menampilkan angka sebelum sinkronisasi
 * sampai TTL habis.
 *
 * cache:clear aman: session memakai driver file terpisah (tidak ada yang
 * ter-logout), token API PD UNSRI tinggal diambil ulang saat dibutuhkan.
 */
class RefreshSaptoCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function handle(): void
    {
        Artisan::call('cache:clear');
        Artisan::call('sapto:warm');
        Artisan::call('sapto:warm', ['--prodi' => true]);

        Log::info('PD UNSRI sync: cache laporan SAPTO dihitung ulang.');
    }
}
