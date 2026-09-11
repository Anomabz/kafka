<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Penugasan;
use App\Models\User;
use App\Support\TelegramNotifier;
use Illuminate\Http\Client\ConnectionException;
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

    /**
     * Site key reCAPTCHA bila captcha aktif, null bila tidak. Aktif hanya
     * kalau KEDUA kunci terisi: site key tanpa secret berarti widget tampil
     * tapi tidak ada yang bisa lolos verifikasi -- semua orang terkunci.
     */
    public static function recaptchaSiteKey(): ?string
    {
        $c = config('services.recaptcha');

        return ($c['site_key'] ?? null) && ($c['secret_key'] ?? null) ? (string) $c['site_key'] : null;
    }

    /**
     * Tombol "Masuk" di welcome page (POST). Captcha diverifikasi di sini,
     * sebelum dialihkan ke Keycloak -- dan karena `sso_state` baru dibuat
     * setelah itu, /auth/callback tanpa captcha yang lolos otomatis ditolak.
     *
     * Catatan: password tetap diketik di halaman Keycloak, jadi captcha ini
     * menahan bot yang membanjiri alur login SAPTO, bukan penebak password.
     */
    public function login(Request $request)
    {
        if (self::recaptchaSiteKey() !== null && ! $this->captchaLolos((string) $request->input('g-recaptcha-response'))) {
            return redirect('/')->with('captcha_gagal', 'Verifikasi keamanan belum berhasil. Selesaikan captcha, lalu tekan Masuk lagi.');
        }

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

    /**
     * Verifikasi token reCAPTCHA ke Google.
     *
     * Google yang tidak bisa dihubungi sengaja dianggap LOLOS (dicatat ke
     * log): captcha di sini bukan pertahanan password -- Keycloak tetap
     * mengautentikasi -- jadi gangguan jaringan tidak boleh mengunci semua
     * orang keluar dari SAPTO. Jawaban "gagal" dari Google tetap ditolak.
     */
    private function captchaLolos(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $res = Http::asForm()->timeout(8)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $token,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('reCAPTCHA tidak bisa dihubungi; captcha dilewati.', ['error' => $e->getMessage()]);

            return true;
        }

        if (! $res->json('success')) {
            Log::info('Captcha reCAPTCHA ditolak.', ['error_codes' => $res->json('error-codes')]);

            return false;
        }

        return true;
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

        // Admin SAPTO tidak wajib punya penugasan — hak aksesnya universitas.
        if (! $ssoUser['is_admin'] && ! $this->hasPenugasan($ssoUser)) {
            $this->logAccessDenied($ssoUser, (string) $userinfo->json('preferred_username'), $request);

            return redirect('/')->with('not_korprodi_hint', $userinfo->json('name') ?: $userinfo->json('preferred_username'));
        }

        $request->session()->regenerate();
        $request->session()->put('sso_user', $ssoUser);
        $request->session()->put('sso_id_token', $token->json('id_token'));
        // Dipakai EnsureSsoAuthenticated untuk mendeteksi logout yang terjadi
        // di aplikasi kampus lain (single logout).
        $request->session()->put('sso_refresh_token', $token->json('refresh_token'));
        $request->session()->flash('show_intro', true);

        $this->notifyLogin($ssoUser, $request);

        return redirect()->intended('/');
    }

    /**
     * Alert Telegram tiap login berhasil: siapa dan dari IP mana. Dikirim ke
     * chat id yang sama dengan alert error 5xx (TelegramNotifier::chatIds()),
     * bukan lewat siaran /notif ke website -- arahnya kebalik (dari SAPTO ke
     * admin, bukan dari admin ke pengguna web).
     */
    private function notifyLogin(array $ssoUser, Request $request): void
    {
        $peran = $ssoUser['is_admin']
            ? 'Admin'
            : (Penugasan::LABEL_PERAN[Penugasan::peranTerluas(
                Penugasan::query()->untukIdentitas($ssoUser['nomor_identitas'] ?? null, $ssoUser['email'] ?? null)->get()
            )] ?? '-');

        $pesan = sprintf(
            "🔓 <b>Login SAPTO</b>\n%s — %s\n%s\nIP: <code>%s</code>",
            e($ssoUser['name'] ?? $ssoUser['preferred_username'] ?? '(tanpa nama)'),
            e($peran),
            e($ssoUser['email'] ?? '-'),
            $request->ip(),
        );

        TelegramNotifier::broadcast($pesan);

        // Akun admin punya akses penuh ke seluruh data SAPTO (scope
        // 'universitas', lihat SaptoUserContext) -- login-nya lebih sensitif
        // daripada korprodi/dekan/rektor biasa, jadi ditambah satu alert
        // terpisah yang lebih mencolok supaya tidak tenggelam di antara
        // notifikasi login rutin.
        if ($ssoUser['is_admin']) {
            TelegramNotifier::broadcast(sprintf(
                "🚨 <b>ADMIN LOGIN</b>\nAkun berhak penuh atas seluruh data SAPTO baru saja masuk.\n%s (%s)\nIP: <code>%s</code>",
                e($ssoUser['name'] ?? $ssoUser['preferred_username'] ?? '(tanpa nama)'),
                e($ssoUser['email'] ?? '-'),
                $request->ip(),
            ));
        }
    }

    /**
     * SAPTO hanya bisa diakses akun yang didaftarkan admin di /admin/peran
     * (korprodi, dekan, atau rektor) — deny-by-default yang sama dengan
     * SaptoUserContext::fromRequest(), tapi ditegakkan sedini mungkin (saat
     * login) alih-alih membiarkan akun SSO yang sah tapi tidak terdaftar masuk
     * ke shell aplikasi lebih dulu.
     */
    private function hasPenugasan(array $userinfo): bool
    {
        return Penugasan::query()
            ->untukIdentitas($userinfo['nomor_identitas'] ?? null, $userinfo['email'] ?? null)
            ->exists();
    }

    /**
     * Kredensialnya benar di Keycloak tapi akunnya tidak terdaftar di SAPTO.
     *
     * Yang dicatat adalah `nomor_identitas` dan `email` yang BENAR-BENAR
     * dikirim Keycloak, karena itulah satu-satunya cara tahu kenapa baris di
     * /admin/peran tidak kena: format NIP berbeda, klaim NIP kosong, atau
     * email yang didaftarkan admin tidak sama persis.
     *
     * @param  array<string, mixed>  $ssoUser
     */
    private function logAccessDenied(array $ssoUser, string $username, Request $request): void
    {
        Log::warning('Login SSO ditolak: akun tidak terdaftar di penugasan/admin SAPTO.', [
            'username' => $username,
            'email_dari_sso' => $ssoUser['email'] ?? null,
            'nomor_identitas_dari_sso' => $ssoUser['nomor_identitas'] ?? null,
            'panjang_nomor_identitas' => strlen(trim((string) ($ssoUser['nomor_identitas'] ?? ''))),
            'unit_kerja' => $ssoUser['unit_kerja'] ?? null,
        ]);

        $pesan = sprintf(
            "⛔ <b>Akses SAPTO ditolak</b>\n%s\n%s\nIP: <code>%s</code>",
            e($ssoUser['name'] ?? $username ?: '(tanpa nama)'),
            e($ssoUser['email'] ?? '-'),
            $request->ip(),
        );

        TelegramNotifier::broadcast($pesan);
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
