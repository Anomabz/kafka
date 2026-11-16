<?php

namespace App\Services\Kafka;

use App\Models\KafkaEventLog;
use App\Models\WebNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Apa yang dilakukan SAPTO dengan setiap event yang dibaca dari Kafka
 * (dipanggil `sapto:kafka-consume`). Dipisah dari loop consumer supaya bisa
 * dites tanpa REST Proxy.
 *
 * Idempoten: consumer bersifat at-least-once (offset di-commit SETELAH
 * event diproses, jadi proses yang mati di tengah akan membaca ulang
 * beberapa event). event_id yang sudah tercatat dilewati seluruhnya --
 * termasuk efek sampingnya (notifikasi tidak muncul dua kali).
 */
class KafkaEventHandler
{
    /**
     * @param  array{topic: string, key: mixed, value: mixed, partition: int, offset: int}  $record
     * @return 'stored'|'duplicate'|'invalid'
     */
    public function handle(array $record): string
    {
        $event = $record['value'] ?? null;

        // Pesan bukan amplop SAPTO (mis. dikirim manual lewat kafka-ui):
        // dicatat ke log lalu dilewati, jangan sampai menghentikan consumer
        // dan membuat semua event sesudahnya tertahan.
        if (! is_array($event) || ! is_string($event['event_id'] ?? null) || ! is_string($event['type'] ?? null)
            || ! preg_match('/^[0-9a-f-]{36}$/i', $event['event_id'])) {
            Log::warning('Kafka consumer: pesan tanpa amplop SAPTO dilewati.', [
                'topic' => $record['topic'] ?? null, 'partition' => $record['partition'] ?? null, 'offset' => $record['offset'] ?? null,
            ]);

            return 'invalid';
        }

        return DB::transaction(function () use ($record, $event): string {
            $inserted = KafkaEventLog::query()->insertOrIgnore([
                'event_id' => $event['event_id'],
                'topic' => (string) $record['topic'],
                'partition' => (int) ($record['partition'] ?? 0),
                'offset' => (int) ($record['offset'] ?? 0),
                'type' => mb_substr($event['type'], 0, 100),
                'event_key' => is_scalar($record['key'] ?? null) ? mb_substr((string) $record['key'], 0, 191) : null,
                'actor' => $this->actorLabel($event['actor'] ?? null),
                'payload' => json_encode($event, JSON_UNESCAPED_UNICODE),
                'occurred_at' => $this->time($event['occurred_at'] ?? null),
                'consumed_at' => now(),
            ]);

            if ($inserted === 0) {
                return 'duplicate';
            }

            if ($record['topic'] === 'sapto.sync.batch_finished') {
                $this->notifyAdmins((array) ($event['data'] ?? []));
            }

            return 'stored';
        });
    }

    /** Notifikasi web (mekanisme /notif yang sudah ada) khusus akun admin. */
    private function notifyAdmins(array $d): void
    {
        $bagian = [($d['done'] ?? 0).' tabel diperbarui'];
        if (! empty($d['skipped'])) {
            $bagian[] = $d['skipped'].' dilewati';
        }
        if (! empty($d['failed'])) {
            $bagian[] = $d['failed'].' gagal';
        }

        $status = ! empty($d['cancelled']) ? 'dibatalkan' : 'selesai';

        WebNotification::create([
            'message' => "Sinkronisasi PD UNSRI {$status}: ".implode(', ', $bagian).'. Detail di Admin → Sinkronisasi PD UNSRI.',
            'audience' => 'admin',
        ]);
    }

    private function actorLabel(mixed $actor): ?string
    {
        if (! is_array($actor)) {
            return null;
        }

        $label = $actor['email'] ?? $actor['nip'] ?? $actor['name'] ?? null;

        return $label === null ? null : mb_substr((string) $label, 0, 191);
    }

    private function time(mixed $iso): ?Carbon
    {
        try {
            return is_string($iso) ? Carbon::parse($iso)->utc() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
