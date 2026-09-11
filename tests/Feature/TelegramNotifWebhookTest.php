<?php

namespace Tests\Feature;

use App\Models\WebNotification;
use App\Support\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Perintah Telegram "/notif <pesan>" (routes/web.php) menyiarkan alert ke
 * website lewat WebNotification + WebNotificationController::latest, dan
 * dibatasi allowlist services.telegram.notif_admin_ids supaya siapa pun yang
 * menemukan @saptostatusbot tidak bisa siar pesan ke semua orang.
 */
class TelegramNotifWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(); // jangan benar-benar memanggil api.telegram.org
        Storage::fake('local'); // jangan sentuh storage/app/private/telegram_chat_ids.json yang asli

        config([
            'services.telegram.bot_token' => 'dummy-token',
            'services.telegram.webhook_secret' => self::SECRET,
            'services.telegram.notif_admin_ids' => ['111'],
        ]);
    }

    private function webhook(array $message)
    {
        return $this->postJson('/telegram/webhook/'.self::SECRET, ['message' => $message]);
    }

    public function test_notif_command_from_an_allowed_chat_broadcasts_a_notification(): void
    {
        $response = $this->webhook(['chat' => ['id' => 111], 'text' => '/notif Server akan maintenance jam 22.00']);

        $response->assertNoContent();
        $this->assertDatabaseHas('web_notifications', ['message' => 'Server akan maintenance jam 22.00']);
    }

    public function test_notif_command_from_an_unknown_chat_is_rejected_and_not_broadcast(): void
    {
        $response = $this->webhook(['chat' => ['id' => 999], 'text' => '/notif coba siar dari luar']);

        $response->assertNoContent();
        $this->assertDatabaseCount('web_notifications', 0);
    }

    public function test_notif_command_without_a_message_does_not_broadcast(): void
    {
        $this->webhook(['chat' => ['id' => 111], 'text' => '/notif']);
        $this->webhook(['chat' => ['id' => 111], 'text' => '/notif   ']);

        $this->assertDatabaseCount('web_notifications', 0);
    }

    public function test_a_wrong_webhook_secret_is_rejected(): void
    {
        $response = $this->postJson('/telegram/webhook/wrong-secret', [
            'message' => ['chat' => ['id' => 111], 'text' => '/notif harusnya tidak masuk'],
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('web_notifications', 0);
    }

    public function test_a_plain_message_still_registers_the_chat_id_as_before(): void
    {
        $response = $this->webhook(['chat' => ['id' => 222], 'text' => 'halo bot']);

        $response->assertNoContent();
        $this->assertContains('222', TelegramNotifier::chatIds());
    }

    public function test_notifications_latest_requires_login(): void
    {
        $this->getJson('/notifications/latest')->assertUnauthorized();
    }

    public function test_notifications_latest_returns_only_notifications_after_the_given_id(): void
    {
        $this->withSession(['sso_user' => [
            'sub' => 'test', 'name' => 'Test', 'email' => 'test@example.test',
            'nomor_identitas' => '199999999999999001',
        ]]);

        $old = WebNotification::create(['message' => 'lama']);
        $new = WebNotification::create(['message' => 'baru']);

        $response = $this->getJson('/notifications/latest?after_id='.$old->id);

        $response->assertOk();
        $response->assertJson([
            'latest_id' => $new->id,
            'notifications' => [
                ['id' => $new->id, 'message' => 'baru'],
            ],
        ]);
    }

    public function test_notifications_latest_bootstrap_reports_the_latest_id_without_a_list(): void
    {
        $this->withSession(['sso_user' => [
            'sub' => 'test', 'name' => 'Test', 'email' => 'test@example.test',
            'nomor_identitas' => '199999999999999001',
        ]]);

        $notif = WebNotification::create(['message' => 'sudah ada sebelum kunjungan pertama']);

        $response = $this->getJson('/notifications/latest?bootstrap=1');

        $response->assertOk();
        $response->assertExactJson([
            'latest_id' => $notif->id,
            'notifications' => [],
        ]);
    }
}
