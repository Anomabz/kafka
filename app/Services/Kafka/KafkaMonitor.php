<?php

namespace App\Services\Kafka;

use App\Console\Commands\KafkaConsume;
use App\Models\KafkaEventLog;
use App\Models\KafkaOutbox;
use App\Services\KafkaProducerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Bahan halaman Admin -> Event Stream (Kafka). Setiap bagian dikumpulkan
 * sendiri-sendiri dan gagalnya satu bagian (mis. REST Proxy mati) tidak
 * menghapus bagian lain: justru saat Kafka bermasalah halaman ini yang paling
 * dibutuhkan, jadi arsip di database dan status outbox tetap tampil.
 */
class KafkaMonitor
{
    /** Consumer dianggap mati kalau detak terakhirnya lebih tua dari ini (detik). */
    private const HEARTBEAT_STALE = 20;

    /** Label bahasa awam per jenis event; jenis yang tak dikenal tampil apa adanya. */
    private const LABELS = [
        'auth.login' => 'Login berhasil',
        'auth.logout' => 'Logout',
        'auth.access_denied' => 'Login ditolak: akun belum terdaftar',
        'penugasan.granted' => 'Akses diberikan',
        'penugasan.revoked' => 'Akses dicabut',
        'impersonation.started' => 'Admin mulai menyamar',
        'impersonation.stopped' => 'Admin berhenti menyamar',
        'sync.table_updated' => 'Tabel sinkronisasi diperbarui',
        'sync.batch_finished' => 'Putaran sinkronisasi selesai',
        'test' => 'Event uji',
    ];

    public function __construct(private readonly KafkaRestProxy $proxy) {}

    /**
     * Ringkasan tanpa jaringan (hanya cache + database): aman dipanggil dari
     * halaman lain, mis. kartu di Sinkronisasi PD UNSRI.
     *
     * @return array{status: string, label: string, detail: string}
     */
    public function brief(): array
    {
        if (! config('kafka.enabled')) {
            return ['status' => 'off', 'label' => 'Dinonaktifkan', 'detail' => 'KAFKA_ENABLED=false'];
        }

        $last = Cache::get(KafkaProducerService::LAST_RESULT_KEY);
        $heartbeat = Cache::get(KafkaConsume::HEARTBEAT_KEY);
        $pending = $this->outboxCount(KafkaOutbox::PENDING);
        $alive = $this->alive($heartbeat);

        if (($last['ok'] ?? true) === false || $pending > 0) {
            return ['status' => 'warn', 'label' => 'Ada event tertunda', 'detail' => "{$pending} event menunggu dikirim ulang"];
        }

        if (! $alive) {
            return ['status' => 'warn', 'label' => 'Consumer tidak aktif', 'detail' => 'Event terkirim tetapi belum ada yang membaca'];
        }

        return ['status' => 'ok', 'label' => 'Aktif & terhubung', 'detail' => 'Producer dan consumer berjalan'];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?string $topicFilter = null): array
    {
        $enabled = (bool) config('kafka.enabled');
        $proxy = $this->proxyStatus($enabled);
        $heartbeat = Cache::get(KafkaConsume::HEARTBEAT_KEY);
        $consumer = $this->consumerStatus($proxy['ok'], $heartbeat);
        $outbox = [
            'pending' => $this->outboxCount(KafkaOutbox::PENDING),
            'dead' => $this->outboxCount(KafkaOutbox::DEAD),
        ];
        $producer = Cache::get(KafkaProducerService::LAST_RESULT_KEY);

        $overall = match (true) {
            ! $enabled => 'off',
            ! $proxy['ok'] => 'down',
            ! $consumer['alive'] || ($consumer['lag'] ?? 0) > 50 || $outbox['pending'] > 0 || $outbox['dead'] > 0 || ($producer['ok'] ?? true) === false => 'warn',
            default => 'ok',
        };

        return [
            'enabled' => $enabled,
            'overall' => $overall,
            'generated_at' => now()->toIso8601String(),
            'proxy' => $proxy,
            'consumer' => $consumer,
            'producer' => $producer,
            'outbox' => $outbox,
            'topics' => $this->topics($proxy['ok']),
            'events' => $this->events($topicFilter),
            'events_24h' => $this->safe(fn () => KafkaEventLog::query()->where('consumed_at', '>=', now()->subDay())->count(), 0),
        ];
    }

