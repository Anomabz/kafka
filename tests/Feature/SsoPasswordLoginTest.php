<?php

namespace Tests\Feature;

use App\Models\Korprodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SsoPasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_credentials_create_a_session_and_redirect_home(): void
    {
        Korprodi::create(['nip' => '199999999999999001', 'nama' => 'Budi', 'kode_program_studi' => '55201']);

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['access_token' => 'abc', 'id_token' => 'idt']),
            '*/protocol/openid-connect/userinfo' => Http::response(['sub' => '1', 'name' => 'Budi', 'email' => 'budi@example.test', 'nomor_identitas' => '199999999999999001']),
        ]);

        $response = $this->post('/login', ['username' => 'budi', 'password' => 'right']);

        $response->assertRedirect('/');
        $this->assertSame('Budi', session('sso_user')['name']);
        // Intro splash muncul tiap kali login (flash, sekali pakai) --
        // bukan disimpan permanen di sessionStorage browser.
        $this->assertTrue(session('show_intro'));
    }

    public function test_credentials_valid_at_sso_but_not_a_korprodi_are_denied(): void
    {
        // Akun ini benar-benar valid di Keycloak (token+userinfo sukses),
        // tapi NIP-nya tidak ada di tabel korprodi -> tetap ditolak, bukan
        // cuma dibiarkan masuk lalu ditolak nanti saat pertama kali memanggil
        // API (lihat SsoController::isKorprodi()).
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['access_token' => 'abc', 'id_token' => 'idt']),
            '*/protocol/openid-connect/userinfo' => Http::response(['sub' => '2', 'name' => 'Firdaus Masdung', 'email' => 'firdaus@example.test', 'nomor_identitas' => '9999999']),
        ]);

        $response = $this->post('/login', ['username' => 'firdaus', 'password' => 'right']);

        $response->assertRedirect(route('login'));
        $this->assertSame('Firdaus Masdung', session('not_korprodi_hint'));
        $this->assertFalse(session()->has('sso_user'));

        $follow = $this->get(route('login'));
        $follow->assertOk();
        $follow->assertSee('Akses ditolak');
        $follow->assertSee('Firdaus Masdung');
    }

    public function test_sso_account_listed_as_admin_gets_full_access_without_being_korprodi(): void
    {
        // Kebalikan test di atas: NIP yang sama, tapi kali ini terdaftar
        // sebagai admin di tabel users -> lolos tanpa harus jadi korprodi,
        // dan sesinya membawa is_admin sehingga SaptoUserContext memberi
        // scope 'universitas'. Password lokal tidak dipakai di jalur ini.
        User::create([
            'name' => 'Firdaus Masdung',
            'email' => 'firdaus@unsri.ac.id',
            'nomor_identitas' => '9999999',
            'is_admin' => true,
            'password' => Hash::make(Str::random(64)),
        ]);

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['access_token' => 'abc', 'id_token' => 'idt']),
            '*/protocol/openid-connect/userinfo' => Http::response(['sub' => '2', 'name' => 'Firdaus Masdung', 'email' => 'firdaus@unsri.ac.id', 'nomor_identitas' => '9999999', 'unit_kerja' => 'Fasilkom']),
        ]);

        $response = $this->post('/login', ['username' => 'firdaus', 'password' => 'right']);

        $response->assertRedirect('/');
        $this->assertTrue(session('sso_user')['is_admin']);
        $this->assertSame('Fasilkom', session('sso_user')['unit_kerja']);
    }

    public function test_is_admin_claim_from_keycloak_is_never_trusted(): void
    {
        // Kalau realm suatu saat mengirim klaim bernama is_admin, klaim itu
        // tidak boleh menaikkan hak akses: statusnya selalu ditimpa hasil
        // pengecekan tabel users lokal (SsoController::ssoSessionUser()).
        Korprodi::create(['nip' => '199999999999999001', 'nama' => 'Budi', 'kode_program_studi' => '55201']);

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['access_token' => 'abc', 'id_token' => 'idt']),
            '*/protocol/openid-connect/userinfo' => Http::response(['sub' => '1', 'name' => 'Budi', 'email' => 'budi@example.test', 'nomor_identitas' => '199999999999999001', 'is_admin' => true]),
        ]);

        $this->post('/login', ['username' => 'budi', 'password' => 'right'])->assertRedirect('/');

        $this->assertFalse(session('sso_user')['is_admin']);
    }

    public function test_wrong_credentials_send_back_to_login_with_unregistered_popup(): void
    {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(
                ['error' => 'invalid_grant', 'error_description' => 'Invalid user credentials'],
                401
            ),
        ]);

        $response = $this->post('/login', ['username' => 'budi', 'password' => 'wrong']);

        $response->assertRedirect(route('login'));
        $this->assertSame('budi', session('unregistered_hint'));
        $this->assertFalse(session()->has('sso_user'));

        $follow = $this->get(route('login'));
        $follow->assertOk();
        $follow->assertSee('Akun tidak terdaftar');
        $follow->assertSee(route('sso.login', ['login_hint' => 'budi']), false);
    }

    public function test_account_that_needs_keycloaks_own_ui_shows_unregistered_popup(): void
    {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(
                ['error' => 'invalid_grant', 'error_description' => 'Account is not fully set up'],
                400
            ),
        ]);

        $response = $this->post('/login', ['username' => 'budi', 'password' => 'right']);

        $response->assertRedirect(route('login'));
        $this->assertSame('budi', session('unregistered_hint'));
    }
}
