<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Minggu dini hari -- di luar jam sibuk kampus, dan cukup jauh dari jam
// warm-cache subuh (lihat docker/entrypoint.sh) supaya tidak berebut koneksi
// ke Gravitee/API kampus.
// Zona waktu eksplisit: app.timezone = UTC, tanpa ini jadwalnya jatuh Minggu
// 09:00 WIB. Samakan dengan PdUnsriSyncDispatcher::SCHEDULE_CRON/_TIMEZONE.
Schedule::command('sapto:sync-pd-unsri')->weeklyOn(0, '02:00')->timezone('Asia/Jakarta');

// Badge status API PD UNSRI (semua peran) membaca hasil ini dari cache, jadi
// probe ke Gravitee/API kampus cukup sekali per menit, bukan per pengguna.
Schedule::call(fn () => app(\App\Services\PdUnsriHealthCheck::class)->refresh())
    ->everyMinute()
    ->name('pd-unsri-health')
    ->withoutOverlapping();

// Event Kafka yang tertahan (proxy/broker sempat mati) dikirim ulang tiap
// menit -- lihat KafkaProducerService dan docs/kafka.md.
Schedule::command('sapto:kafka-flush-outbox')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
