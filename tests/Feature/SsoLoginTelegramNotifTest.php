<?php

namespace Tests\Feature;

use App\Models\Penugasan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Login/akses SAPTO -- baik yang berhasil maupun yang ditolak -- mengirim
 * alert ke chat Telegram yang sama dengan alert error 5xx (lihat
 * SsoController::notifyLogin()/logAccessDenied()), berisi siapa dan IP-nya.
 */
class SsoLoginTelegramNotifTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local'); // jangan sentuh telegram_chat_ids.json yang asli
        Storage::disk('local')->put('telegram_chat_ids.json', json_encode(['111']));

        config(['services.telegram.bot_token' => 'dummy-token']);
    }

    private function ssoCallback(array $userinfo, string $ip = '203.0.113.7')
    {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['access_token' => 'abc', 'id_token' => 'idt']),
            '*/protocol/openid-connect/userinfo' => Http::response($userinfo),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        return $this->withSession(['sso_state' => 'st'])
            ->from('/')
            ->get('/auth/callback?code=kode&state=st', ['REMOTE_ADDR' => $ip]);
    }

    public function test_a_successful_login_sends_a_telegram_alert_with_the_name_and_ip(): void
    {
        Penugasan::create(['peran' => 'korprodi', 'nip' => '199999999999999001', 'nama' => 'Budi', 'lingkup' => '55201']);

        $this->ssoCallback(['sub' => '1', 'name' => 'Budi', 'email' => 'budi@example.test', 'nomor_identitas' => '199999999999999001'], '203.0.113.7')
            ->assertRedirect('/');

        Http::assertSent(function ($request) {
            return str_contains((string) $request->url(), 'api.telegram.org')
                && str_contains((string) $request['text'], 'Login SAPTO')
                && str_contains((string) $request['text'], 'Budi')
                && str_contains((string) $request['text'], 'Korprodi')
                && str_contains((string) $request['text'], '203.0.113.7');
        });
    }

    public function test_an_admin_login_is_labelled_admin_in_the_alert(): void
    {
        User::create([
            'name' => 'Admin SAPTO',
            'email' => 'admin@unsri.ac.id',
            'nomor_identitas' => '9999999',
            'is_admin' => true,
            'password' => Hash::make(Str::random(64)),
        ]);

        $response = $this->ssoCallback(['sub' => '2', 'name' => 'Admin SAPTO', 'email' => 'admin@unsri.ac.id', 'nomor_identitas' => '9999999']);

        $response->assertRedirect('/');
        Http::assertSent(fn ($request) => str_contains((string) ($request['text'] ?? ''), 'Admin SAPTO — Admin'));

        // Login admin dapat SATU alert tambahan di WEBSITE (toast, lihat
        // sapto.blade.php), bukan Telegram lagi -- akses admin lebih
        // sensitif (scope 'universitas') daripada korprodi/dekan/rektor,
        // jadi orang yang login perlu sadar dia sedang masuk dengan hak
        // penuh saat itu juga.
        $this->assertTrue(session('admin_login_alert'));
    }

    public function test_a_non_admin_login_does_not_flash_the_admin_alert(): void
    {
        Penugasan::create(['peran' => 'korprodi', 'nip' => '199999999999999001', 'nama' => 'Budi', 'lingkup' => '55201']);

        $this->ssoCallback(['sub' => '1', 'name' => 'Budi', 'email' => 'budi@example.test', 'nomor_identitas' => '199999999999999001'])
            ->assertRedirect('/');

        $this->assertFalse(session()->has('admin_login_alert'));
    }

    public function test_the_dashboard_shows_the_admin_toast_when_the_alert_is_flashed(): void
    {
        $this->withSession([
            'sso_user' => ['sub' => 'x', 'name' => 'Admin', 'email' => 'admin@unsri.ac.id', 'is_admin' => true],
            'admin_login_alert' => true,
        ]);

        $this->get('/')->assertOk()->assertSee('Anda masuk sebagai Admin', false);
    }

    public function test_a_denied_access_attempt_sends_a_separate_telegram_alert(): void
    {
        $this->ssoCallback(['sub' => '3', 'name' => 'Firdaus Masdung', 'email' => 'firdaus@example.test', 'nomor_identitas' => '000'], '198.51.100.9')
            ->assertRedirect('/');

        Http::assertSent(function ($request) {
            return str_contains((string) $request->url(), 'api.telegram.org')
                && str_contains((string) $request['text'], 'Akses SAPTO ditolak')
                && str_contains((string) $request['text'], 'Firdaus Masdung')
                && str_contains((string) $request['text'], '198.51.100.9');
        });
    }
}
