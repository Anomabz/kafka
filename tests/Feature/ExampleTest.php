<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * '/' sits behind sso.auth (see routes/web.php) — an unauthenticated
     * visitor is redirected to SSO login, not served the page directly.
     */
    public function test_unauthenticated_visitor_is_redirected_to_sso_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('sso.login'));
    }
}
