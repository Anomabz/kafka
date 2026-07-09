<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Keycloak OIDC login (realm "unsri", client "app-sapto").
 * Port of public/test-sso.php into the Laravel session.
 */
class SsoController extends Controller
{
    private function base(): string
    {
        $c = config('services.sso');

        return $c['base_url'].'/realms/'.$c['realm'].'/protocol/openid-connect';
    }

    private function redirectUri(): string
    {
        return config('services.sso.redirect_uri') ?: route('sso.callback');
    }

    public function login(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('sso_state', $state);

        return redirect()->away($this->base().'/auth?'.http_build_query([
            'client_id' => config('services.sso.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
        ]));
    }

    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            abort(400, 'Login SSO gagal: '.$request->input('error_description', $request->input('error')));
        }

        $expected = $request->session()->pull('sso_state');
        if (! $request->filled('code') || ! $expected || ! hash_equals($expected, (string) $request->input('state'))) {
            abort(400, 'State OAuth tidak valid. Silakan ulangi login.');
        }

        $token = Http::asForm()->post($this->base().'/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.sso.client_id'),
            'client_secret' => config('services.sso.client_secret'),
            'code' => $request->input('code'),
            'redirect_uri' => $this->redirectUri(),
        ]);

        if ($token->failed() || ! $token->json('access_token')) {
            abort(400, 'Gagal menukar authorization code menjadi token.');
        }

        $userinfo = Http::withToken($token->json('access_token'))->get($this->base().'/userinfo');
        if ($userinfo->failed()) {
            abort(400, 'Gagal mengambil data pengguna dari SSO.');
        }

        $request->session()->regenerate();
        $request->session()->put('sso_user', $userinfo->json());
        $request->session()->put('sso_id_token', $token->json('id_token'));

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        $idToken = $request->session()->pull('sso_id_token');
        $request->session()->flush();
        $request->session()->regenerate();

        return redirect()->away($this->base().'/logout?'.http_build_query(array_filter([
            'client_id' => config('services.sso.client_id'),
            'post_logout_redirect_uri' => url('/'),
            'id_token_hint' => $idToken,
        ])));
    }
}
