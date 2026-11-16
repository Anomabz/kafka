<?php

namespace App\Services;

use App\Models\KafkaOutbox;
use App\Services\Kafka\KafkaRestProxy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mengirim event SAPTO ke Kafka lewat REST Proxy -- lihat docs/kafka.md.
 *
 * Aturan utamanya: publish TIDAK PERNAH menjatuhkan alur yang memanggilnya.
 * Login, perubahan peran, dan sinkronisasi adalah pekerjaan utama; event
 * hanyalah kabar tentang pekerjaan itu. Jadi semua kegagalan ditangkap di
 * sini, dicatat jujur sebagai warning, dan event-nya disimpan ke outbox
 * (tabel kafka_outbox) untuk dikirim ulang `sapto:kafka-flush-outbox` tiap
 * menit -- event audit yang terjadi saat Kafka restart tidak hilang.
 *
 * Kenapa outbox hanya ditulis saat GAGAL (bukan setiap event seperti pola
 * transactional outbox klasik): event di sini tidak perlu atomik dengan
 * transaksi database, dan menulis setiap login ke tabel dulu hanya menambah
 * beban tanpa manfaat. Konsekuensinya: kalau PHP mati tepat di antara aksi
 * dan publish, event itu hilang -- risiko yang bisa diterima untuk SAPTO.
 */
class KafkaProducerService
{
    public const CIRCUIT_KEY = 'kafka:circuit_open_until';

    public const LAST_RESULT_KEY = 'kafka:producer:last_result';

    public function __construct(private readonly KafkaRestProxy $proxy) {}

