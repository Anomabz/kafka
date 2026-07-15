<?php

namespace Tests\Feature;

use App\Services\SaptoDosenTetapRepository;
use App\Services\SaptoIpkLulusanRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/sapto/dashboard/summary — komposisi 5 kartu dari 5 repository yang
 * sudah ada. Fokus tes ini adalah orkestrasi (urutan kartu, isolasi galat per
 * domain, passthrough notice, scoping), BUKAN mengetes ulang perhitungan tiap
 * domain — itu sudah dites di SaptoNewTablesTest/SaptoApiTest/
 * SaptoMahasiswaAsingRepositoryTest.
 */
class SaptoDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession(['sso_user' => [
            'sub' => 'test', 'name' => 'Test', 'email' => 'test@example.test',
            'nomor_identitas' => '199999999999999001',
        ]]);
        Cache::flush();
        $this->seedAllFiveDomains();

        // Auto-scopes this session to P1 (kode 55201) via the korprodi
        // mapping — X-SAPTO-* headers are no longer trusted for scope.
        \App\Models\Korprodi::create([
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'kode_program_studi' => '55201',
        ]);
    }

    public function test_dashboard_returns_five_cards_in_order(): void
    {
        $response = $this->getJson('/api/sapto/dashboard/summary?ts=2025')
            ->assertOk();

        $ids = collect($response->json('cards'))->pluck('id')->all();

        $this->assertSame([
            'dosen-tetap-pt',
            'dosen-pembimbing-ta',
            'ipk-lulusan',
            'masa-studi',
            'mahasiswa-asing',
        ], $ids);
    }

    public function test_dashboard_resolves_ts_and_returns_available_years_when_ts_omitted(): void
    {
        $response = $this->getJson('/api/sapto/dashboard/summary')
            ->assertOk();

        $ts = $response->json('ts');
        $availableYears = $response->json('available_years');

        $this->assertNotNull($ts);
        $this->assertNotEmpty($availableYears);
        $this->assertContains($ts, $availableYears);
    }

    public function test_dosen_pembimbing_card_has_metrics_and_three_point_trend(): void
    {
        $response = $this->getJson('/api/sapto/dashboard/summary?ts=2025')
            ->assertOk();

        $card = collect($response->json('cards'))->firstWhere('id', 'dosen-pembimbing-ta');

        $this->assertFalse($card['error']);
        $this->assertNull($card['notice']);
        $this->assertSame('Total dosen pembimbing', $card['metrics'][0]['label']);
        $this->assertSame(1, $card['metrics'][0]['value']); // D1 only
        $this->assertCount(3, $card['trend']['labels']);
        $this->assertCount(3, $card['trend']['values']);
        $this->assertSame(['2023', '2024', '2025'], $card['trend']['labels']);
    }

    public function test_masa_studi_card_shows_notice_without_program_studi_then_real_data_when_selected(): void
    {
        // Every authenticated user is now always locked to their own korprodi
        // scope (see SaptoUserContext::fromRequest) — the "universitas viewer
        // with no prodi selected" case this test compared against no longer
        // exists as a reachable state.
        $this->markTestSkipped('Every session now always resolves to a specific program studi — see SaptoUserContext::fromRequest().');
    }

    public function test_dosen_tetap_and_ipk_cards_report_seeded_metric_values(): void
    {
        // Dashboard cards look up sibling repositories' metrics by label string
        // (e.g. $byLabel['Total dosen tetap']) rather than a stable key. If a
        // sibling repo ever renames a label, the lookup silently falls back to
        // 0 with error:false. These assertions on real seeded values would
        // catch that — a rename would fail this test instead of shipping
        // silently-wrong data on an accreditation surface.
        $response = $this->getJson('/api/sapto/dashboard/summary?ts=2025')
            ->assertOk();

        $cards = collect($response->json('cards'))->keyBy('id');

        $dosenTetapMetrics = collect($cards['dosen-tetap-pt']['metrics'])->pluck('value', 'label');
        $this->assertSame(2, $dosenTetapMetrics['Total dosen tetap']);
        $this->assertSame(1, $dosenTetapMetrics['Pendidikan S2 (Magister)']);
        $this->assertSame(1, $dosenTetapMetrics['Pendidikan S3 (Doktor)']);

        $ipkMetrics = collect($cards['ipk-lulusan']['metrics'])->pluck('value', 'label');
        $this->assertSame(1, $ipkMetrics['Jumlah lulusan (TS)']);
        $this->assertEquals(3.5, $ipkMetrics['IPK rata-rata (TS)']);
    }

    public function test_one_domain_failure_does_not_break_other_cards(): void
    {
        $this->mock(SaptoIpkLulusanRepository::class, function ($mock): void {
            $mock->shouldReceive('summary')->andThrow(new \RuntimeException('DB down'));
        });

        $response = $this->getJson('/api/sapto/dashboard/summary?ts=2025')
            ->assertOk();

        $cards = collect($response->json('cards'))->keyBy('id');

        $this->assertTrue($cards['ipk-lulusan']['error']);
        $this->assertSame([], $cards['ipk-lulusan']['metrics']);

        // Domain lain tetap normal.
        $this->assertFalse($cards['dosen-tetap-pt']['error']);
        $this->assertFalse($cards['dosen-pembimbing-ta']['error']);
        $this->assertFalse($cards['mahasiswa-asing']['error']);
    }

    public function test_available_years_failure_does_not_break_whole_endpoint(): void
    {
        $this->partialMock(SaptoDosenTetapRepository::class, function ($mock): void {
            $mock->shouldReceive('availableYears')->andThrow(new \RuntimeException('DB down'));
        });

        $response = $this->getJson('/api/sapto/dashboard/summary?ts=2025')
            ->assertOk();

        $this->assertSame(2025, $response->json('ts'));
        $this->assertSame([], $response->json('available_years'));

        $ids = collect($response->json('cards'))->pluck('id')->all();
        $this->assertSame([
            'dosen-tetap-pt',
            'dosen-pembimbing-ta',
            'ipk-lulusan',
            'masa-studi',
            'mahasiswa-asing',
        ], $ids);
    }

    private function seedAllFiveDomains(): void
    {
        DB::table('program_studi')->insert([
            ['id_prodi' => 'P1', 'kode_program_studi' => '55201', 'nama_program_studi' => 'Teknik Informatika', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 1, 'nama_jenjang_pendidikan' => 'S1'],
        ]);

        // Dosen Tetap PT (3.a.1) — D1 punya S2+S3 (dihitung S3 saja), D2 hanya
        // S2; nilai non-nol ini yang menjaga lookup label di dashboard card.
        DB::table('list_penugasan_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2025/2026'],
            ['id_dosen' => 'D2', 'nama_dosen' => 'Dr. Budi', 'nidn' => '222', 'jk' => 'L', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2025/2026'],
        ]);
        DB::table('riwayat_pendidikan_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_jenjang_pendidikan' => 'S2', 'nama_bidang_studi' => 'Ilmu Komputer', 'nama_perguruan_tinggi' => 'Universitas Indonesia', 'tahun_lulus' => '2009'],
            ['id_dosen' => 'D1', 'nama_jenjang_pendidikan' => 'S3', 'nama_bidang_studi' => 'Ilmu Komputer', 'nama_perguruan_tinggi' => 'Universitas Gadjah Mada', 'tahun_lulus' => '2015'],
            ['id_dosen' => 'D2', 'nama_jenjang_pendidikan' => 'S2', 'nama_bidang_studi' => 'Manajemen', 'nama_perguruan_tinggi' => 'Universitas Sriwijaya', 'tahun_lulus' => '2012'],
        ]);

        // Dosen Pembimbing TA (3.a.2) — satu mahasiswa per tahun TS-2/TS-1/TS.
        DB::table('dosen_pembimbing')->insert([
            ['id_registrasi_mahasiswa' => 'M1', 'id_bimbing_mahasiswa' => 'M1', 'nim' => 'NIM1', 'nama_mahasiswa' => 'Mhs Satu', 'id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'pembimbing_ke' => '1', 'id_aktivitas' => 'A1', 'jenis_aktivitas' => 'Skripsi'],
            ['id_registrasi_mahasiswa' => 'M2', 'id_bimbing_mahasiswa' => 'M2', 'nim' => 'NIM2', 'nama_mahasiswa' => 'Mhs Dua', 'id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'pembimbing_ke' => '1', 'id_aktivitas' => 'A2', 'jenis_aktivitas' => 'Skripsi'],
            ['id_registrasi_mahasiswa' => 'M3', 'id_bimbing_mahasiswa' => 'M3', 'nim' => 'NIM3', 'nama_mahasiswa' => 'Mhs Tiga', 'id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'pembimbing_ke' => '1', 'id_aktivitas' => 'A3', 'jenis_aktivitas' => 'Skripsi'],
        ]);
        DB::table('list_aktivitas_mahasiswa')->insert([
            ['id_aktivitas' => 'A1', 'id_prodi' => 'P1', 'nama_prodi' => 'Teknik Informatika', 'id_semester' => '20231', 'judul' => 'TA 2023', 'status_sync' => 'sudah sync'],
            ['id_aktivitas' => 'A2', 'id_prodi' => 'P1', 'nama_prodi' => 'Teknik Informatika', 'id_semester' => '20241', 'judul' => 'TA 2024', 'status_sync' => 'sudah sync'],
            ['id_aktivitas' => 'A3', 'id_prodi' => 'P1', 'nama_prodi' => 'Teknik Informatika', 'id_semester' => '20251', 'judul' => 'TA 2025', 'status_sync' => 'sudah sync'],
        ]);

        // IPK Lulusan (8.a) + Masa Studi (8.c) share list_mahasiswa_lulus_do.
        DB::table('list_mahasiswa_lulus_do')->insert([
            'nama_jenis_keluar' => 'Lulus',
            'id_prodi' => 'P1',
            'nama_program_studi' => 'Teknik Informatika',
            'angkatan' => '2021',
            'id_periode_keluar' => '20251',
            'ipk' => '3.50',
            'tgl_masuk_sp' => '01-09-2021',
            'tgl_sk_yudisium' => '01-09-2025',
        ]);
        DB::table('list_riwayat_pendidikan_mahasiswa')->insert([
            'id_prodi' => 'P1',
            'nama_jenis_daftar' => 'Peserta didik baru',
            'id_periode_masuk' => '20211',
        ]);

        // Mahasiswa Asing (2.b)
        DB::table('negara')->insert(['id_negara' => 'ID', 'nama_negara' => 'Indonesia']);
        DB::table('negara')->insert(['id_negara' => 'MY', 'nama_negara' => 'Malaysia']);
        DB::table('biodata_mahasiswa')->insert(['id_mahasiswa' => 'MA1', 'nama_mahasiswa' => 'Foreign One', 'id_negara' => 'MY']);
        DB::table('list_riwayat_pendidikan_mahasiswa')->insert([
            'id_mahasiswa' => 'MA1',
            'id_prodi' => 'P1',
            'nim' => 'NIMASING1',
            'nama_mahasiswa' => 'Foreign One',
            'id_periode_masuk' => '20231',
            'id_periode_keluar' => null,
        ]);
    }
}
