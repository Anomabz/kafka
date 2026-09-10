<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Korprodi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Keycloak OIDC login (realm "unsri", client "app-sapto").
 * Port of public/test-sso.php into the Laravel session.
 */
class SsoController extends Controller
{
    public static function base(): string
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

        $params = [
            'client_id' => config('services.sso.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
        ];

        return redirect()->away(self::base().'/auth?'.http_build_query($params));
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

        $token = Http::asForm()->post(self::base().'/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.sso.client_id'),
            'client_secret' => config('services.sso.client_secret'),
            'code' => $request->input('code'),
            'redirect_uri' => $this->redirectUri(),
        ]);

        if ($token->failed() || ! $token->json('access_token')) {
            abort(400, 'Gagal menukar authorization code menjadi token.');
        }

        $userinfo = Http::withToken($token->json('access_token'))->get(self::base().'/userinfo');
        if ($userinfo->failed()) {
            abort(400, 'Gagal mengambil data pengguna dari SSO.');
        }

        $ssoUser = $this->ssoSessionUser($userinfo->json());

        // Admin SAPTO tidak wajib ada di tabel korprodi — hak aksesnya
        // universitas, bukan per prodi.
        if (! $ssoUser['is_admin'] && ! $this->isKorprodi($ssoUser)) {
            $this->logAccessDenied($ssoUser, (string) $userinfo->json('preferred_username'));

            return redirect('/')->with('not_korprodi_hint', $userinfo->json('name') ?: $userinfo->json('preferred_username'));
        }

        $request->session()->regenerate();
        $request->session()->put('sso_user', $ssoUser);
        $request->session()->put('sso_id_token', $token->json('id_token'));
        // Dipakai EnsureSsoAuthenticated untuk mendeteksi logout yang terjadi
        // di aplikasi kampus lain (single logout).
        $request->session()->put('sso_refresh_token', $token->json('refresh_token'));
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
     * (is_admin = true) mendapat akses penuh 'universitas' — lihat
     * SaptoUserContext::fromRequest().
     *
     * Dicocokkan lewat nomor_identitas ATAU email, karena realm tidak selalu
     * mengirim `nomor_identitas`: log 2026-09-10 mencatat akun SSO yang valid
     * ditolak dengan klaim itu null. Email selalu ada dan sama-sama dijamin
     * Keycloak,
     * jadi aman dipakai sebagai kunci kedua. Identitas dijamin Keycloak, jadi
     * `users` cuma berperan sebagai daftar "siapa yang admin" — kolom password
     * tidak dipakai sama sekali dan boleh acak.
     */
    private function isSaptoAdmin(array $userinfo): bool
    {
        $nip = trim((string) ($userinfo['nomor_identitas'] ?? ''));
        $email = trim((string) ($userinfo['email'] ?? ''));

        // Tanpa satu pun kunci, jangan pernah menjalankan query -- where()
        // dengan closure kosong akan cocok ke SEMUA admin.
        if ($nip === '' && $email === '') {
            return false;
        }

        return User::query()
            ->where('is_admin', true)
            ->where(function ($q) use ($nip, $email) {
                if ($nip !== '') {
                    $q->orWhere('nomor_identitas', $nip);
                }
                if ($email !== '') {
                    $q->orWhere('email', $email);
                }
            })
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

    public function logout(Request $request)
    {
        $idToken = $request->session()->pull('sso_id_token');
        $request->session()->flush();
        $request->session()->regenerate();

        return redirect()->away(self::base().'/logout?'.http_build_query(array_filter([
            'client_id' => config('services.sso.client_id'),
            'post_logout_redirect_uri' => url('/'),
            'id_token_hint' => $idToken,
        ])));
    }
}
