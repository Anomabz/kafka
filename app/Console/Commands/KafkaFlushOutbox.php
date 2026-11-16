<?php

namespace App\Console\Commands;

use App\Models\KafkaOutbox;
use App\Services\KafkaProducerService;
use Illuminate\Console\Command;

/**
 * Mengirim ulang event yang sebelumnya gagal masuk ke Kafka (outbox) --
 * dijadwalkan tiap menit di routes/console.php. Lihat KafkaProducerService
 * untuk alasan outbox hanya ditulis saat gagal.
 *
 * event_id tetap sama di setiap percobaan, jadi kalau ternyata percobaan
 * sebelumnya sebenarnya berhasil (proxy mati SESUDAH menulis, sebelum
 * menjawab), consumer menyaring duplikatnya.
 */
class KafkaFlushOutbox extends Command
{
    protected $signature = 'sapto:kafka-flush-outbox
        {--dead : Kirim ulang juga event yang percobaannya sudah habis}
        {--limit=200 : Maksimal event per putaran}';

    protected $description = 'Kirim ulang event Kafka yang tertahan di outbox';

    public function handle(KafkaProducerService $producer): int
    {
        if (! config('kafka.enabled')) {
            return self::SUCCESS;
        }

        $statuses = [KafkaOutbox::PENDING];
        if ($this->option('dead')) {
            $statuses[] = KafkaOutbox::DEAD;
        }

        $rows = KafkaOutbox::query()
            ->whereIn('status', $statuses)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $sent = $failed = 0;

        foreach ($rows as $row) {
            if ($producer->send($row->topic, $row->event_key, (array) $row->payload, fromOutbox: true)) {
                $row->update(['status' => KafkaOutbox::SENT, 'sent_at' => now(), 'last_error' => null]);
                $sent++;

                continue;
            }

            $attempts = $row->attempts + 1;
            $dead = $attempts >= (int) config('kafka.outbox.max_attempts', 60);

            // Jeda bertambah (1, 2, 4 ... menit, maksimum 30) supaya proxy yang
            // mati lama tidak dihujani percobaan dari ratusan event sekaligus.
            $row->update([
                'attempts' => $attempts,
                'status' => $dead ? KafkaOutbox::DEAD : KafkaOutbox::PENDING,
                'last_error' => 'Gagal dikirim ulang ke REST Proxy (lihat log aplikasi).',
                'next_attempt_at' => now()->addMinutes(min(30, 2 ** min($attempts, 5))),
            ]);
            $failed++;

            // Proxy jelas sedang bermasalah: berhenti, sisanya dicoba menit depan.
            break;
        }

        if ($sent + $failed > 0) {
            $this->line("Outbox Kafka: {$sent} terkirim, {$failed} gagal.");
        }

        return self::SUCCESS;
    }
}
