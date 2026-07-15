<?php

namespace Tests\Feature;

use App\Models\Korprodi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoPasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_credentials_create_a_session_and_redirect_home(): void
    {
        Korprodi::create(['nip' => '199999999999999001', 'nama' => 'Budi', 'kode_program_studi' => '55201']);

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['access_token' => 'abc', 'id_token' => 'idt']),
            '*/protocol/openid-connect/userinfo' => Http::response(['sub' => '1', 'name' => 'Budi', 'email' => 'budi@example.test', 'nomor_identitas' => '199999999999999001']),
        ]);

        $response = $this->post('/login', ['username' => 'budi', 'password' => 'right']);

        $response->assertRedirect('/');
        $this->assertSame('Budi', session('sso_user')['name']);
    }

    public function test_credentials_valid_at_sso_but_not_a_korprodi_are_denied(): void
    {
        // Akun ini benar-benar valid di Keycloak (token+userinfo sukses),
        // tapi NIP-nya tidak ada di tabel korprodi -> tetap ditolak, bukan
        // cuma dibiarkan masuk lalu ditolak nanti saat pertama kali memanggil
        // API (lihat SsoController::isKorprodi()).
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['access_token' => 'abc', 'id_token' => 'idt']),
            '*/protocol/openid-connect/userinfo' => Http::response(['sub' => '2', 'name' => 'Firdaus Masdung', 'email' => 'firdaus@example.test', 'nomor_identitas' => '9999999']),
        ]);

        $response = $this->post('/login', ['username' => 'firdaus', 'password' => 'right']);

        $response->assertRedirect(route('login'));
        $this->assertSame('Firdaus Masdung', session('not_korprodi_hint'));
        $this->assertFalse(session()->has('sso_user'));

        $follow = $this->get(route('login'));
        $follow->assertOk();
        $follow->assertSee('Akses ditolak');
        $follow->assertSee('Firdaus Masdung');
    }

    public function test_wrong_credentials_send_back_to_login_with_unregistered_popup(): void
    {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(
                ['error' => 'invalid_grant', 'error_description' => 'Invalid user credentials'],
                401
            ),
        ]);

        $response = $this->post('/login', ['username' => 'budi', 'password' => 'wrong']);

        $response->assertRedirect(route('login'));
        $this->assertSame('budi', session('unregistered_hint'));
        $this->assertFalse(session()->has('sso_user'));

        $follow = $this->get(route('login'));
        $follow->assertOk();
        $follow->assertSee('Akun tidak terdaftar');
        $follow->assertSee(route('sso.login', ['login_hint' => 'budi']), false);
    }

    public function test_account_that_needs_keycloaks_own_ui_shows_unregistered_popup(): void
    {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(
                ['error' => 'invalid_grant', 'error_description' => 'Account is not fully set up'],
                400
            ),
        ]);

        $response = $this->post('/login', ['username' => 'budi', 'password' => 'right']);

        $response->assertRedirect(route('login'));
        $this->assertSame('budi', session('unregistered_hint'));
    }
}
