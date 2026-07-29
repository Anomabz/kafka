<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 3.a.1) Dosen Tetap Perguruan Tinggi.
 */
class SaptoDosenTetapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->withSession(['sso_user' => [
            'sub' => 'test', 'name' => 'Test', 'email' => 'test@example.test',
            'nomor_identitas' => '199999999999999001',
        ]]);

        DB::table('program_studi')->insert([
            'id_prodi' => 'P1',
            'kode_program_studi' => '55201',
            'nama_program_studi' => 'Teknik Informatika',
            'status' => 'AKTIF',
            'id_jenjang_pendidikan' => 1,
            'nama_jenjang_pendidikan' => 'S1',
        ]);

        \App\Models\Korprodi::create([
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'kode_program_studi' => '55201',
        ]);
    }

    public function test_excludes_biro_rows_that_are_not_a_real_program_studi(): void
    {
        DB::table('list_penugasan_dosen')->insert([
            // D1 homebase di P1, prodi asli -> ikut.
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2024/2025'],
            // Pegawai biro tercatat "homebase" ke unit non-akademik -> bukan dosen, harus difilter.
            ['id_dosen' => 'D2', 'nama_dosen' => 'Budi Staf', 'nidn' => '', 'jk' => 'L', 'id_prodi' => 'BIRO1', 'nama_program_studi' => '(tidak diisi) Biro Umum dan Keuangan', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2024/2025'],
        ]);

        $response = $this->getJson('/api/sapto/tables/dosen-tetap-pt/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '3.a.1');

        $rows = collect($response->json('rows'));

        $this->assertSame(1, $rows->count());
        $this->assertSame('D1', $rows[0]['dosen_id']);

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(1, $metrics['Total dosen tetap']);
    }
}
