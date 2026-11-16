<?php

namespace Tests\Feature;

use App\Models\KafkaEventLog;
use App\Models\KafkaOutbox;
use App\Models\WebNotification;
use App\Services\Kafka\KafkaEventHandler;
use App\Services\Kafka\KafkaRestProxy;
use App\Services\KafkaProducerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Event stream Kafka lewat REST Proxy (lihat docs/kafka.md). Tidak ada tes
 * yang menyentuh Kafka sungguhan: REST Proxy selalu di-fake. phpunit.xml
 * memaksa KAFKA_ENABLED=false, jadi tes yang butuh jalur jaringan
 * menyalakannya sendiri lewat config().
 */
class KafkaTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['sub' => 'a', 'name' => 'Admin', 'email' => 'admin@unsri.ac.id', 'nomor_identitas' => '111', 'is_admin' => true];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['kafka.rest_url' => 'http://kafka-rest.test:8082', 'kafka.circuit_seconds' => 30]);
    }

    private function producer(): KafkaProducerService
    {
        return app(KafkaProducerService::class);
    }

    private function fakeProduceOk(int $offset = 7): void
    {
        Http::fake([
            '*/topics/*' => Http::response(['offsets' => [['partition' => 0, 'offset' => $offset, 'error_code' => null, 'error' => null]]]),
        ]);
    }

    // --- Producer -----------------------------------------------------------

    public function test_publish_sends_a_json_record_and_reports_the_broker_offset(): void
    {
        config(['kafka.enabled' => true]);
        $this->fakeProduceOk(7);

        $ok = $this->producer()->publish('sapto.audit', 'auth.login', ['ip' => '1.2.3.4'], 'a@b.c', ['email' => 'a@b.c', 'nip' => null, 'name' => 'A']);

        $this->assertTrue($ok);
        Http::assertSent(function (Request $r): bool {
            $body = json_decode($r->body(), true);
            $event = $body['records'][0]['value'];

            return $r->url() === 'http://kafka-rest.test:8082/topics/sapto.audit'
                && $r->hasHeader('Content-Type', 'application/vnd.kafka.json.v2+json')
                && $body['records'][0]['key'] === 'a@b.c'
                && $event['type'] === 'auth.login'
                && $event['source'] === 'sapto'
                && $event['data'] === ['ip' => '1.2.3.4']
                && $event['actor']['email'] === 'a@b.c'
                && preg_match('/^[0-9a-f-]{36}$/', $event['event_id']) === 1;
        });

        // Bukti nyata untuk halaman monitor: partisi & offset dari broker.
        $last = Cache::get(KafkaProducerService::LAST_RESULT_KEY);
        $this->assertTrue($last['ok']);
        $this->assertSame(7, $last['offset']);
        $this->assertSame(0, KafkaOutbox::count());
    }

    public function test_a_failed_send_never_throws_and_goes_to_the_outbox(): void
    {
        config(['kafka.enabled' => true]);
        Http::fake(['*' => Http::response(['message' => 'down'], 503)]);

        $ok = $this->producer()->publish('sapto.audit', 'auth.login', ['ip' => '1.2.3.4']);

        $this->assertFalse($ok);
        $row = KafkaOutbox::first();
        $this->assertSame('sapto.audit', $row->topic);
        $this->assertSame(KafkaOutbox::PENDING, $row->status);
        $this->assertSame('auth.login', $row->payload['type']);
        $this->assertFalse(Cache::get(KafkaProducerService::LAST_RESULT_KEY)['ok']);
    }

    public function test_a_broker_side_rejection_is_a_failure_not_a_success(): void
    {
        // REST Proxy membalas HTTP 200 walau record ditolak broker; itu yang
        // dulu (fsockopen) tidak pernah bisa dibedakan dari "terkirim".
        config(['kafka.enabled' => true]);
        Http::fake(['*/topics/*' => Http::response(['offsets' => [['partition' => null, 'offset' => null, 'error_code' => 40301, 'error' => 'not authorized']]])]);

        $this->assertFalse($this->producer()->publish('sapto.audit', 'test', []));
        $this->assertSame(1, KafkaOutbox::count());
    }

    public function test_circuit_breaker_skips_the_network_after_a_failure(): void
    {
        config(['kafka.enabled' => true]);
        Http::fake(['*' => Http::response('x', 500)]);

        $this->producer()->publish('sapto.audit', 'a', []);
        $this->producer()->publish('sapto.audit', 'b', []);

        // Request kedua tidak menunggu proxy yang baru saja gagal.
        Http::assertSentCount(1);
        $this->assertSame(2, KafkaOutbox::count());
    }

    public function test_disabled_kafka_writes_to_the_outbox_without_touching_the_network(): void
    {
        Http::fake();

        $this->assertFalse($this->producer()->publish('sapto.audit', 'test', []));

        Http::assertNothingSent();
        $this->assertSame(1, KafkaOutbox::count());
    }

    // --- Outbox -------------------------------------------------------------

    public function test_flush_outbox_resends_with_the_same_event_id_and_marks_sent(): void
    {
        config(['kafka.enabled' => true]);
        // Kirim pertama gagal (masuk outbox), kirim ulang berhasil.
        Http::fake(['*' => Http::sequence()
            ->push('x', 500)
            ->push(['offsets' => [['partition' => 0, 'offset' => 3, 'error_code' => null, 'error' => null]]])]);
        $this->producer()->publish('sapto.audit', 'auth.login', []);
        $eventId = KafkaOutbox::first()->event_id;

        Cache::flush();
        $this->artisan('sapto:kafka-flush-outbox')->assertSuccessful();

        $row = KafkaOutbox::first();
        $this->assertSame(KafkaOutbox::SENT, $row->status);
        Http::assertSent(fn (Request $r): bool => json_decode($r->body(), true)['records'][0]['value']['event_id'] === $eventId);
    }

    public function test_flush_outbox_backs_off_and_eventually_marks_events_dead(): void
    {
        config(['kafka.enabled' => true, 'kafka.outbox.max_attempts' => 2]);
        Http::fake(['*' => Http::response('x', 500)]);
        $this->producer()->publish('sapto.audit', 'auth.login', []);

        $this->artisan('sapto:kafka-flush-outbox');
        $row = KafkaOutbox::first();
        $this->assertSame(1, $row->attempts);
        $this->assertSame(KafkaOutbox::PENDING, $row->status);
        $this->assertTrue($row->next_attempt_at->isFuture());

        $row->update(['next_attempt_at' => now()->subMinute()]);
        $this->artisan('sapto:kafka-flush-outbox');
        $this->assertSame(KafkaOutbox::DEAD, KafkaOutbox::first()->status);
    }

    // --- Consumer handler ---------------------------------------------------

    private function record(string $topic, array $event, int $offset = 0): array
    {
        return ['topic' => $topic, 'key' => 'k', 'value' => $event, 'partition' => 0, 'offset' => $offset];
    }

    public function test_handler_archives_an_event_once_even_if_it_is_delivered_twice(): void
    {
        $event = KafkaProducerService::envelope('auth.login', ['ip' => '1.1.1.1'], ['email' => 'a@b.c', 'nip' => null, 'name' => 'A']);
        $handler = app(KafkaEventHandler::class);

        $this->assertSame('stored', $handler->handle($this->record('sapto.audit', $event, 0)));
        $this->assertSame('duplicate', $handler->handle($this->record('sapto.audit', $event, 5)));

        $this->assertSame(1, KafkaEventLog::count());
        $log = KafkaEventLog::first();
        $this->assertSame('auth.login', $log->type);
        $this->assertSame('a@b.c', $log->actor);
        $this->assertSame(0, $log->offset);
    }

    public function test_handler_skips_messages_that_are_not_sapto_events(): void
    {
        $handler = app(KafkaEventHandler::class);

        $this->assertSame('invalid', $handler->handle($this->record('sapto.audit', ['hello' => 'world'])));
        $this->assertSame('invalid', $handler->handle($this->record('sapto.audit', ['event_id' => 'bukan-uuid', 'type' => 'x'])));
        $this->assertSame(0, KafkaEventLog::count());
    }

    public function test_batch_finished_creates_one_admin_only_notification_even_on_redelivery(): void
    {
        $event = KafkaProducerService::envelope('sync.batch_finished', ['done' => 300, 'skipped' => 9, 'failed' => 3, 'cancelled' => false]);
        $handler = app(KafkaEventHandler::class);

        $handler->handle($this->record('sapto.sync.batch_finished', $event));
        $handler->handle($this->record('sapto.sync.batch_finished', $event, 1));

        $this->assertSame(1, WebNotification::count());
        $n = WebNotification::first();
        $this->assertSame('admin', $n->audience);
        $this->assertStringContainsString('300 tabel diperbarui', $n->message);
        $this->assertStringContainsString('3 gagal', $n->message);
    }

    public function test_admin_notifications_are_hidden_from_non_admin_users(): void
    {
        WebNotification::create(['message' => 'untuk semua', 'created_at' => now()]);
        WebNotification::create(['message' => 'khusus admin', 'audience' => 'admin', 'created_at' => now()]);

        $korprodi = ['sub' => 'k', 'name' => 'K', 'nomor_identitas' => '222'];

        $this->withSession(['sso_user' => $korprodi])->getJson('/notifications/latest?after_id=0')
            ->assertOk()->assertJsonCount(1, 'notifications')->assertJsonPath('notifications.0.message', 'untuk semua');

        $this->withSession(['sso_user' => self::ADMIN])->getJson('/notifications/latest?after_id=0')
            ->assertOk()->assertJsonCount(2, 'notifications');
    }

    // --- Setup topic --------------------------------------------------------

    public function test_setup_creates_missing_topics_and_aligns_retention_of_existing_ones(): void
    {
        config(['kafka.enabled' => true]);
        Http::fake([
            '*/v3/clusters' => Http::response(['data' => [['cluster_id' => 'C1']]]),
            '*/v3/clusters/C1/brokers' => Http::response(['data' => [['broker_id' => 1]]]),
            '*/v3/clusters/C1/topics/sapto.sync.table_updated/configs:alter' => Http::response('', 204),
            '*/v3/clusters/C1/topics/sapto.sync.table_updated/configs' => Http::response(['data' => [['name' => 'retention.ms', 'value' => '604800000']]]),
            '*/v3/clusters/C1/topics/sapto.sync.table_updated' => Http::response([]),
            '*/v3/clusters/C1/topics/sapto.sync.batch_finished' => Http::response([], 404),
            '*/v3/clusters/C1/topics/sapto.audit' => Http::response([], 404),
            '*/v3/clusters/C1/topics' => Http::response([], 201),
        ]);

        $this->artisan('sapto:kafka-setup')->assertSuccessful();

        Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && str_ends_with($r->url(), '/topics')
            && $r['topic_name'] === 'sapto.audit' && $r['partitions_count'] === 1 && $r['replication_factor'] === 1
            && $r['configs'][0]['value'] === (string) (90 * 86_400_000));
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'sapto.sync.table_updated/configs:alter')
            && $r['data'][0]['value'] === (string) (30 * 86_400_000));
    }

    // --- Halaman admin ------------------------------------------------------

    public function test_kafka_admin_pages_are_admin_only(): void
    {
        $korprodi = ['sub' => 'k', 'name' => 'K', 'nomor_identitas' => '222'];

        foreach (['/admin/kafka', '/admin/kafka/status'] as $url) {
            $this->withSession(['sso_user' => $korprodi])->get($url)->assertForbidden();
        }
        $this->withSession(['sso_user' => $korprodi])->post('/admin/kafka/test')->assertForbidden();
        $this->assertSame(0, KafkaOutbox::count());
    }

    public function test_kafka_admin_page_renders_for_admin(): void
    {
        $this->withSession(['sso_user' => self::ADMIN])->get('/admin/kafka')
            ->assertOk()
            ->assertSee('Event Stream (Kafka)')
            ->assertSee('sapto.sync.batch_finished')
            ->assertSee('Kirim event uji');
    }

    public function test_status_reports_a_dead_proxy_without_failing_and_keeps_the_archive(): void
    {
        config(['kafka.enabled' => true]);
        Http::fake(['*' => Http::response('boom', 502)]);
        KafkaEventLog::create([
            'event_id' => '00000000-0000-4000-8000-000000000001', 'topic' => 'sapto.audit', 'partition' => 0, 'offset' => 0,
            'type' => 'auth.login', 'actor' => 'a@b.c', 'payload' => ['data' => ['ip' => '9.9.9.9']], 'occurred_at' => now(), 'consumed_at' => now(),
        ]);

        $json = $this->withSession(['sso_user' => self::ADMIN])->getJson('/admin/kafka/status')->assertOk();

        $json->assertJsonPath('overall', 'down')
            ->assertJsonPath('proxy.ok', false)
            ->assertJsonPath('events.0.label', 'Login berhasil')
            ->assertJsonPath('events.0.detail', 'IP 9.9.9.9')
            ->assertJsonPath('topics.2.archived', 1);
    }

    public function test_test_event_endpoint_publishes_and_returns_the_broker_offset(): void
    {
        config(['kafka.enabled' => true]);
        $this->fakeProduceOk(11);

        $this->withSession(['sso_user' => self::ADMIN])->postJson('/admin/kafka/test')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('offset', 11)
            ->assertJsonPath('partition', 0);

        Http::assertSent(fn (Request $r): bool => json_decode($r->body(), true)['records'][0]['value']['type'] === 'test');
    }

    public function test_test_event_endpoint_is_rate_limited(): void
    {
        $this->withSession(['sso_user' => self::ADMIN]);

        $this->postJson('/admin/kafka/test')->assertOk();
        $this->postJson('/admin/kafka/test')->assertStatus(429);
    }

    // --- Pemicu audit -------------------------------------------------------

    public function test_granting_access_records_an_audit_event(): void
    {
        $this->withSession(['sso_user' => self::ADMIN])->post('/admin/peran', [
            'peran' => 'rektor', 'nip' => '198001012005011001', 'nama' => 'Bapak Rektor',
        ])->assertRedirect();

        $row = KafkaOutbox::where('topic', 'sapto.audit')->first();
        $this->assertNotNull($row, 'penugasan.granted harus tercatat (KAFKA_ENABLED=false -> outbox)');
        $this->assertSame('penugasan.granted', $row->payload['type']);
        $this->assertSame('admin@unsri.ac.id', $row->payload['actor']['email']);
        $this->assertSame('rektor', $row->payload['data']['peran']);
    }

    public function test_batch_finished_callback_survives_serialization_and_announces_the_batch(): void
    {
        // Callback batch disimpan serial di job_batches: harus bisa dibungkus
        // ulang tanpa membawa objek lain, lalu dijalankan saat batch selesai.
        \Illuminate\Support\Facades\Queue::fake();
        $callback = unserialize(serialize(new \App\Services\PdUnsriSyncFinished));

        $batch = \Illuminate\Support\Facades\Bus::batch([new \App\Jobs\SyncPdUnsriTableJob('t1', 'tabel_uji')])
            ->name('pd-unsri-sync')
            ->withOption('triggered_by', 'uji')
            ->dispatch();
        \App\Models\PdUnsriSyncResult::create(['batch_id' => $batch->id, 'table_name' => 't1', 'slug' => 't1', 'status' => \App\Models\PdUnsriSyncResult::DONE]);
        \App\Models\PdUnsriSyncResult::create(['batch_id' => $batch->id, 'table_name' => 't2', 'slug' => 't2', 'status' => \App\Models\PdUnsriSyncResult::FAILED]);

        $callback(\Illuminate\Support\Facades\Bus::findBatch($batch->id));

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RefreshSaptoCacheJob::class);
        $row = KafkaOutbox::where('topic', 'sapto.sync.batch_finished')->first();
        $this->assertNotNull($row);
        $this->assertSame('sync.batch_finished', $row->payload['type']);
        $this->assertSame($batch->id, $row->payload['data']['batch_id']);
        $this->assertSame(1, $row->payload['data']['done']);
        $this->assertSame('uji', $row->payload['data']['triggered_by']);
    }

    public function test_the_proxy_client_is_built_from_config(): void
    {
        config(['kafka.rest_url' => 'http://contoh.test:1234/', 'kafka.timeout' => 3]);

        $this->assertSame('http://contoh.test:1234', app(KafkaRestProxy::class)->baseUrl());
    }
}