    /** @return array{ok: bool, url: string, brokers: int|null, latency_ms: int|null, error: string|null} */
    private function proxyStatus(bool $enabled): array
    {
        $out = ['ok' => false, 'url' => $this->proxy->baseUrl(), 'brokers' => null, 'latency_ms' => null, 'error' => null];

        if (! $enabled) {
            $out['error'] = 'Kafka dinonaktifkan (KAFKA_ENABLED=false).';

            return $out;
        }

        $t = microtime(true);
        try {
            $out['brokers'] = $this->proxy->brokerCount();
            $out['ok'] = $out['brokers'] > 0;
            $out['error'] = $out['ok'] ? null : 'REST Proxy aktif tetapi tidak melihat broker Kafka.';
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        $out['latency_ms'] = (int) round((microtime(true) - $t) * 1000);

        return $out;
    }

    /** @return array<string, mixed> */
    private function consumerStatus(bool $proxyOk, ?array $heartbeat): array
    {
        $group = (string) config('kafka.consumer.group');
        $out = [
            'group' => $group,
            'alive' => $this->alive($heartbeat),
            'heartbeat_at' => $heartbeat['at'] ?? null,
            'processed' => $heartbeat['processed'] ?? 0,
            'error' => $heartbeat['error'] ?? null,
            'state' => null,
            'lag' => null,
            'lags' => [],
        ];

        if (! $proxyOk) {
            return $out;
        }

        try {
            $out['state'] = $this->proxy->consumerGroup($group)['state'] ?? null;
            $lags = $this->proxy->consumerGroupLags($group);
            $out['lags'] = $lags ?? [];
            $out['lag'] = $lags === null ? null : array_sum(array_column($lags, 'lag'));
        } catch (\Throwable $e) {
            $out['error'] ??= $e->getMessage();
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function topics(bool $proxyOk): array
    {
        $archived = $this->safe(
            fn () => KafkaEventLog::query()->select('topic', DB::raw('COUNT(*) AS n'))->groupBy('topic')->pluck('n', 'topic')->all(),
            [],
        );

        $rows = [];
        foreach (config('kafka.topics') as $name => $topic) {
            $messages = null;
            if ($proxyOk) {
                try {
                    $messages = array_sum(array_map(fn ($p) => $p['end_offset'], $this->proxy->partitionOffsets($name)));
                } catch (\Throwable) {
                    // Topic belum dibuat / proxy lambat: tampil sebagai "-".
                }
            }

            $rows[] = [
                'name' => $name,
                'description' => $topic['description'],
                'partitions' => $topic['partitions'],
                'retention_days' => $topic['retention_days'],
                'messages' => $messages,
                'archived' => (int) ($archived[$name] ?? 0),
            ];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function events(?string $topic): array
    {
        return $this->safe(function () use ($topic): array {
            return KafkaEventLog::query()
                ->when($topic && array_key_exists($topic, config('kafka.topics')), fn ($q) => $q->where('topic', $topic))
                ->orderByDesc('id')
                ->limit(25)
                ->get()
                ->map(fn (KafkaEventLog $e): array => [
                    'event_id' => $e->event_id,
                    'topic' => $e->topic,
                    'type' => $e->type,
                    'label' => self::LABELS[$e->type] ?? $e->type,
                    'detail' => $this->detail($e->type, (array) ($e->payload['data'] ?? [])),
                    'actor' => $e->actor,
                    'partition' => $e->partition,
                    'offset' => $e->offset,
                    'occurred_at' => $e->occurred_at?->toIso8601String(),
                    'consumed_at' => $e->consumed_at?->toIso8601String(),
                ])
                ->all();
        }, []);
    }

    /** Satu baris ringkas isi event untuk dibaca manusia. */
    private function detail(string $type, array $d): string
    {
        return match (true) {
            $type === 'sync.table_updated' => sprintf('%s: %s baris diterima', $d['table'] ?? '?', number_format((int) ($d['rows_fetched'] ?? 0), 0, ',', '.')),
            $type === 'sync.batch_finished' => sprintf(
                '%d diperbarui, %d dilewati, %d gagal%s',
                $d['done'] ?? 0, $d['skipped'] ?? 0, $d['failed'] ?? 0, ! empty($d['cancelled']) ? ' (dibatalkan)' : '',
            ),
            str_starts_with($type, 'penugasan.') => trim(($d['peran'] ?? '').' '.($d['target_nama'] ?? $d['target_nip'] ?? $d['target_email'] ?? '')),
            str_starts_with($type, 'impersonation.') => (string) ($d['target_nama'] ?? $d['terakhir_sebagai'] ?? ''),
            str_starts_with($type, 'auth.') => isset($d['ip']) ? 'IP '.$d['ip'] : '',
            $type === 'test' => (string) ($d['pesan'] ?? ''),
            default => '',
        };
    }

    private function alive(?array $heartbeat): bool
    {
        if (! is_array($heartbeat) || empty($heartbeat['at'])) {
            return false;
        }

        return now()->diffInSeconds($heartbeat['at'], true) <= self::HEARTBEAT_STALE;
    }

    private function outboxCount(string $status): int
    {
        return $this->safe(fn () => KafkaOutbox::query()->where('status', $status)->count(), 0);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @param  T  $fallback
     * @return T
     */
    private function safe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
