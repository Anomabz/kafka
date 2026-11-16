<?php

namespace App\Console\Commands;

use App\Services\Kafka\KafkaEventHandler;
use App\Services\Kafka\KafkaRestException;
use App\Services\Kafka\KafkaRestProxy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Consumer Kafka SAPTO: membaca semua topic SAPTO lewat REST Proxy dan
 * menyerahkan tiap event ke KafkaEventHandler (arsip ke kafka_event_log +
 * notifikasi admin). Berjalan terus di latar container app
 * (docker/entrypoint.sh, pola sama dengan queue worker) -- lihat docs/kafka.md.
 *
 * At-least-once: offset di-commit SETELAH seluruh batch tersimpan. Proses
 * yang mati di tengah membaca ulang beberapa event, dan handler menyaring
 * duplikatnya lewat event_id.
 *
 * Sengaja keluar sendiri setiap --max-runtime detik: loop di entrypoint
 * menyalakannya lagi, jadi memori dilepas dan kode baru terpakai tanpa
 * restart container (alasan yang sama dengan queue:work).
 */
class KafkaConsume extends Command
{
    public const HEARTBEAT_KEY = 'kafka:consumer:heartbeat';

    protected $signature = 'sapto:kafka-consume
        {--once : Satu kali poll lalu keluar (uji manual)}
        {--max-runtime=3600 : Keluar sendiri setelah sekian detik (0 = tanpa batas)}';

    protected $description = 'Baca event SAPTO dari Kafka dan simpan ke kafka_event_log';

    private int $processed = 0;

    public function handle(KafkaRestProxy $proxy, KafkaEventHandler $handler): int
    {
        if (! config('kafka.enabled')) {
            $this->warn('KAFKA_ENABLED=false: consumer tidak dijalankan.');

            return self::SUCCESS;
        }

        $group = (string) config('kafka.consumer.group');
        $instance = (string) config('kafka.consumer.instance');
        $topics = array_keys(config('kafka.topics'));
        $pollMs = (int) config('kafka.consumer.poll_timeout_ms', 1000);
        $maxBytes = (int) config('kafka.consumer.max_bytes', 1048576);
        $deadline = (int) $this->option('max-runtime') > 0 ? time() + (int) $this->option('max-runtime') : null;

        $ready = false;

        while (true) {
            try {
                if (! $ready) {
                    $this->register($proxy, $group, $instance, $topics);
                    $ready = true;
                }

                $records = $proxy->poll($group, $instance, $pollMs, $maxBytes);
                $stored = 0;
                $commit = [];

                foreach ($records as $record) {
                    if ($handler->handle($record) === 'stored') {
                        $stored++;
                    }

                    // Offset yang dikirim ke REST Proxy = offset record terakhir
                    // yang selesai diproses; proxy sendiri menambah 1.
                    $key = $record['topic'].'#'.$record['partition'];
                    $commit[$key] = ['topic' => $record['topic'], 'partition' => (int) $record['partition'], 'offset' => (int) $record['offset']];
                }

                if ($commit !== []) {
                    $proxy->commit($group, $instance, array_values($commit));
                    $this->processed += count($records);
                    Log::info('Kafka consumer: batch diproses.', ['record' => count($records), 'baru' => $stored]);
                }

                $this->heartbeat($instance, null);
            } catch (\Throwable $e) {
                $ready = false;
                $gone = $e instanceof KafkaRestException && $e->isConsumerGone();

                // Instance hilang (proxy restart/kedaluwarsa) hal biasa dan
                // cukup didaftarkan ulang; selain itu dicatat sebagai warning.
                if (! $gone) {
                    Log::warning('Kafka consumer: gagal, mencoba lagi.', ['error' => $e->getMessage()]);
                }

                $this->heartbeat($instance, $gone ? null : $e->getMessage());

                if ($this->option('once')) {
                    $this->error($e->getMessage());

                    return self::FAILURE;
                }

                sleep($gone ? 1 : 5);
            }

            if ($this->option('once') || ($deadline !== null && time() >= $deadline)) {
                break;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Instance lama dengan nama yang sama (dari proses yang mati mendadak)
     * dihapus dulu: kalau tidak, REST Proxy menolak nama yang sudah dipakai
     * dan instance lama menahan partisi sampai kedaluwarsa sendiri.
     *
     * @param  list<string>  $topics
     */
    private function register(KafkaRestProxy $proxy, string $group, string $instance, array $topics): void
    {
        try {
            $proxy->deleteConsumer($group, $instance);
        } catch (KafkaRestException) {
            // Tidak ada instance lama: lanjut.
        }

        $proxy->createConsumer($group, $instance);
        $proxy->subscribe($group, $instance, $topics);

        // Poll pertama hanya memicu penugasan partisi (selalu kosong); tanpa
        // ini poll berikutnya bisa lolos sebelum consumer benar-benar siap.
        $proxy->poll($group, $instance, 500, 1024);

        Log::info('Kafka consumer: terdaftar.', ['group' => $group, 'instance' => $instance, 'topics' => $topics]);
    }

    private function heartbeat(string $instance, ?string $error): void
    {
        try {
            Cache::put(self::HEARTBEAT_KEY, [
                'at' => now()->toIso8601String(),
                'instance' => $instance,
                'processed' => $this->processed,
                'error' => $error,
            ], 3600);
        } catch (\Throwable) {
            // Hanya untuk halaman monitor.
        }
    }
}
