<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 8.b.2) Prestasi Non-akademik Mahasiswa. Sama seperti 8.b.1 (lihat
 * SaptoPrestasiAkademikTest untuk cakupan uji resolusi prodi/tingkat/
 * peringkat/jendela tahun yang identik lewat basis SaptoPrestasiRepository)
 * -- di sini fokus ke yang beda: jenis_prestasi mana yang masuk varian ini.
 */
class SaptoPrestasiNonAkademikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->withSession(['sso_user' => [
            'sub' => 'admin-test', 'name' => 'Admin Test', 'email' => 'admin@example.test',
            'nomor_identitas' => '000000000000000001', 'is_admin' => true,
        ]]);

        DB::table('program_studi')->insert([
            ['id_prodi' => 'P1', 'kode_program_studi' => '55201', 'nama_program_studi' => 'Teknik Informatika', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 1, 'nama_jenjang_pendidikan' => 'S1'],
        ]);
    }

    private function prestasi(string $idMahasiswa, string $jenis, string $tahun = '2024', string $nama = 'Lomba Uji'): array
    {
        return [
            'id_prestasi' => (string) \Illuminate\Support\Str::uuid(),
            'id_mahasiswa' => $idMahasiswa,
            'nama_prestasi' => $nama,
            'nama_jenis_prestasi' => $jenis,
            'nama_tingkat_prestasi' => 'Nasional',
            'peringkat' => '1',
            'tahun_prestasi' => $tahun,
        ];
    }

    public function test_olahraga_seni_dan_lain_lain_included_sains_excluded(): void
    {
        DB::table('list_mahasiswa')->insert([
            ['id_mahasiswa' => 'M1', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M2', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M3', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M4', 'id_prodi' => 'P1', 'id_periode' => '20241'],
        ]);

        DB::table('list_prestasi_mahasiswa')->insert([
            $this->prestasi('M1', 'Olahraga', '2024', 'Futsal'),
            $this->prestasi('M2', 'Seni', '2024', 'Band Competition'),
            $this->prestasi('M3', 'Lain-lain', '2024', 'Debat'),
            $this->prestasi('M4', 'Sains', '2024', 'Lomba KTI'),
        ]);

        $response = $this->getJson('/api/sapto/tables/prestasi-non-akademik/summary?ts=2024&program_studi_id=P1')
            ->assertOk()
            ->assertJsonPath('table_code', '8.b.2')
            ->assertJsonPath('table_name', 'Prestasi Non-akademik Mahasiswa');

        $rows = collect($response->json('rows'))->pluck('nama_kegiatan');

        $this->assertTrue($rows->contains('Futsal'));
        $this->assertTrue($rows->contains('Band Competition'));
        $this->assertTrue($rows->contains('Debat'), '"Lain-lain" adalah kategori residual non-akademik.');
        $this->assertFalse($rows->contains('Lomba KTI'), '"Sains" cuma boleh masuk 8.b.1, bukan 8.b.2.');
        $this->assertCount(3, $rows);
    }

    public function test_same_achievement_never_appears_in_both_tables(): void
    {
        DB::table('list_mahasiswa')->insert([
            ['id_mahasiswa' => 'M1', 'id_prodi' => 'P1', 'id_periode' => '20241'],
        ]);

        DB::table('list_prestasi_mahasiswa')->insert([
            $this->prestasi('M1', 'Olahraga', '2024', 'PON Cabor Renang'),
        ]);

        $akademik = collect($this->getJson('/api/sapto/tables/prestasi-akademik/summary?ts=2024&program_studi_id=P1')
            ->assertOk()->json('rows'))->pluck('nama_kegiatan');
        $nonAkademik = collect($this->getJson('/api/sapto/tables/prestasi-non-akademik/summary?ts=2024&program_studi_id=P1')
            ->assertOk()->json('rows'))->pluck('nama_kegiatan');

        $this->assertFalse($akademik->contains('PON Cabor Renang'));
        $this->assertTrue($nonAkademik->contains('PON Cabor Renang'));
    }
}
