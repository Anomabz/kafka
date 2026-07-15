<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoPasswordLoginTest extends TestCase
{
    public function test_correct_credentials_create_a_session_and_redirect_home(): void
    {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['access_token' => 'abc', 'id_token' => 'idt']),
            '*/protocol/openid-connect/userinfo' => Http::response(['sub' => '1', 'name' => 'Budi', 'email' => 'budi@example.test']),
        ]);

        $response = $this->post('/login', ['username' => 'budi', 'password' => 'right']);

        $response->assertRedirect('/');
        $this->assertSame('Budi', session('sso_user')['name']);
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
