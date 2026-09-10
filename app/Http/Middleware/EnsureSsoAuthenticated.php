<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\SsoController;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EnsureSsoAuthenticated
{
    /**
     * Jeda antar pengecekan sesi SSO ke Keycloak.
     *
     * ponytail: satu round-trip (~200ms dari host ini) per user per menit,
     * bukan per request. Konsekuensinya logout dari aplikasi lain baru terasa
     * paling lama 60 detik kemudian — kecilkan angkanya kalau perlu lebih
     * cepat, dengan ongkos request Keycloak yang lebih sering.
     */
    private const CHECK_INTERVAL = 60;

    public function handle(Request $request, Closure $next)
    {
        if ($request->session()->has('sso_user') && ! $this->ssoSessionStillAlive($request)) {
            $request->session()->flush();
        }

        if (! $request->session()->has('sso_user')) {
            if ($request->expectsJson() || $request->is('api/*')) {
                abort(401, 'Sesi tidak valid. Silakan login ulang.');
            }

            // Tamu di root dapat welcome page langsung di URL yang dia buka --
            // address bar tetap "sapto.unsri.ac.id", tanpa /login. Halaman
            // dalam tetap dipantulkan ke root dulu, dan url.intended membawa
            // mereka kembali ke sana setelah login (lihat SsoController).
            if ($request->is('/')) {
                return response()->view('auth.login', [
                    'notKorprodiHint' => $request->session()->get('not_korprodi_hint'),
                ]);
            }

            $request->session()->put('url.intended', $request->fullUrl());

            return redirect('/');
        }

        return $next($request);
    }

    /**
     * Logout di aplikasi kampus lain mengakhiri sesi SSO di Keycloak, tapi
     * cookie session Laravel milik SAPTO tetap hidup — jadi sesi lokal harus
     * ditanyakan ulang, tidak bisa cuma dipercaya. Refresh token adalah
     * cerminan langsung sesi SSO itu: begitu sesinya berakhir (logout di mana
     * pun, atau ditendang admin realm), refresh grant dijawab invalid_grant.
     */
    private function ssoSessionStillAlive(Request $request): bool
    {
        $session = $request->session();
        $refreshToken = $session->get('sso_refresh_token');

        // Sesi lama dari sebelum fitur ini ada (tanpa refresh token) dibiarkan
        // jalan sampai kedaluwarsa sendiri.
        if (! $refreshToken || now()->timestamp < (int) $session->get('sso_checked_until')) {
            return true;
        }

        try {
            $token = Http::asForm()->post(SsoController::base().'/token', [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.sso.client_id'),
                'client_secret' => config('services.sso.client_secret'),
                'refresh_token' => $refreshToken,
            ]);
        } catch (ConnectionException) {
            // Keycloak tidak bisa dihubungi bukan berarti user sudah logout —
            // jangan tendang semua orang cuma karena jaringan lagi ngadat.
            return true;
        }

        if ($token->failed()) {
            return false;
        }

        // Keycloak memutar refresh token tiap kali dipakai; simpan yang baru,
        // kalau tidak pengecekan berikutnya akan gagal dan user tertendang.
        // ponytail: tanpa lock karena session driver 'file' mengunci per sesi,
        // jadi request paralel dari user yang sama berjalan berurutan dan yang
        // kedua sudah melihat sso_checked_until. Pindah ke redis/database ->
        // dua request bisa memakai refresh token yang sama; pasang lock kalau
        // realm mengaktifkan "Revoke Refresh Token".
        $session->put('sso_refresh_token', $token->json('refresh_token') ?: $refreshToken);
        $session->put('sso_checked_until', now()->timestamp + self::CHECK_INTERVAL);

        return true;
    }
}
