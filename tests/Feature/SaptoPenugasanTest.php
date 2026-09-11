<?php

namespace Tests\Feature;

use App\Models\Penugasan;
use App\Support\SaptoUserContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Hak akses berbasis tabel `penugasan`: admin mendaftarkan korprodi, dekan,
 * atau rektor lewat /admin/peran, dan SaptoUserContext menerjemahkannya jadi
 * scope data. Yang tidak terdaftar tidak bisa masuk.
 */
class SaptoPenugasanTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['sub' => 'a', 'name' => 'Admin', 'email' => 'admin@unsri.ac.id', 'nomor_identitas' => '111', 'is_admin' => true];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // Dua fakultas, dua prodi masing-masing -- pemetaan fakultas diambil
        // dari database/data/program_studi.csv (55201/56201 = Ilmu Komputer,
        // 62201/61201 = Ekonomi).
        foreach ([['P1', '55201', 'Teknik Informatika'], ['P2', '56201', 'Sistem Komputer'], ['P3', '62201', 'Akuntansi'], ['P4', '61201', 'Manajemen']] as [$id, $kode, $nama]) {
            DB::table('program_studi')->insert([
                'id_prodi' => $id,
                'kode_program_studi' => $kode,
                'nama_program_studi' => $nama,
                'status' => 'AKTIF',
                'id_jenjang_pendidikan' => 1,
                'nama_jenjang_pendidikan' => 'S1',
            ]);
        }
    }

    private function sebagai(array $ssoUser): self
    {
        return $this->withSession(['sso_user' => $ssoUser]);
    }

    private function penugasan(string $peran, ?string $lingkup, array $identitas = ['nip' => '197801212008121003']): Penugasan
    {
        return Penugasan::create(array_merge(['peran' => $peran, 'lingkup' => $lingkup, 'nama' => 'Uji'], $identitas));
    }

    // --- Scope per peran -------------------------------------------------

    public function test_rektor_sees_every_faculty_and_program_studi(): void
    {
        $this->penugasan('rektor', null);

        $response = $this->sebagai(['sub' => '1', 'nomor_identitas' => '197801212008121003'])->getJson('/api/sapto/program-studi');

        $response->assertOk()
            ->assertJsonPath('can_select_fakultas', true)
            ->assertJsonPath('fakultas_id', null)
            ->assertJsonCount(4, 'data');
    }

    public function test_dekan_is_locked_to_their_faculty_but_can_pick_prodi_inside_it(): void
    {
        $this->penugasan('dekan', 'fakultas-ilmu-komputer');

        $response = $this->sebagai(['sub' => '1', 'nomor_identitas' => '197801212008121003'])->getJson('/api/sapto/program-studi');

        $response->assertOk()
            ->assertJsonPath('can_select_fakultas', false)
            ->assertJsonPath('can_select_program_studi', true)
            ->assertJsonPath('fakultas_id', 'fakultas-ilmu-komputer');
        $this->assertEqualsCanonicalizing(['P1', 'P2'], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_dekan_cannot_peek_at_a_prodi_of_another_faculty_by_editing_the_url(): void
    {
        // PrestasiAkademik termasuk repository yang TIDAK melewati
        // validateScope(), jadi yang menahan ini murni penjaga di
        // SaptoUserContext::resolveProgramStudiId().
        $this->penugasan('dekan', 'fakultas-ilmu-komputer');

        $this->sebagai(['sub' => '1', 'nomor_identitas' => '197801212008121003'])
            ->getJson('/api/sapto/tables/prestasi-akademik/summary?program_studi_id=P3')
            ->assertStatus(403);
    }

    public function test_dekan_prodi_guard_allows_own_faculty_and_rejects_others(): void
    {
        $dekan = new SaptoUserContext('fakultas', null, 'fakultas-ilmu-komputer');

        $this->assertSame('P2', $dekan->resolveProgramStudiId(Request::create('/', 'GET', ['program_studi_id' => 'P2'])));
        $this->assertNull($dekan->resolveProgramStudiId(Request::create('/')));

        $this->expectException(HttpException::class);
        $dekan->resolveProgramStudiId(Request::create('/', 'GET', ['program_studi_id' => 'P4']));
    }

    public function test_korprodi_is_locked_to_their_prodi(): void
    {
        $this->penugasan('korprodi', '56201');

        $response = $this->sebagai(['sub' => '1', 'nomor_identitas' => '197801212008121003'])->getJson('/api/sapto/program-studi');

        $response->assertOk()->assertJsonPath('can_select_program_studi', false);
        $this->assertSame(['P2'], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_the_widest_role_wins_for_someone_holding_several(): void
    {
        $this->penugasan('korprodi', '56201');
        $this->penugasan('dekan', 'fakultas-ekonomi');

        $this->sebagai(['sub' => '1', 'nomor_identitas' => '197801212008121003'])->getJson('/api/sapto/program-studi')
            ->assertOk()
            ->assertJsonPath('fakultas_id', 'fakultas-ekonomi');
    }

    // --- Pencocokan identitas --------------------------------------------

    public function test_assignment_is_matched_by_email_when_sso_sends_no_nip(): void
    {
        $this->penugasan('dekan', 'fakultas-ekonomi', ['nip' => null, 'email' => 'dekan@unsri.ac.id']);

        $this->sebagai(['sub' => '1', 'email' => 'dekan@unsri.ac.id'])->getJson('/api/sapto/program-studi')
            ->assertOk()
            ->assertJsonPath('fakultas_id', 'fakultas-ekonomi');
    }

    public function test_session_without_nip_and_email_never_matches_anything(): void
    {
        // Kalau penjaga ini hilang, where() dengan closure kosong cocok ke
        // SEMUA baris dan akun SSO tanpa identitas mendapat peran rektor.
        $this->penugasan('rektor', null, ['nip' => null, 'email' => 'rektor@unsri.ac.id']);

        $this->sebagai(['sub' => '1', 'name' => 'Tanpa Identitas'])->getJson('/api/sapto/program-studi')->assertStatus(403);
    }

    public function test_unregistered_account_is_denied(): void
    {
        $this->sebagai(['sub' => '1', 'nomor_identitas' => '999', 'email' => 'x@unsri.ac.id'])
            ->getJson('/api/sapto/program-studi')
            ->assertStatus(403);
    }

    public function test_revoking_takes_effect_on_the_very_next_request(): void
    {
        $penugasan = $this->penugasan('rektor', null);
        $user = ['sub' => '1', 'nomor_identitas' => '197801212008121003'];

        $this->sebagai($user)->getJson('/api/sapto/program-studi')->assertOk();

        $penugasan->delete();

        $this->sebagai($user)->getJson('/api/sapto/program-studi')->assertStatus(403);
    }

    public function test_dekan_can_log_in_through_sso_without_being_korprodi(): void
    {
        $this->penugasan('dekan', 'fakultas-ekonomi', ['nip' => null, 'email' => 'dekan@unsri.ac.id']);

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response(['access_token' => 'at', 'id_token' => 'idt', 'refresh_token' => 'rt']),
            '*/protocol/openid-connect/userinfo' => Http::response(['sub' => '9', 'name' => 'Dekan', 'email' => 'dekan@unsri.ac.id']),
        ]);

        $this->withSession(['sso_state' => 'st'])->get('/auth/callback?code=c&state=st')->assertRedirect('/');

        $this->assertSame('dekan@unsri.ac.id', session('sso_user')['email']);
    }

    public function test_dekan_sees_foreign_student_details_of_their_faculty_only(): void
    {
        // Endpoint detail dulu hanya menyaring per prodi: dekan dengan
        // "Semua Program Studi" berarti prodi null = TANPA saringan, dan
        // daftar mahasiswa asing se-universitas ikut keluar.
        DB::table('negara')->insert([['id_negara' => 'MY', 'nama_negara' => 'Malaysia']]);
        DB::table('biodata_mahasiswa')->insert([
            ['id_mahasiswa' => 'M1', 'nama_mahasiswa' => 'Ali', 'id_negara' => 'MY'],
            ['id_mahasiswa' => 'M2', 'nama_mahasiswa' => 'Chen', 'id_negara' => 'MY'],
        ]);
        DB::table('list_riwayat_pendidikan_mahasiswa')->insert([
            ['id_mahasiswa' => 'M1', 'id_prodi' => 'P2', 'nim' => 'NIM-FASILKOM', 'nama_mahasiswa' => 'Ali', 'id_periode_masuk' => '20231', 'id_periode_keluar' => null],
            ['id_mahasiswa' => 'M2', 'id_prodi' => 'P3', 'nim' => 'NIM-EKONOMI', 'nama_mahasiswa' => 'Chen', 'id_periode_masuk' => '20231', 'id_periode_keluar' => null],
        ]);
        $this->penugasan('dekan', 'fakultas-ilmu-komputer');
        $this->penugasan('rektor', null, ['nip' => '1000001']);

        $dekan = $this->sebagai(['sub' => '1', 'nomor_identitas' => '197801212008121003'])->getJson('/sapto/mahasiswa-asing/detail');
        $dekan->assertOk()->assertJsonPath('total_mahasiswa', 1)->assertJsonPath('data.0.nim', 'NIM-FASILKOM');

        $this->sebagai(['sub' => '2', 'nomor_identitas' => '1000001'])->getJson('/sapto/mahasiswa-asing/detail')
            ->assertOk()->assertJsonPath('total_mahasiswa', 2);
    }

    public function test_profile_shows_the_role_that_actually_applies(): void
    {
        $this->penugasan('dekan', 'fakultas-ilmu-komputer');
        $this->penugasan('korprodi', '56201', ['nip' => '1000003']);

        $this->sebagai(['sub' => '1', 'name' => 'Bemby', 'nomor_identitas' => '197801212008121003'])->get('/')
            ->assertOk()
            ->assertSee('Dekan — Fakultas Ilmu Komputer')
            ->assertSee('Dekan — semua program studi di Fakultas Ilmu Komputer')
            ->assertDontSee('Pengelola Program Studi');

        $this->sebagai(['sub' => '2', 'name' => 'Kaprodi', 'nomor_identitas' => '1000003'])->get('/')
            ->assertOk()
            ->assertSee('Koordinator Program Studi — S1 Sistem Komputer');
    }

    public function test_page_still_renders_for_an_account_without_any_access(): void
    {
        // "Uji akun tanpa akses" di Login sebagai: SaptoUserContext melempar
        // 403, dan profil di sidebar tidak boleh ikut menggagalkan halaman.
        $this->sebagai(['sub' => '1', 'name' => 'Uji', 'nomor_identitas' => '0000000000'])->get('/')
            ->assertOk()
            ->assertSee('Belum punya akses SAPTO');
    }

    // --- Halaman admin ---------------------------------------------------

    public function test_non_admin_cannot_open_or_use_the_role_page(): void
    {
        $this->penugasan('rektor', null);
        $rektor = ['sub' => '1', 'nomor_identitas' => '197801212008121003'];

        $this->sebagai($rektor)->get('/admin/peran')->assertForbidden();
        $this->sebagai($rektor)->post('/admin/peran', ['peran' => 'rektor', 'nip' => '123456'])->assertForbidden();
        $this->assertSame(1, Penugasan::count());
    }

    public function test_admin_sees_the_role_page(): void
    {
        $this->penugasan('dekan', 'fakultas-ekonomi', ['nip' => '197801212008121003', 'nama' => 'Dr. Dekan Ekonomi']);

        $this->sebagai(self::ADMIN)->get('/admin/peran')
            ->assertOk()
            ->assertSee('Kelola peran')
            ->assertSee('Dr. Dekan Ekonomi')
            ->assertSee('Dekan · Fakultas Ekonomi');
    }

    public function test_admin_adds_a_korprodi_and_the_nip_is_normalised(): void
    {
        $this->sebagai(self::ADMIN)->post('/admin/peran', [
            'peran' => 'korprodi',
            'nip' => '19780121 200812 1 003',
            'email' => '  Kaprodi@UNSRI.ac.id ',
            'nama' => 'Kaprodi SK',
            'lingkup_prodi' => '56201',
        ])->assertRedirect(route('admin.peran'));

        $row = Penugasan::sole();
        $this->assertSame(['korprodi', '197801212008121003', 'kaprodi@unsri.ac.id', '56201', 'admin@unsri.ac.id'],
            [$row->peran, $row->nip, $row->email, $row->lingkup, $row->dibuat_oleh]);
    }

    public function test_admin_adds_a_rektor_without_any_scope(): void
    {
        $this->sebagai(self::ADMIN)->post('/admin/peran', [
            'peran' => 'rektor',
            'email' => 'rektor@unsri.ac.id',
            'lingkup_prodi' => '56201', // sisa form yang tersembunyi -- harus diabaikan
        ])->assertRedirect(route('admin.peran'));

        $this->assertNull(Penugasan::sole()->lingkup);
    }

    /** @return array<string, array{0: array, 1: string}> */
    public static function inputTidakSah(): array
    {
        return [
            'tanpa nip dan email' => [['peran' => 'rektor'], 'nip'],
            'nip berisi huruf' => [['peran' => 'rektor', 'nip' => 'abc123'], 'nip'],
            'korprodi tanpa prodi' => [['peran' => 'korprodi', 'nip' => '123456'], 'lingkup_prodi'],
            'dekan tanpa fakultas' => [['peran' => 'dekan', 'nip' => '123456'], 'lingkup_fakultas'],
            'prodi tidak dikenal' => [['peran' => 'korprodi', 'nip' => '123456', 'lingkup_prodi' => '00000'], 'lingkup'],
            'fakultas tidak dikenal' => [['peran' => 'dekan', 'nip' => '123456', 'lingkup_fakultas' => 'fakultas-sihir'], 'lingkup'],
            'peran tidak dikenal' => [['peran' => 'admin', 'nip' => '123456'], 'peran'],
        ];
    }

    #[DataProvider('inputTidakSah')]
    public function test_admin_input_is_validated(array $input, string $field): void
    {
        $this->sebagai(self::ADMIN)->post('/admin/peran', $input)->assertSessionHasErrors($field);

        $this->assertSame(0, Penugasan::count());
    }

    public function test_rejected_input_reopens_the_popup_with_what_was_typed(): void
    {
        // Popup "Tambah akses" tertutup begitu halaman dimuat ulang; kalau
        // server menolak isian, popup harus terbuka lagi dengan isian lama
        // supaya admin tidak mengetik ulang dan tahu apa yang salah.
        $this->sebagai(self::ADMIN)->get('/admin/peran')->assertSee('data-buka="0"', false);

        $this->sebagai(self::ADMIN)->followingRedirects()
            ->from('/admin/peran')
            ->post('/admin/peran', ['peran' => 'korprodi', 'nip' => '197801212008121003'])
            ->assertOk()
            ->assertSee('data-buka="1"', false)
            ->assertSee('value="197801212008121003"', false)
            ->assertSee('Pilih program studi untuk korprodi.');
    }

    public function test_the_same_person_cannot_be_added_twice_for_the_same_scope(): void
    {
        $this->penugasan('korprodi', '56201', ['nip' => '197801212008121003']);

        // Beda kunci (email), orang yang sama sudah tercatat lewat NIP-nya.
        Penugasan::first()->update(['email' => 'kaprodi@unsri.ac.id']);

        $this->sebagai(self::ADMIN)->post('/admin/peran', [
            'peran' => 'korprodi', 'email' => 'kaprodi@unsri.ac.id', 'lingkup_prodi' => '56201',
        ])->assertSessionHasErrors('nip');

        $this->assertSame(1, Penugasan::count());
    }

    public function test_admin_revokes_access_and_it_is_logged(): void
    {
        Log::spy();
        $penugasan = $this->penugasan('dekan', 'fakultas-ekonomi');

        $this->sebagai(self::ADMIN)->delete('/admin/peran/'.$penugasan->id)->assertRedirect(route('admin.peran'));

        $this->assertSame(0, Penugasan::count());
        Log::shouldHaveReceived('info')->with('Admin SAPTO mencabut akses.', Mockery::on(
            fn (array $ctx): bool => $ctx['admin_email'] === 'admin@unsri.ac.id' && $ctx['peran'] === 'dekan'
        ));
    }

    // --- Login sebagai ---------------------------------------------------

    public function test_admin_can_impersonate_a_dekan_and_sees_exactly_their_scope(): void
    {
        Log::spy();
        $penugasan = $this->penugasan('dekan', 'fakultas-ekonomi', ['nip' => null, 'email' => 'dekan@unsri.ac.id', 'nama' => 'Dekan Ekonomi']);

        $this->sebagai(self::ADMIN)->post('/admin/login-as', ['penugasan_id' => $penugasan->id])->assertRedirect('/');

        $this->getJson('/api/sapto/program-studi')->assertOk()->assertJsonPath('fakultas_id', 'fakultas-ekonomi');
        Log::shouldHaveReceived('info')->with('Admin SAPTO menyamar sebagai pengguna lain.', Mockery::on(
            fn (array $ctx): bool => $ctx['admin_email'] === 'admin@unsri.ac.id' && $ctx['target_email'] === 'dekan@unsri.ac.id'
        ));
    }

    public function test_impersonation_list_shows_every_role(): void
    {
        $this->penugasan('rektor', null, ['nip' => '1000001', 'nama' => 'Pak Rektor']);
        $this->penugasan('dekan', 'fakultas-ekonomi', ['nip' => '1000002', 'nama' => 'Bu Dekan']);
        $this->penugasan('korprodi', '56201', ['nip' => '1000003', 'nama' => 'Kaprodi SK']);

        $this->sebagai(self::ADMIN)->get('/admin/login-as')
            ->assertOk()
            ->assertSee('Pak Rektor')
            ->assertSee('Bu Dekan')
            ->assertSee('Kaprodi SK');
    }
}
