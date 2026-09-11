<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Captcha Cloudflare Turnstile di tombol "Masuk". Diverifikasi server
 * sebelum pengunjung dialihkan ke Keycloak (SsoController::login).
 */
class SsoCaptchaTest extends TestCase
{
    use RefreshDatabase;

    private const SITEVERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private function captchaAktif(): void
    {
        config(['services.turnstile' => ['site_key' => 'site-uji', 'secret_key' => 'rahasia-uji']]);
    }

    private function captchaMati(): void
    {
        config(['services.turnstile' => ['site_key' => null, 'secret_key' => null]]);
    }

    public function test_welcome_page_shows_the_widget_and_locks_masuk_until_solved(): void
    {
        $this->captchaAktif();

        $this->get('/')
            ->assertOk()
            ->assertSee('turnstile/v0/api.js', false)
            ->assertSee('data-sitekey="site-uji"', false)
            ->assertSee('id="login-submit" class="auth-cta"  disabled', false);
    }

    public function test_without_keys_there_is_no_widget_and_masuk_is_open(): void
    {
        $this->captchaMati();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('turnstile/v0/api.js', false)
            ->assertDontSee(' disabled>', false);
    }

    public function test_only_one_key_counts_as_disabled_so_nobody_gets_locked_out(): void
    {
        // Site key tanpa secret: widget tampil tapi server tak bisa
        // memverifikasi apa pun -- lebih baik captcha dianggap mati.
        config(['services.turnstile' => ['site_key' => 'site-uji', 'secret_key' => null]]);

        $this->get('/')->assertOk()->assertDontSee('data-sitekey', false);
    }

    public function test_solved_captcha_continues_to_keycloak(): void
    {
        $this->captchaAktif();
        Http::fake([self::SITEVERIFY => Http::response(['success' => true])]);

        $response = $this->post('/auth/login', ['cf-turnstile-response' => 'token-sah']);

        $this->assertStringContainsString('/protocol/openid-connect/auth?', (string) $response->headers->get('Location'));
        $this->assertNotNull(session('sso_state'));
        Http::assertSent(fn ($req) => $req->url() === self::SITEVERIFY && $req['secret'] === 'rahasia-uji' && $req['response'] === 'token-sah');
    }

    public function test_rejected_captcha_goes_back_without_starting_login(): void
    {
        $this->captchaAktif();
        Http::fake([self::SITEVERIFY => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        $this->post('/auth/login', ['cf-turnstile-response' => 'token-palsu'])
            ->assertRedirect('/')
            ->assertSessionHas('captcha_gagal');

        // Tanpa sso_state, /auth/callback juga pasti menolak -- captcha
        // tidak bisa dilompati lewat callback.
        $this->assertNull(session('sso_state'));
        $this->get('/')->assertSee('Verifikasi keamanan belum berhasil');
    }

    public function test_missing_token_is_rejected_without_asking_cloudflare(): void
    {
        $this->captchaAktif();
        Http::fake();

        $this->post('/auth/login')->assertRedirect('/')->assertSessionHas('captcha_gagal');

        Http::assertNothingSent();
    }

    public function test_cloudflare_being_unreachable_does_not_lock_everyone_out(): void
    {
        // Captcha di sini bukan pertahanan password (Keycloak tetap
        // mengautentikasi), jadi gangguan jaringan ke Cloudflare tidak boleh
        // membuat SAPTO tidak bisa dimasuki sama sekali.
        $this->captchaAktif();
        Log::spy();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $response = $this->post('/auth/login', ['cf-turnstile-response' => 'token']);

        $this->assertStringContainsString('/protocol/openid-connect/auth?', (string) $response->headers->get('Location'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'Turnstile tidak bisa dihubungi'));
    }

    public function test_without_keys_masuk_goes_straight_to_keycloak(): void
    {
        $this->captchaMati();
        Http::fake();

        $response = $this->post('/auth/login');

        $this->assertStringContainsString('/protocol/openid-connect/auth?', (string) $response->headers->get('Location'));
        Http::assertNothingSent();
    }

    public function test_get_auth_login_cannot_be_used_to_skip_the_captcha(): void
    {
        $this->captchaAktif();

        $this->get('/auth/login')->assertRedirect('/');
        $this->assertNull(session('sso_state'));
    }
}
