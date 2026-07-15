<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Kampus proxy di depan sapto.unsri.ac.id terminate TLS tapi TIDAK
        // mengirim header X-Forwarded-Proto (dicek langsung — nilainya null),
        // jadi Laravel mengira tiap request itu HTTP dan generate URL
        // aset/redirect http:// di halaman yang sebenarnya https:// — browser
        // blokir itu sebagai mixed content, tampilan jadi rusak (CSS/JS gagal
        // dimuat). Domain produksi ini SELALU diakses lewat https, jadi paksa
        // skemanya di sini alih-alih bergantung pada header yang tidak pernah
        // dikirim proxy-nya.
        if (request()->getHost() === 'sapto.unsri.ac.id') {
            URL::forceScheme('https');
        }
    }
}
