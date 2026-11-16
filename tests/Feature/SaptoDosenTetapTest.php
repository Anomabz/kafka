<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        \App\Models\Penugasan::create([
            'peran' => 'korprodi',
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'lingkup' => '55201',
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

    public function test_dosen_tetap_mata_kuliah_dipisahkan_berdasarkan_prodi_lingkup(): void
    {
        Schema::create('aktivitas_mengajar_dosen', function ($table): void {
            $table->string('id_registrasi_dosen')->nullable();
            $table->string('id_dosen')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('id_periode')->nullable();
            $table->string('nama_periode')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('id_matkul')->nullable();
            $table->string('nama_mata_kuliah')->nullable();
            $table->string('id_kelas')->nullable();
            $table->string('nama_kelas_kuliah')->nullable();
            $table->string('rencana_minggu_pertemuan')->nullable();
            $table->string('realisasi_minggu_pertemuan')->nullable();
        });

        $pengajarColumns = Schema::getColumnListing('aktivitas_mengajar_dosen');
        $this->assertContains('nama_mata_kuliah', $pengajarColumns);

        DB::table('program_studi')->insert([
            'id_prodi' => 'P2',
            'kode_program_studi' => '61101',
            'nama_program_studi' => 'Sistem Informasi',
            'status' => 'AKTIF',
            'id_jenjang_pendidikan' => 1,
            'nama_jenjang_pendidikan' => 'S1',
        ]);

        DB::table('aktivitas_mengajar_dosen')->insert([
            ['id_dosen' => 'D1', 'id_periode' => '20241', 'nama_periode' => '2024/2025 Ganjil', 'id_prodi' => 'P1', 'id_matkul' => 'MK1', 'nama_mata_kuliah' => 'Basis Data', 'id_kelas' => 'K1'],
            ['id_dosen' => 'D1', 'id_periode' => '20241', 'nama_periode' => '2024/2025 Ganjil', 'id_prodi' => 'P1', 'id_matkul' => 'MK2', 'nama_mata_kuliah' => 'Algoritma', 'id_kelas' => 'K2'],
            ['id_dosen' => 'D1', 'id_periode' => '20241', 'nama_periode' => '2024/2025 Ganjil', 'id_prodi' => 'P2', 'id_matkul' => 'MK3', 'nama_mata_kuliah' => 'Jaringan Komputer', 'id_kelas' => 'K3'],
            ['id_dosen' => 'D1', 'id_periode' => '20231', 'nama_periode' => '2023/2024 Ganjil', 'id_prodi' => 'P1', 'id_matkul' => 'MK4', 'nama_mata_kuliah' => 'Mata Kuliah Lama', 'id_kelas' => 'K4'],
            ['id_dosen' => 'D1', 'id_periode' => '20241', 'nama_periode' => '2024/2025 Ganjil', 'id_prodi' => 'P1', 'id_matkul' => 'MK1', 'nama_mata_kuliah' => 'Basis Data', 'id_kelas' => 'K5'],
        ]);

        $repository = app(\App\Services\SaptoDosenTetapRepository::class);
        $method = new \ReflectionMethod($repository, 'mataKuliahByDosen');
        $mataKuliah = $method->invoke($repository, 2024, ['D1'], ['P1']);

        $this->assertSame(['Algoritma', 'Basis Data'], $mataKuliah['D1']['diakreditasi']);
        $this->assertSame(['Jaringan Komputer'], $mataKuliah['D1']['ps_lain']);

        $semuaProdi = $method->invoke($repository, 2024, ['D1'], null);
        $this->assertSame(['Algoritma', 'Basis Data', 'Jaringan Komputer'], $semuaProdi['D1']['diakreditasi']);
        $this->assertSame([], $semuaProdi['D1']['ps_lain']);

        DB::table('aktivitas_mengajar_dosen')->insert([
            'id_dosen' => 'D1', 'id_periode' => '20251', 'nama_periode' => '2025/2026 Ganjil',
            'id_prodi' => 'P1', 'id_matkul' => 'MK5', 'nama_mata_kuliah' => 'Analisis Data', 'id_kelas' => 'K6',
        ]);
        $periode2025 = $method->invoke($repository, 2025, ['D1'], ['P1']);
        $this->assertSame(['Analisis Data'], $periode2025['D1']['diakreditasi']);
    }
}
