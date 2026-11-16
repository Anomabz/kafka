<?php

namespace App\Console\Commands;

use App\Services\Kafka\KafkaRestProxy;
use Illuminate\Console\Command;

/**
 * Membuat topic SAPTO di Kafka secara eksplisit -- lihat config/kafka.php dan
 * docs/kafka.md. Idempoten: aman dijalankan berulang (dijalankan otomatis
 * tiap container app naik, docker/entrypoint.sh). Topic yang sudah ada tidak
 * dibuat ulang, hanya retensinya disamakan dengan config.
 *
 * Kenapa tidak membiarkan broker membuat topic otomatis (auto.create.topics):
 * topic yang salah ketik ikut terbentuk diam-diam dengan setelan bawaan, dan
 * kita tidak punya tempat menyatakan partisi/retensi yang dimaksud.
 */
class KafkaSetup extends Command
{
    protected $signature = 'sapto:kafka-setup {--wait=0 : Tunggu sampai REST Proxy siap, maksimal sekian detik}';

    protected $description = 'Buat topic Kafka SAPTO yang belum ada dan samakan retensinya dengan config/kafka.php';

    public function handle(KafkaRestProxy $proxy): int
    {
        if (! config('kafka.enabled')) {
            $this->warn('KAFKA_ENABLED=false: setup dilewati.');

            return self::SUCCESS;
        }

        // Saat container baru naik, REST Proxy dan broker sering belum siap
        // beberapa puluh detik; menunggu di sini lebih baik daripada gagal
        // lalu tidak ada yang mengulang.
        $deadline = time() + (int) $this->option('wait');
        while (true) {
            try {
                $brokers = $proxy->brokerCount();
                break;
            } catch (\Throwable $e) {
                if (time() >= $deadline) {
                    $this->error('REST Proxy/Kafka belum bisa dihubungi: '.$e->getMessage());

                    return self::FAILURE;
                }
                sleep(3);
            }
        }

        $this->line("Terhubung ke Kafka lewat {$proxy->baseUrl()} ({$brokers} broker).");

        foreach (config('kafka.topics') as $name => $topic) {
            $retentionMs = (string) ((int) $topic['retention_days'] * 86_400_000);

            if ($proxy->topic($name) === null) {
                // Replication factor mengikuti jumlah broker (maks 3): saat ini
                // satu broker, jadi 1; naik sendiri kalau broker ditambah.
                $proxy->createTopic($name, (int) $topic['partitions'], max(1, min($brokers, 3)), ['retention.ms' => $retentionMs]);
                $this->info("  dibuat   {$name} ({$topic['partitions']} partisi, retensi {$topic['retention_days']} hari)");

                continue;
            }

            if (($proxy->topicConfigs($name)['retention.ms'] ?? null) !== $retentionMs) {
                $proxy->alterTopicConfigs($name, ['retention.ms' => $retentionMs]);
                $this->info("  diubah   {$name}: retensi {$topic['retention_days']} hari");

                continue;
            }

            $this->line("  sudah ada {$name}");
        }

        return self::SUCCESS;
    }
}
