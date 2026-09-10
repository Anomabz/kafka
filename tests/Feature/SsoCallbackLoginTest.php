<?php

namespace Tests\Feature;

use App\Models\Korprodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Satu-satunya jalur login sekarang: authorization code dari halaman login
 * bawaan Keycloak yang mendarat di /auth/callback. Tidak ada lagi form
 * kredensial milik SAPTO (grant_type=password sudah dihapus).
 */
class SsoCallbackLoginTest extends TestCase
{
    use RefreshDatabase;

    private function ssoCallback(array $userinfo)
    {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['access_token' => 'abc', 'id_token' => 'idt']),
            '*/protocol/openid-connect/userinfo' => Http::response($userinfo),
        ]);

        return $this->withSession(['sso_state' => 'st'])
            ->get('/auth/callback?code=kode&state=st');
    }

    public function test_korprodi_gets_a_session_and_lands_on_home(): void
    {
        Korprodi::create(['nip' => '199999999999999001', 'nama' => 'Budi', 'kode_program_studi' => '55201']);

        $response = $this->ssoCallback(['sub' => '1', 'name' => 'Budi', 'email' => 'budi@example.test', 'nomor_identitas' => '199999999999999001']);

        $response->assertRedirect('/');
        $this->assertSame('Budi', session('sso_user')['name']);
        // Intro splash muncul tiap kali login (flash, sekali pakai) --
        // bukan disimpan permanen di sessionStorage browser.
        $this->assertTrue(session('show_intro'));
    }

    public function test_account_valid_at_sso_but_not_a_korprodi_is_denied(): void
    {
        // Akun ini benar-benar valid di Keycloak (token+userinfo sukses),
        // tapi NIP-nya tidak ada di tabel korprodi -> tetap ditolak, bukan
        // cuma dibiarkan masuk lalu ditolak nanti saat pertama kali memanggil
        // API (lihat SsoController::isKorprodi()).
        $response = $this->ssoCallback(['sub' => '2', 'name' => 'Firdaus Masdung', 'email' => 'firdaus@example.test', 'nomor_identitas' => '9999999']);

        $response->assertRedirect('/');
        $this->assertSame('Firdaus Masdung', session('not_korprodi_hint'));
        $this->assertFalse(session()->has('sso_user'));

        $follow = $this->get('/');
        $follow->assertOk();
        $follow->assertSee('Akses ditolak');
        $follow->assertSee('Firdaus Masdung');
    }

    public function test_sso_account_listed_as_admin_gets_full_access_without_being_korprodi(): void
    {
        // Kebalikan test di atas: NIP yang sama, tapi kali ini terdaftar
        // sebagai admin di tabel users -> lolos tanpa harus jadi korprodi,
        // dan sesinya membawa is_admin sehingga SaptoUserContext memberi
        // scope 'universitas'. Kolom password lokal tidak dipakai di jalur ini.
        User::create([
            'name' => 'Firdaus Masdung',
            'email' => 'firdaus@unsri.ac.id',
            'nomor_identitas' => '9999999',
            'is_admin' => true,
            'password' => Hash::make(Str::random(64)),
        ]);

        $response = $this->ssoCallback(['sub' => '2', 'name' => 'Firdaus Masdung', 'email' => 'firdaus@unsri.ac.id', 'nomor_identitas' => '9999999', 'unit_kerja' => 'Fasilkom']);

        $response->assertRedirect('/');
        $this->assertTrue(session('sso_user')['is_admin']);
        $this->assertSame('Fasilkom', session('sso_user')['unit_kerja']);
    }

    public function test_admin_is_matched_by_email_when_sso_sends_no_nomor_identitas(): void
    {
        // Realm tidak selalu mengirim `nomor_identitas` (ada di log 2026-09-10:
        // akun valid ditolak dengan klaim itu null). Admin tetap harus dikenali
        // lewat email, kalau tidak baris admin-nya tidak pernah kena.
        User::create([
            'name' => 'Admin SAPTO',
            'email' => '09011282429038@student.unsri.ac.id',
            'nomor_identitas' => '09011282429038',
            'is_admin' => true,
            'password' => Hash::make(Str::random(64)),
        ]);

        $response = $this->ssoCallback(['sub' => '3', 'name' => 'Admin SAPTO', 'email' => '09011282429038@student.unsri.ac.id']);

        $response->assertRedirect('/');
        $this->assertTrue(session('sso_user')['is_admin']);
    }

    public function test_userinfo_without_nip_and_email_never_matches_an_admin(): void
    {
        // Penjaga bug klasik: kalau kedua kunci kosong, query admin tidak boleh
        // dijalankan sama sekali -- where() dengan closure kosong akan cocok ke
        // semua baris admin dan memberi hak universitas ke sembarang akun SSO.
        User::create([
            'name' => 'Admin SAPTO',
            'email' => 'admin@unsri.ac.id',
            'nomor_identitas' => '123',
            'is_admin' => true,
            'password' => Hash::make(Str::random(64)),
        ]);

        $response = $this->ssoCallback(['sub' => '4', 'name' => 'Orang Asing']);

        $response->assertRedirect('/');
        $this->assertFalse(session()->has('sso_user'));
    }

    public function test_is_admin_claim_from_keycloak_is_never_trusted(): void
    {
        // Kalau realm suatu saat mengirim klaim bernama is_admin, klaim itu
        // tidak boleh menaikkan hak akses: statusnya selalu ditimpa hasil
        // pengecekan tabel users lokal (SsoController::ssoSessionUser()).
        Korprodi::create(['nip' => '199999999999999001', 'nama' => 'Budi', 'kode_program_studi' => '55201']);

        $this->ssoCallback(['sub' => '1', 'name' => 'Budi', 'email' => 'budi@example.test', 'nomor_identitas' => '199999999999999001', 'is_admin' => true])
            ->assertRedirect('/');

        $this->assertFalse(session('sso_user')['is_admin']);
    }

    public function test_callback_with_a_mismatched_state_is_rejected(): void
    {
        Http::fake();

        $response = $this->withSession(['sso_state' => 'st'])
            ->get('/auth/callback?code=kode&state=punya-penyerang');

        $response->assertStatus(400);
        $this->assertFalse(session()->has('sso_user'));
        Http::assertNothingSent();
    }
}
