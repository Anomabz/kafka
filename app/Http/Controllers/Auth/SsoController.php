<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Korprodi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public function showLoginForm(Request $request)
    {
        if ($request->session()->has('sso_user')) {
            return redirect('/');
        }

        return view('auth.login', [
            'unregisteredHint' => $request->session()->get('unregistered_hint'),
            'notKorprodiHint' => $request->session()->get('not_korprodi_hint'),
        ]);
    }

    /**
     * NIM/username + password typed on our own page, validated against
     * Keycloak via the Resource Owner Password Credentials grant. Requires
     * "Direct Access Grants" enabled on the app-sapto client. Any failure —
     * wrong credentials, account needs something only Keycloak's hosted UI
     * can do (required actions, MFA, ...), direct grants disabled — sends
     * the visitor back to /login, which shows a popup that redirects to the
     * hosted SSO login after a short countdown (or immediately via its
     * "Register Sekarang" button).
     */
    public function authenticate(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($adminRedirect = $this->attemptLocalAdminLogin($request, $data)) {
            return $adminRedirect;
        }

        $token = Http::asForm()->post($this->base().'/token', [
            'grant_type' => 'password',
            'client_id' => config('services.sso.client_id'),
            'client_secret' => config('services.sso.client_secret'),
            'username' => $data['username'],
            'password' => $data['password'],
            'scope' => 'openid profile email',
        ]);

        if ($token->failed() || ! $token->json('access_token')) {
            return redirect()->route('login')->with('unregistered_hint', $data['username']);
        }

        $userinfo = Http::withToken($token->json('access_token'))->get($this->base().'/userinfo');
        if ($userinfo->failed()) {
            return redirect()->route('login')->with('unregistered_hint', $data['username']);
        }

        $ssoUser = $this->ssoSessionUser($userinfo->json());

        // Admin SAPTO tidak wajib ada di tabel korprodi — hak aksesnya
        // universitas, bukan per prodi.
        if (! $ssoUser['is_admin'] && ! $this->isKorprodi($ssoUser)) {
            $this->logAccessDenied($ssoUser, $data['username']);

            return redirect()->route('login')->with('not_korprodi_hint', $userinfo->json('name') ?: $data['username']);
        }

        $request->session()->regenerate();
        $request->session()->put('sso_user', $ssoUser);
        $request->session()->put('sso_id_token', $token->json('id_token'));
        $request->session()->flash('show_intro', true);

        return redirect()->intended('/');
    }

    public function login(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('sso_state', $state);

        $params = [
            'client_id' => config('services.sso.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
        ];

        if ($request->filled('login_hint')) {
            $params['login_hint'] = $request->string('login_hint');
        }

        return redirect()->away($this->base().'/auth?'.http_build_query($params));
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

        $ssoUser = $this->ssoSessionUser($userinfo->json());

        // Sama seperti authenticate(): admin lolos tanpa harus jadi korprodi.
        if (! $ssoUser['is_admin'] && ! $this->isKorprodi($ssoUser)) {
            $this->logAccessDenied($ssoUser, (string) $userinfo->json('preferred_username'));

            return redirect()->route('login')->with('not_korprodi_hint', $userinfo->json('name') ?: $userinfo->json('preferred_username'));
        }

        $request->session()->regenerate();
        $request->session()->put('sso_user', $ssoUser);
        $request->session()->put('sso_id_token', $token->json('id_token'));
        $request->session()->flash('show_intro', true);

        return redirect()->intended('/');
    }

    /**
     * SAPTO hanya bisa diakses koordinator program studi (korprodi) yang NIP
     * (nomor_identitas)-nya terdaftar di tabel korprodi — deny-by-default yang
     * sama dengan SaptoUserContext::fromRequest(), tapi ditegakkan sedini
     * mungkin (saat login) alih-alih membiarkan akun yang valid di SSO tapi
     * tak terdaftar sebagai korprodi masuk ke shell aplikasi lebih dulu.
     */
    private function isKorprodi(array $userinfo): bool
    {
        $nip = trim((string) ($userinfo['nomor_identitas'] ?? ''));

        return $nip !== '' && Korprodi::query()->where('nip', $nip)->exists();
    }

    /**
     * Kredensialnya benar di Keycloak tapi NIP-nya tidak dikenali SAPTO.
     *
     * Yang dicatat adalah `nomor_identitas` yang BENAR-BENAR dikirim Keycloak,
     * karena itulah satu-satunya cara memastikan formatnya sama dengan kolom
     * `korprodi.nip` (18 digit). Kalau realm ternyata mengirim NIDN atau ID
     * pegawai yang lebih pendek, seluruh korprodi akan tertolak dan penyebabnya
     * cuma kelihatan dari sini.
     *
     * @param  array<string, mixed>  $ssoUser
     */
    private function logAccessDenied(array $ssoUser, string $username): void
    {
        Log::warning('Login SSO ditolak: NIP tidak terdaftar sebagai korprodi/admin SAPTO.', [
            'username' => $username,
            'nomor_identitas_dari_sso' => $ssoUser['nomor_identitas'] ?? null,
            'panjang_nomor_identitas' => strlen(trim((string) ($ssoUser['nomor_identitas'] ?? ''))),
            'unit_kerja' => $ssoUser['unit_kerja'] ?? null,
        ]);
    }

    /**
     * Akun SSO yang terdaftar sebagai admin SAPTO di tabel `users`
     * (is_admin = true, dicocokkan lewat nomor_identitas) mendapat akses
     * penuh 'universitas' — lihat SaptoUserContext::fromRequest().
     *
     * Bedanya dengan attemptLocalAdminLogin(): di sana tabel `users` dipakai
     * untuk OTENTIKASI (cek hash password) karena akunnya memang di luar SSO.
     * Di sini identitas sudah dijamin Keycloak, jadi `users` cuma berperan
     * sebagai daftar "siapa yang admin" — passwordnya tidak dipakai sama
     * sekali dan boleh acak.
     */
    private function isSaptoAdmin(array $userinfo): bool
    {
        $nip = trim((string) ($userinfo['nomor_identitas'] ?? ''));

        return $nip !== '' && User::query()
            ->where('is_admin', true)
            ->where('nomor_identitas', $nip)
            ->exists();
    }

    /**
     * Bentuk isi session `sso_user` dari userinfo Keycloak.
     *
     * `is_admin` SELALU ditulis ulang dari hasil pengecekan tabel users, tidak
     * pernah diambil dari respons Keycloak — kalau realm suatu saat mengirim
     * klaim bernama sama, klaim itu tidak boleh bisa menaikkan hak akses.
     *
     * @param  array<string, mixed>  $userinfo
     * @return array<string, mixed>
     */
    private function ssoSessionUser(array $userinfo): array
    {
        $userinfo['is_admin'] = $this->isSaptoAdmin($userinfo);

        return $userinfo;
    }

    /**
     * Login lokal khusus developer SAPTO — bukan lewat Keycloak sama sekali
     * (akunnya memang tidak ada di SSO kampus). Kredensial dicek terhadap
     * hash bcrypt di kolom users.password (Hash::check), bukan Keycloak.
     * Akun dengan is_admin=true dapat akses penuh semua fakultas/prodi —
     * lihat SaptoUserContext::fromRequest(), yang mengenali sesi ini lewat
     * flag 'is_admin' dan memberi role 'universitas' tanpa cek tabel korprodi.
     */
    private function attemptLocalAdminLogin(Request $request, array $data): ?RedirectResponse
    {
        $admin = User::query()
            ->where('is_admin', true)
            ->where('nomor_identitas', $data['username'])
            ->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            return null;
        }

        $request->session()->regenerate();
        $request->session()->put('sso_user', [
            'sub' => 'local-admin-'.$admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'nomor_identitas' => $admin->nomor_identitas,
            'is_admin' => true,
        ]);
        $request->session()->flash('show_intro', true);

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
