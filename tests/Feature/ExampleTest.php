<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * '/' sits behind sso.auth (see routes/web.php) — an unauthenticated
     * visitor lands on the app's own branded /login page, not straight on
     * the external Keycloak domain.
     */
    public function test_unauthenticated_visitor_is_redirected_to_local_login_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    /**
     * The /login page renders SAPTO's own form — it must not redirect to
     * Keycloak automatically. Falling back to the hosted SSO page only
     * happens after a failed submit (see SsoPasswordLoginTest).
     */
    public function test_login_page_renders_its_own_form_without_redirecting(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(route('login.attempt'), false);
    }
}
