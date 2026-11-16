<?php

namespace Tests\Feature;

use App\Models\Penugasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Beranda desktop: halaman awal setelah login. Logika render ada di JS
 * (sapto.blade.php); yang bisa dijaga di sini adalah kerangka HTML dari
 * server, siapa yang boleh melihat apa, dan kontrak dengan API dashboard yang
 * dipakai JS.
 */
class DesktopBerandaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_landing_page_is_beranda_with_desktop_markup_and_active_menu(): void
    {
        $this->sebagai('199999999999999001')
            ->get('/')
            ->assertOk()
            ->assertSee('id="home-page"', false)
            ->assertSee('id="beranda-page"', false)
            ->assertSee('data-table-id="beranda"', false)
            ->assertSee('menu-home is-active', false);
    }

    public function test_hidden_class_actually_hides_the_desktop_beranda(): void
    {
        // `.home` memakai display:flex di luar @layer sehingga mengalahkan
        // utilitas `.hidden` Tailwind; 2026-09-30 Beranda jadi tetap tampil
        // di atas setiap tabel. Aturan khusus ini yang menyembunyikannya.
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression('/#home-page\.hidden\s*\{\s*display:\s*none/', $css);
    }

    public function test_regular_user_sees_about_data_but_not_system_health(): void
    {
        $this->sebagai('199999999999999001')
            ->get('/')
            ->assertOk()
            ->assertSee('data-admin="0"', false)
            ->assertSee('id="home-about"', false)
            ->assertDontSee('id="home-system"', false);
    }

    public function test_admin_sees_system_health_instead_of_about_data(): void
    {
        $this->sebagai('1', ['is_admin' => true])
            ->get('/')
            ->assertOk()
            ->assertSee('data-admin="1"', false)
            ->assertSee('id="home-system"', false)
            ->assertDontSee('id="home-about"', false);
    }

    public function test_greeting_uses_a_tidied_name_but_keeps_mixed_case_names(): void
    {
        // Nama mentah tetap tampil di kotak akun sidebar; yang dirapikan
        // hanya sapaan di Beranda, jadi yang diperiksa judulnya saja.
        $this->assertSame('Selamat datang, Dr. H.M. Irsan Saleh', $this->judulSapaan('DR. H.M. IRSAN SALEH'));
        $this->assertSame('Selamat datang, Ir. Siti Aminah, M.T.', $this->judulSapaan('Ir. Siti Aminah, M.T.'));
    }

    public function test_names_are_escaped_in_the_greeting(): void
    {
        $judul = $this->judulSapaan('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $judul);
        $this->assertStringContainsString('&lt;script&gt;', $this->sebagai('199999999999999001', ['name' => '<script>alert(1)</script>'])->get('/')->getContent());
    }

    public function test_trend_charts_only_reference_dashboard_cards_that_exist_and_have_matching_series(): void
    {
        DB::table('program_studi')->insert([
            'id_prodi' => 'P1', 'kode_program_studi' => '55201', 'nama_program_studi' => 'Teknik Informatika',
            'status' => 'AKTIF', 'id_jenjang_pendidikan' => 1, 'nama_jenjang_pendidikan' => 'S1',
        ]);
        Penugasan::create(['peran' => 'korprodi', 'nip' => '199999999999999001', 'nama' => 'Test', 'lingkup' => '55201']);

        $js = file_get_contents(resource_path('views/sapto.blade.php'));
        $this->assertSame(1, preg_match('/const HOME_CHARTS = \[(.*?)\n    \];/s', $js, $block), 'HOME_CHARTS tidak ditemukan.');
        preg_match_all("/\\{ id: '([a-z0-9-]+)'/", $block[1], $ids);
        $this->assertNotEmpty($ids[1]);

        $cards = collect(
            $this->sebagai('199999999999999001')->getJson('/api/sapto/dashboard/summary')->assertOk()->json('cards')
        )->keyBy('id');

        foreach ($ids[1] as $id) {
            $this->assertTrue($cards->has($id), "Grafik Beranda memakai kartu '{$id}' yang tidak ada di API dashboard.");

            $trend = $cards[$id]['trend'];
            $this->assertIsArray($trend, "Kartu '{$id}' tidak punya tren.");
            $this->assertSame(count($trend['labels']), count($trend['values']), "Label dan nilai tren '{$id}' tidak sama panjang.");
        }

        // Kartu angka utama membaca metrik dengan indeks tetap.
        foreach (['dosen-tetap-pt' => 3, 'ipk-lulusan' => 3, 'dosen-pembimbing-ta' => 3] as $id => $minMetrics) {
            $this->assertGreaterThanOrEqual($minMetrics, count($cards[$id]['metrics']), "Metrik '{$id}' kurang dari yang dibaca Beranda.");
        }
    }

    /** Teks judul sapaan Beranda (tanpa tag), dengan entitas HTML sudah didekode. */
    private function judulSapaan(string $nama): string
    {
        $html = $this->sebagai('199999999999999001', ['name' => $nama])->get('/')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<h2 class="home-title"[^>]*>(.*?)<\/h2>/s', $html, $m), 'Judul sapaan tidak ditemukan.');

        return preg_replace('/\s+/', ' ', trim(strip_tags($m[1])));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function sebagai(string $nip, array $extra = []): static
    {
        $this->flushSession();
        $this->withSession(['sso_user' => array_merge([
            'sub' => $nip, 'name' => 'Test', 'email' => "{$nip}@example.test", 'nomor_identitas' => $nip,
        ], $extra)]);

        return $this;
    }
}