    /**
     * Amplop event yang sama untuk semua topic: consumer dan orang yang
     * membaca kafka-ui tidak perlu menebak bentuk tiap pesan.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $actor
     * @return array<string, mixed>
     */
    public static function envelope(string $type, array $data, ?array $actor = null): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'type' => $type,
            'occurred_at' => now()->toIso8601String(),
            'source' => config('kafka.source', 'sapto'),
            'actor' => $actor,
            'data' => $data,
        ];
    }

    /**
     * Identitas akun seperlunya dari sesi SSO (tanpa token, unit kerja, dsb).
     * Saat admin sedang menyamar, pelakunya tetap admin aslinya.
     *
     * @return array{email: string|null, nip: string|null, name: string|null, impersonating: bool}|null
     */
    public static function actorFromRequest(?Request $request): ?array
    {
        if ($request === null || ! $request->hasSession()) {
            return null;
        }

        $impersonator = $request->session()->get('impersonator_sso_user');
        $user = (array) ($impersonator ?? $request->session()->get('sso_user', []));

        if ($user === []) {
            return null;
        }

        return self::actor($user) + ['impersonating' => $impersonator !== null];
    }

    /** @return array{email: string|null, nip: string|null, name: string|null} */
    public static function actor(array $ssoUser): array
    {
        return [
            'email' => $ssoUser['email'] ?? null,
            'nip' => $ssoUser['nomor_identitas'] ?? null,
            'name' => $ssoUser['name'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $actor
     * @return bool true hanya bila broker sudah menerima dan memberi offset
     */
    public function publish(string $topic, string $type, array $data, ?string $key = null, ?array $actor = null): bool
    {
        try {
            $event = self::envelope($type, $data, $actor);
        } catch (\Throwable $e) {
            Log::warning('Kafka: gagal menyusun event, dilewati.', ['topic' => $topic, 'type' => $type, 'error' => $e->getMessage()]);

            return false;
        }

        return $this->send($topic, $key, $event);
    }

    /**
     * Event jejak audit ke topic `sapto.audit`. Pelakunya diambil dari sesi
     * (admin asli bila sedang menyamar); key = identitas pelaku supaya event
     * satu orang tetap berurutan bila partisi ditambah suatu hari.
     *
     * @param  array<string, mixed>  $data
     */
    public function audit(string $type, array $data, ?Request $request = null): bool
    {
        $actor = self::actorFromRequest($request ?? (app()->bound('request') ? app('request') : null));

        return $this->publish('sapto.audit', $type, $data, $actor['email'] ?? $actor['nip'] ?? null, $actor);
    }

    /**
     * Kirim amplop yang sudah jadi (dipakai juga oleh flush outbox, supaya
     * event_id tetap sama di setiap percobaan -- consumer menyaring duplikat).
     *
     * @param  array<string, mixed>  $event
     */
    public function send(string $topic, ?string $key, array $event, bool $fromOutbox = false): bool
    {
        if (! config('kafka.enabled')) {
            return $fromOutbox ? false : $this->toOutbox($topic, $key, $event, 'Kafka dinonaktifkan (KAFKA_ENABLED=false).', quiet: true);
        }

        // Circuit breaker: proxy baru saja gagal -- jangan buat request ini
        // ikut menunggu timeout, langsung antrekan.
        if (! $fromOutbox && (int) Cache::get(self::CIRCUIT_KEY, 0) > time()) {
            return $this->toOutbox($topic, $key, $event, 'Dilewati: REST Proxy gagal < '.config('kafka.circuit_seconds').' detik lalu.', quiet: true);
        }

        try {
            $at = $this->proxy->produce($topic, $key, $event);
        } catch (\Throwable $e) {
            $this->remember(false, $topic, $e->getMessage(), null, $event['event_id'] ?? null);
            Cache::put(self::CIRCUIT_KEY, time() + (int) config('kafka.circuit_seconds', 30), 300);

            Log::warning('Kafka: event GAGAL dikirim ke REST Proxy.', [
                'topic' => $topic,
                'type' => $event['type'] ?? null,
                'event_id' => $event['event_id'] ?? null,
                'error' => $e->getMessage(),
                'outbox' => ! $fromOutbox,
            ]);

            return $fromOutbox ? false : $this->toOutbox($topic, $key, $event, $e->getMessage(), quiet: true);
        }

        Cache::forget(self::CIRCUIT_KEY);
        $this->remember(true, $topic, null, $at, $event['event_id'] ?? null);

        Log::info('Kafka: event diterima broker.', [
            'topic' => $topic,
            'type' => $event['type'] ?? null,
            'event_id' => $event['event_id'] ?? null,
            'partition' => $at['partition'],
            'offset' => $at['offset'],
        ]);

        return true;
    }

    /** @param  array<string, mixed>  $event */
    private function toOutbox(string $topic, ?string $key, array $event, string $reason, bool $quiet = false): bool
    {
        try {
            KafkaOutbox::query()->insertOrIgnore([
                'event_id' => $event['event_id'],
                'topic' => $topic,
                'event_key' => $key,
                'payload' => json_encode($event, JSON_UNESCAPED_UNICODE),
                'status' => KafkaOutbox::PENDING,
                'attempts' => 0,
                'last_error' => mb_substr($reason, 0, 2000),
                'next_attempt_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Database juga bermasalah: jejak terakhir yang tersisa adalah log.
            Log::warning('Kafka: event HILANG -- gagal dikirim dan gagal masuk outbox.', [
                'topic' => $topic, 'event' => $event, 'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Hasil kirim terakhir untuk halaman monitor (Admin -> Event Stream):
     * termasuk partisi/offset dari broker, bukti event benar-benar tersimpan.
     *
     * @param  array{partition: int, offset: int}|null  $at
     */
    private function remember(bool $ok, string $topic, ?string $error, ?array $at = null, ?string $eventId = null): void
    {
        try {
            Cache::put(self::LAST_RESULT_KEY, [
                'ok' => $ok, 'topic' => $topic, 'error' => $error, 'event_id' => $eventId,
                'partition' => $at['partition'] ?? null, 'offset' => $at['offset'] ?? null,
                'at' => now()->toIso8601String(),
            ], now()->addDays(7));
        } catch (\Throwable) {
            // Cache hanya untuk halaman monitor; jangan sampai menggagalkan apa pun.
        }
    }
}
