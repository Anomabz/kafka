<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Root adalah satu-satunya pintu masuk: tamu dilayani welcome page di URL
     * itu juga (tanpa redirect ke /login, tanpa dilempar ke domain Keycloak),
     * jadi address bar tetap "sapto.unsri.ac.id".
     */
    public function test_root_serves_the_welcome_page_without_redirecting(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('sso.login'), false);
        $response->assertDontSee('type="password"', false);
    }

    /**
     * /login sudah tidak punya halaman sendiri -- disisakan cuma untuk
     * bookmark lama.
     */
    public function test_the_old_login_url_redirects_to_root(): void
    {
        $this->get('/login')->assertRedirect('/');
    }

    /**
     * Halaman dalam yang dibuka tanpa sesi dipantulkan ke root, dan alamat
     * aslinya disimpan supaya user kembali ke sana setelah login.
     */
    public function test_a_deep_link_without_a_session_bounces_to_root_and_is_remembered(): void
    {
        $response = $this->get('/admin/peran');

        $response->assertRedirect('/');
        $this->assertSame(url('/admin/peran'), session('url.intended'));
    }
}
