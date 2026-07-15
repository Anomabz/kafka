<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 3.a.4) Dosen Tidak Tetap. Sumber sama dengan Dosen Tetap
 * (list_penugasan_dosen) tapi baris a_sp_homebase = 0: dosen ditugaskan
 * mengajar di prodi tsb tanpa itu jadi homebase-nya.
 */
class SaptoDosenTidakTetapTest extends TestCase
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
            [
                'id_prodi' => 'P1',
                'kode_program_studi' => '55201',
                'nama_program_studi' => 'Teknik Informatika',
                'status' => 'AKTIF',
                'id_jenjang_pendidikan' => 1,
                'nama_jenjang_pendidikan' => 'S1',
            ],
            [
                'id_prodi' => 'P2',
                'kode_program_studi' => '61101',
                'nama_program_studi' => 'Manajemen',
                'status' => 'AKTIF',
                'id_jenjang_pendidikan' => 1,
                'nama_jenjang_pendidikan' => 'S1',
            ],
        ]);

        // Auto-scopes this session to P1 (kode 55201) via the korprodi
        // mapping — X-SAPTO-* headers are no longer trusted for scope.
        \App\Models\Korprodi::create([
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'kode_program_studi' => '55201',
        ]);
    }

    public function test_lists_non_homebase_assignments_and_ignores_homebase_rows(): void
    {
        DB::table('list_penugasan_dosen')->insert([
            // D1 homebase (tetap) di P1 -> bukan baris tidak tetap.
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2024/2025'],
            // D2 tidak tetap di P1 -> ikut.
            ['id_dosen' => 'D2', 'nama_dosen' => 'Dr. Budi', 'nidn' => '222', 'jk' => 'L', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '0', 'nama_tahun_ajaran' => '2024/2025'],
            // Duplikat (D2,P1) tercatat 2x (mis. semester ganjil & genap) -> harus di-dedup jadi satu baris.
            ['id_dosen' => 'D2', 'nama_dosen' => 'Dr. Budi', 'nidn' => '222', 'jk' => 'L', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '0', 'nama_tahun_ajaran' => '2024/2025'],
        ]);

        $response = $this->getJson('/api/sapto/tables/dosen-tidak-tetap/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '3.a.4')
            ->assertJsonPath('columns.10', ['key' => 'kesesuaian_mk', 'label' => 'Kesesuaian Bidang Keahlian dengan Mata Kuliah yang Diampu', 'align' => 'left']);

        $rows = collect($response->json('rows'));

        // 1 baris: D2@P1 (duplikat di-dedup). D1@P1 (homebase) tidak ikut.
        $this->assertSame(1, $rows->count());
        $this->assertEqualsCanonicalizing(
            ['D2|S1 Teknik Informatika'],
            $rows->map(fn (array $r): string => "{$r['dosen_id']}|{$r['program_studi']}")->all(),
        );

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(1, $metrics['Total dosen tidak tetap']);
        $this->assertSame(1, $metrics['Laki-laki']);
        $this->assertSame(0, $metrics['Perempuan']);
    }

    public function test_respects_prodi_scope(): void
    {
        DB::table('list_penugasan_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '0', 'nama_tahun_ajaran' => '2024/2025'],
            ['id_dosen' => 'D2', 'nama_dosen' => 'Dr. Budi', 'nidn' => '222', 'jk' => 'L', 'id_prodi' => 'P2', 'nama_program_studi' => 'Manajemen', 'a_sp_homebase' => '0', 'nama_tahun_ajaran' => '2024/2025'],
        ]);

        $rows = collect($this->getJson('/api/sapto/tables/dosen-tidak-tetap/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('program_studi.id', 'P1')
            ->json('rows'));

        $this->assertSame(1, $rows->count());
        $this->assertSame('D1', $rows[0]['dosen_id']);
    }

    public function test_ts_defaults_to_latest_year_with_non_homebase_data(): void
    {
        DB::table('list_penugasan_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '0', 'nama_tahun_ajaran' => '2023/2024'],
            ['id_dosen' => 'D2', 'nama_dosen' => 'Dr. Budi', 'nidn' => '222', 'jk' => 'L', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '0', 'nama_tahun_ajaran' => '2024/2025'],
        ]);

        $this->getJson('/api/sapto/tables/dosen-tidak-tetap/summary')
            ->assertOk()
            ->assertJsonPath('ts', 2024)
            ->assertJsonPath('available_years', [2024, 2023]);
    }
}
