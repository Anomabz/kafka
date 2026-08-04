<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 8.d.2) Kesesuaian Bidang Kerja Lulusan. Satu format tabel untuk
 * semua jenjang (tidak bercabang seperti 8.d.1) -- lihat
 * SaptoKesesuaianBidangKerjaRepository. Kolom tracer study (terlacak,
 * tingkat kesesuaian) tidak punya sumber data sama sekali di database ini,
 * jadi yang diuji terutama: baris "Jumlah Lulusan" terhitung benar per
 * TS-4/TS-3/TS-2 (sama seperti 8.d.1), kolom yang muncul, dan notice saat
 * belum pilih prodi.
 */
class SaptoKesesuaianBidangKerjaTest extends TestCase
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

        DB::table('list_mahasiswa_lulus_do')->insert([
            // P1: TS-4=2020 (2 lulus), TS-3=2021 (1), TS-2=2022 (3).
            $this->lulus('P1', '20201'),
            $this->lulus('P1', '20201'),
            $this->lulus('P1', '20211'),
            $this->lulus('P1', '20221'),
            $this->lulus('P1', '20221'),
            $this->lulus('P1', '20221'),
            // Di luar cakupan tabel (TS-1) -> tidak boleh ikut terhitung.
            $this->lulus('P1', '20231'),
            // Bukan "Lulus" -> diabaikan.
            $this->lulus('P1', '20201', 'Putus Studi'),
        ]);
    }

    private function lulus(string $prodi, string $periode, string $jenisKeluar = 'Lulus'): array
    {
        return [
            'nama_jenis_keluar' => $jenisKeluar,
            'id_prodi' => $prodi,
            'id_periode_keluar' => $periode,
        ];
    }

    public function test_columns_and_counts(): void
    {
        $response = $this->getJson('/api/sapto/tables/kesesuaian-bidang-kerja/summary?ts=2024&program_studi_id=P1')
            ->assertOk()
            ->assertJsonPath('table_code', '8.d.2')
            ->assertJsonPath('table_name', 'Kesesuaian Bidang Kerja Lulusan')
            ->assertJsonPath('notice', null);

        $columnKeys = collect($response->json('columns'))->pluck('key')->all();
        $this->assertSame(
            ['no', 'tahun_lulus', 'jumlah_lulusan', 'jumlah_terlacak', 'rendah', 'sedang', 'tinggi'],
            $columnKeys
        );
        $this->assertSame('Rendah', $response->json('columns.4.label'));
        $this->assertSame('Sedang', $response->json('columns.5.label'));
        $this->assertSame('Tinggi', $response->json('columns.6.label'));

        // Rendah/Sedang/Tinggi berbagi header kelompok yang sama (dirender
        // sebagai satu <th colspan> di atas ketiganya) -- lihat renderGroupedHead.
        $group = 'Jumlah Lulusan Terlacak dengan Tingkat Kesesuaian Bidang Kerja';
        $this->assertSame($group, $response->json('columns.4.group'));
        $this->assertSame($group, $response->json('columns.5.group'));
        $this->assertSame($group, $response->json('columns.6.group'));
        $this->assertNull($response->json('columns.3.group'));

        $rows = $response->json('rows');
        $this->assertSame('2020 (TS-4)', $rows[0]['tahun_lulus']);
        $this->assertSame(2, $rows[0]['jumlah_lulusan']);
        $this->assertSame('2021 (TS-3)', $rows[1]['tahun_lulus']);
        $this->assertSame(1, $rows[1]['jumlah_lulusan']);
        $this->assertSame('2022 (TS-2)', $rows[2]['tahun_lulus']);
        $this->assertSame(3, $rows[2]['jumlah_lulusan']);

        // Kolom tracer study selalu 0: tidak ada sumbernya di database ini.
        foreach ($rows as $row) {
            $this->assertSame(0, $row['jumlah_terlacak']);
            $this->assertSame(0, $row['rendah']);
            $this->assertSame(0, $row['sedang']);
            $this->assertSame(0, $row['tinggi']);
        }

        $jumlah = $rows[3];
        $this->assertNull($jumlah['no']);
        $this->assertSame('Jumlah', $jumlah['tahun_lulus']);
        $this->assertSame(6, $jumlah['jumlah_lulusan']);

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(6, $metrics['Total lulusan (TS-4..TS-2)']);
        $this->assertSame(3, $metrics['Lulusan TS-2']);
    }

    public function test_no_program_studi_selected_returns_notice(): void
    {
        $this->getJson('/api/sapto/tables/kesesuaian-bidang-kerja/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('columns', [])
            ->assertJson(fn ($json) => $json->has('notice')->where('notice', fn ($notice) => str_contains($notice, 'per program studi'))->etc());
    }
}
