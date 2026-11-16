<?php

namespace App\Services\Kafka;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Satu-satunya tempat SAPTO bicara ke Confluent Kafka REST Proxy. Semua
 * operasi lain (producer, consumer, setup topic, halaman monitor) memakai
 * kelas ini, jadi URL dan content type REST Proxy cukup benar di satu tempat.
 *
 * Dua generasi API dipakai sesuai kekuatannya:
 *  - v2 (`/topics`, `/consumers`): produce dan consume JSON -- satu-satunya
 *    API consumer yang disediakan REST Proxy;
 *  - v3 (`/v3/clusters/...`): administrasi -- buat/ubah topic, lag consumer group.
 *
 * Jawaban gagal dilempar sebagai KafkaRestException (membawa status HTTP dan
 * error_code REST Proxy); pemanggil yang memutuskan apakah itu fatal.
 */
class KafkaRestProxy
{
    public const V2_JSON = 'application/vnd.kafka.json.v2+json';

    public const V2 = 'application/vnd.kafka.v2+json';

    private ?string $clusterId = null;

    private readonly string $baseUrl;

    public function __construct(string $baseUrl, private readonly float $timeout)
    {
        // Garis miring di ujung (mudah terbawa dari .env) membuat setiap URL
        // berbentuk "//topics/..." yang ditolak proxy dengan 404.
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public static function fromConfig(): self
    {
        return new self((string) config('kafka.rest_url'), (float) config('kafka.timeout', 2));
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    // --- Produce -----------------------------------------------------------

    /**
     * Kirim satu record JSON. Mengembalikan partisi & offset tempat record
     * disimpan broker -- bukti nyata record masuk, bukan sekadar koneksi TCP
     * terbuka (pelajaran dari bug fsockopen, lihat docs/kafka.md).
     *
     * @param  array<string, mixed>  $value
     * @return array{partition: int, offset: int}
     */
    public function produce(string $topic, ?string $key, array $value): array
    {
        $record = ['value' => $value];
        if ($key !== null) {
            $record['key'] = $key;
        }

        $res = $this->http()
            ->withBody(json_encode(['records' => [$record]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), self::V2_JSON)
            ->accept(self::V2)
            ->post($this->url('/topics/'.rawurlencode($topic)));

        $this->ensureOk($res, "produce ke {$topic}");

        // REST Proxy v2 membalas 200 walau record-nya ditolak broker; hasil
        // per record ada di offsets[].error_code.
        $offset = $res->json('offsets.0') ?? [];
        if (($offset['error_code'] ?? null) !== null || ! isset($offset['offset'])) {
            throw new KafkaRestException(
                "Broker menolak record ke {$topic}: ".($offset['error'] ?? 'jawaban tanpa offset'),
                $res->status(), $offset['error_code'] ?? null,
            );
        }

        return ['partition' => (int) $offset['partition'], 'offset' => (int) $offset['offset']];
    }

    // --- Admin (v3) --------------------------------------------------------

    public function clusterId(): string
    {
        if ($this->clusterId !== null) {
            return $this->clusterId;
        }

        $res = $this->admin()->get($this->url('/v3/clusters'));
        $this->ensureOk($res, 'membaca cluster');

        $id = $res->json('data.0.cluster_id');
        if (! $id) {
            throw new KafkaRestException('REST Proxy tidak melaporkan cluster Kafka mana pun.', $res->status());
        }

        return $this->clusterId = (string) $id;
    }

    /** @return int jumlah broker yang terlihat REST Proxy */
    public function brokerCount(): int
    {
        $res = $this->admin()->get($this->url('/v3/clusters/'.$this->clusterId().'/brokers'));
        $this->ensureOk($res, 'membaca broker');

        return count($res->json('data') ?? []);
    }

    /** @return array<string, mixed>|null null kalau topic belum ada */
    public function topic(string $topic): ?array
    {
        $res = $this->admin()->get($this->url('/v3/clusters/'.$this->clusterId().'/topics/'.rawurlencode($topic)));

        if ($res->status() === 404) {
            return null;
        }

        $this->ensureOk($res, "membaca topic {$topic}");

        return (array) $res->json();
    }

    /** @param  array<string, string>  $configs */
    public function createTopic(string $topic, int $partitions, int $replication, array $configs): void
    {
        $res = $this->admin()->post($this->url('/v3/clusters/'.$this->clusterId().'/topics'), [
            'topic_name' => $topic,
            'partitions_count' => $partitions,
            'replication_factor' => $replication,
            'configs' => $this->configList($configs),
        ]);

        $this->ensureOk($res, "membuat topic {$topic}");
    }

    /** @return array<string, string|null> */
    public function topicConfigs(string $topic): array
    {
        $res = $this->admin()->get($this->url('/v3/clusters/'.$this->clusterId().'/topics/'.rawurlencode($topic).'/configs'));
        $this->ensureOk($res, "membaca konfigurasi {$topic}");

        return collect($res->json('data') ?? [])->pluck('value', 'name')->all();
    }

    /** @param  array<string, string>  $configs */
    public function alterTopicConfigs(string $topic, array $configs): void
    {
        $res = $this->admin()->post(
            $this->url('/v3/clusters/'.$this->clusterId().'/topics/'.rawurlencode($topic).'/configs:alter'),
            ['data' => $this->configList($configs)],
        );

        $this->ensureOk($res, "mengubah konfigurasi {$topic}");
    }

    /**
     * Offset awal & akhir tiap partisi. end_offset = jumlah pesan yang pernah
     * masuk (offset dimulai dari 0), selama belum ada yang dihapus retensi.
     *
     * @return list<array{partition: int, beginning_offset: int, end_offset: int}>
     */
    public function partitionOffsets(string $topic): array
    {
        // Endpoint v2 menolak Accept: application/json (HTTP 406); harus
        // application/vnd.kafka.v2+json seperti semua endpoint v2 lainnya.
        $res = $this->admin()->accept(self::V2)->get($this->url('/topics/'.rawurlencode($topic).'/partitions'));
        $this->ensureOk($res, "membaca partisi {$topic}");

        $out = [];
        foreach ((array) $res->json() as $p) {
            $partition = (int) ($p['partition'] ?? 0);
            $o = $this->admin()->accept(self::V2)->get($this->url('/topics/'.rawurlencode($topic).'/partitions/'.$partition.'/offsets'));
            $this->ensureOk($o, "membaca offset {$topic}/{$partition}");

            $out[] = [
                'partition' => $partition,
                'beginning_offset' => (int) $o->json('beginning_offset'),
                'end_offset' => (int) $o->json('end_offset'),
            ];
        }

        return $out;
    }

    /**
     * Lag per partisi untuk satu consumer group: berapa pesan yang sudah
     * masuk tapi belum di-commit consumer. null kalau group belum pernah ada.
     *
     * @return list<array{topic: string, partition: int, current_offset: int, log_end_offset: int, lag: int, consumer_id: string|null}>|null
     */
    public function consumerGroupLags(string $group): ?array
    {
        $res = $this->admin()->get($this->url('/v3/clusters/'.$this->clusterId().'/consumer-groups/'.rawurlencode($group).'/lags'));

        if ($res->status() === 404) {
            return null;
        }

        $this->ensureOk($res, "membaca lag {$group}");

        return collect($res->json('data') ?? [])->map(fn (array $l) => [
            'topic' => (string) $l['topic_name'],
            'partition' => (int) $l['partition_id'],
            'current_offset' => (int) $l['current_offset'],
            'log_end_offset' => (int) $l['log_end_offset'],
            'lag' => (int) $l['lag'],
            'consumer_id' => $l['consumer_id'] ?? null,
        ])->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function consumerGroup(string $group): ?array
    {
        $res = $this->admin()->get($this->url('/v3/clusters/'.$this->clusterId().'/consumer-groups/'.rawurlencode($group)));

        if ($res->status() === 404) {
            return null;
        }

        $this->ensureOk($res, "membaca consumer group {$group}");

        return (array) $res->json();
    }

    // --- Consume (v2) ------------------------------------------------------

    public function createConsumer(string $group, string $instance): void
    {
        $res = $this->admin()
            ->withBody(json_encode([
                'name' => $instance,
                'format' => 'json',
                // Group baru mulai dari awal topic: event yang dikirim sebelum
                // consumer pertama kali jalan tetap ikut tercatat.
                'auto.offset.reset' => 'earliest',
                // Commit manual SETELAH event tersimpan -- at-least-once.
                'auto.commit.enable' => 'false',
            ]), self::V2)
            ->accept(self::V2)
            ->post($this->url('/consumers/'.rawurlencode($group)));

        $this->ensureOk($res, "membuat consumer {$group}/{$instance}");
    }

    public function deleteConsumer(string $group, string $instance): void
    {
        $res = $this->admin()->accept(self::V2)
            ->withHeaders(['Content-Type' => self::V2])
            ->delete($this->consumerUrl($group, $instance));

        if ($res->status() !== 404) {
            $this->ensureOk($res, "menghapus consumer {$group}/{$instance}");
        }
    }

    /** @param  list<string>  $topics */
    public function subscribe(string $group, string $instance, array $topics): void
    {
        $res = $this->admin()
            ->withBody(json_encode(['topics' => array_values($topics)]), self::V2)
            ->accept(self::V2)
            ->post($this->consumerUrl($group, $instance).'/subscription');

        $this->ensureOk($res, 'subscribe');
    }

    /** @return list<array{topic: string, key: mixed, value: mixed, partition: int, offset: int}> */
    public function poll(string $group, string $instance, int $timeoutMs, int $maxBytes): array
    {
        // Timeout HTTP harus lebih panjang dari waktu tunggu REST Proxy, kalau
        // tidak poll kosong yang normal terbaca sebagai error jaringan.
        $res = Http::timeout(max($this->timeout, $timeoutMs / 1000 + 5))
            ->connectTimeout($this->timeout)
            ->accept(self::V2_JSON)
            ->get($this->consumerUrl($group, $instance).'/records', ['timeout' => $timeoutMs, 'max_bytes' => $maxBytes]);

        $this->ensureOk($res, 'poll');

        return array_values((array) $res->json());
    }

    /** @param  list<array{topic: string, partition: int, offset: int}>  $offsets */
    public function commit(string $group, string $instance, array $offsets): void
    {
        $res = $this->admin()
            ->withBody(json_encode(['offsets' => array_values($offsets)]), self::V2)
            ->accept(self::V2)
            ->post($this->consumerUrl($group, $instance).'/offsets');

        $this->ensureOk($res, 'commit offset');
    }

    // --- Util --------------------------------------------------------------

    private function consumerUrl(string $group, string $instance): string
    {
        // Sengaja disusun sendiri, bukan memakai base_uri dari jawaban
        // createConsumer: base_uri berisi host yang diiklankan proxy dan tidak
        // tersedia kalau instance dipakai ulang setelah proses restart.
        return $this->url('/consumers/'.rawurlencode($group).'/instances/'.rawurlencode($instance));
    }

    /**
     * Timeout PENDEK, hanya untuk produce: dipanggil di tengah login dan
     * sinkronisasi, yang tidak boleh ikut lambat karena Kafka lambat.
     */
    private function http(): PendingRequest
    {
        return Http::timeout($this->timeout)->connectTimeout($this->timeout)->acceptJson();
    }

    /**
     * Semua operasi lain (setup topic, monitor, kelola consumer) lebih longgar:
     * REST Proxy yang baru naik butuh beberapa detik untuk membuat topic atau
     * mengubah konfigurasi, dan jawabannya yang terlambat bukan berarti gagal
     * (perubahannya tetap terjadi di broker). connectTimeout tetap pendek supaya
     * proxy yang benar-benar mati tetap cepat terdeteksi.
     */
    private function admin(): PendingRequest
    {
        return Http::timeout(max($this->timeout, 15))->connectTimeout($this->timeout)->acceptJson();
    }

    private function url(string $path): string
    {
        return $this->baseUrl.$path;
    }

    /** @param  array<string, string>  $configs */
    private function configList(array $configs): array
    {
        return collect($configs)->map(fn ($v, $k) => ['name' => $k, 'value' => (string) $v])->values()->all();
    }

    private function ensureOk(Response $res, string $what): void
    {
        if ($res->successful()) {
            return;
        }

        throw new KafkaRestException(
            sprintf('REST Proxy gagal %s: HTTP %d %s', $what, $res->status(), (string) ($res->json('message') ?? mb_substr($res->body(), 0, 200))),
            $res->status(),
            $res->json('error_code'),
        );
    }
}
