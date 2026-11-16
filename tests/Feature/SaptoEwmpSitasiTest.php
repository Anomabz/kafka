<?php

namespace Tests\Feature;

use App\Models\Penugasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 3.a.3 EWMP: dulu semua kolom 0 (diisi di kode) dengan roster berbeda dari
 * 3.a.1. Sekarang EWMP Pendidikan dari beban mengajar nyata.
 *
 * 3.b.5 Sitasi DTPS: kunci cache dulu tanpa roster, sehingga prodi pertama
 * yang dibuka "mengunci" daftar artikelnya untuk semua prodi lain.
 */
class SaptoEwmpSitasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->withSession(['sso_user' => [
            'sub' => 'test', 'name' => 'Test', 'email' => 'test@example.test', 'nomor_identitas' => '199999999999999001',
        ]]);
        DB::table('program_studi')->insert([
            ['id_prodi' => 'P1', 'kode_program_studi' => '55201', 'nama_program_studi' => 'Teknik Informatika', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 30, 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'P2', 'kode_program_studi' => '61201', 'nama_program_studi' => 'Manajemen', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 30, 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        Penugasan::create(['peran' => 'korprodi', 'nip' => '199999999999999001', 'nama' => 'Test', 'lingkup' => '55201']);
    }

    public function test_ewmp_uses_the_3a1_roster_and_real_teaching_load(): void
    {
        DB::table('list_penugasan_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_dosen' => 'DOSEN SATU', 'nama_tahun_ajaran' => '2025/2026', 'id_prodi' => 'P1', 'a_sp_homebase' => '1'],
            ['id_dosen' => 'D2', 'nama_dosen' => 'DOSEN DUA', 'nama_tahun_ajaran' => '2025/2026', 'id_prodi' => 'P2', 'a_sp_homebase' => '1'],
        ]);
        DB::table('dosen_pengajar_kelas_kuliah')->insert([
            ['id_dosen' => 'D1', 'id_prodi' => 'P1', 'id_semester' => '20251', 'sks_substansi_total' => '3'],
            ['id_dosen' => 'D1', 'id_prodi' => 'P1', 'id_semester' => '20251', 'sks_substansi_total' => '2.5'],
            ['id_dosen' => 'D1', 'id_prodi' => 'P2', 'id_semester' => '20251', 'sks_substansi_total' => '2'],
            ['id_dosen' => 'D1', 'id_prodi' => 'P1', 'id_semester' => '20241', 'sks_substansi_total' => '9'],
        ]);

        $response = $this->getJson('/api/sapto/tables/ewmp-dosen/summary?ts=2025')->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.nama_dosen', 'DOSEN SATU')
            ->assertJsonPath('rows.0.ewmp_ps_lain_luar', null)
            ->assertJsonPath('rows.0.ewmp_penelitian', null);

        $this->assertEquals(5.5, $response->json('rows.0.ewmp_ps_diakreditasi'));
        $this->assertEquals(2, $response->json('rows.0.ewmp_ps_lain_pt'));
        $this->assertEquals(7.5, $response->json('rows.0.jumlah_sks'));
        // Baru semester ganjil yang ada: rata-rata = jumlah / 1, dan notice menyebutnya.
        $this->assertEquals(7.5, $response->json('rows.0.rata_rata_per_semester'));
        $this->assertStringContainsString('baru mencakup semester 20251', $response->json('notice'));
    }

    public function test_sitasi_dtps_does_not_leak_one_programs_articles_to_another(): void
    {
        DB::table('sister_list_penugasan')->insert([
            ['id' => 'G1', 'id_sdm' => 'S1', 'perguruan_tinggi' => 'Universitas Sriwijaya', 'unit_kerja' => 'Teknik Informatika', 'jenjang_pendidikan' => 'S1', 'apakah_penugasan_homebase' => '1', 'tanggal_mulai' => '2010-01-01', 'tanggal_keluar' => null],
            ['id' => 'G2', 'id_sdm' => 'S2', 'perguruan_tinggi' => 'Universitas Sriwijaya', 'unit_kerja' => 'Manajemen', 'jenjang_pendidikan' => 'S1', 'apakah_penugasan_homebase' => '1', 'tanggal_mulai' => '2010-01-01', 'tanggal_keluar' => null],
        ]);
        DB::table('sister_detail_publikasi')->insert([
            ['id' => 'A1', 'id_jenis_publikasi' => '22', 'judul' => 'Artikel Informatika', 'judul_artikel' => null, 'tanggal' => '2025-01-01', 'penulis' => json_encode([['nama' => 'DOSEN TI', 'id_sdm' => 'S1', 'urutan' => 1]])],
            ['id' => 'A2', 'id_jenis_publikasi' => '22', 'judul' => 'Artikel Manajemen', 'judul_artikel' => null, 'tanggal' => '2025-01-01', 'penulis' => json_encode([['nama' => 'DOSEN MNJ', 'id_sdm' => 'S2', 'urutan' => 1]])],
        ]);

        $this->getJson('/api/sapto/tables/sitasi-dtps/summary?ts=2025')->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.judul_artikel', 'Artikel Informatika')
            ->assertJsonPath('rows.0.jumlah_sitasi', null)
            ->assertJsonPath('source.template', true);

        // Korprodi prodi lain, cache TIDAK dikosongkan di antaranya.
        Penugasan::query()->delete();
        Penugasan::create(['peran' => 'korprodi', 'nip' => '199999999999999001', 'nama' => 'Test', 'lingkup' => '61201']);

        $this->getJson('/api/sapto/tables/sitasi-dtps/summary?ts=2025')->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.judul_artikel', 'Artikel Manajemen');
    }
}
