<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Logout di aplikasi kampus lain mengakhiri sesi SSO di Keycloak. SAPTO tidak
 * diberi tahu, jadi EnsureSsoAuthenticated yang menanyakan ulang lewat refresh
 * token (lihat middleware itu).
 */
class SsoSingleLogoutTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function liveSession(array $extra = []): array
    {
        return array_merge([
            'sso_user' => ['sub' => '1', 'name' => 'Budi', 'is_admin' => true],
            'sso_refresh_token' => 'rt-lama',
            'sso_checked_until' => 0,
        ], $extra);
    }

    public function test_session_ended_at_keycloak_logs_the_user_out_of_sapto(): void
    {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $response = $this->withSession($this->liveSession())->get('/');

        // Sesi dibuang, dan yang tampil di URL yang sama adalah welcome page.
        $response->assertOk();
        $response->assertSee(route('sso.login'), false);
        $this->assertFalse(session()->has('sso_user'));
    }

    public function test_session_still_alive_keeps_the_user_in_and_rotates_the_refresh_token(): void
    {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt-baru']),
        ]);

        $this->withSession($this->liveSession())->get('/')->assertOk();

        $this->assertSame('rt-baru', session('sso_refresh_token'));
        $this->assertGreaterThan(now()->timestamp, session('sso_checked_until'));
    }

    public function test_check_is_throttled_so_keycloak_is_not_hit_every_request(): void
    {
        Http::fake();

        $this->withSession($this->liveSession(['sso_checked_until' => now()->timestamp + 60]))
            ->get('/')
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_keycloak_being_unreachable_does_not_log_anyone_out(): void
    {
        // Jaringan ngadat != user sudah logout. Kalau ini salah, satu Keycloak
        // yang down menendang seluruh pengguna SAPTO sekaligus.
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->withSession($this->liveSession())->get('/')->assertOk();

        $this->assertTrue(session()->has('sso_user'));
    }

    public function test_api_request_with_a_dead_sso_session_gets_401(): void
    {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->withSession($this->liveSession())
            ->getJson('/api/sapto/fakultas')
            ->assertStatus(401);
    }
}
