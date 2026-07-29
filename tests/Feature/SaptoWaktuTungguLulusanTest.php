<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 8.d.1) Waktu Tunggu Lulusan. Satu tabel, dua varian kolom menurut
 * jenjang prodi terpilih (Diploma Tiga / Sarjana) -- lihat
 * SaptoWaktuTungguLulusanRepository. Kolom tracer study (terlacak/dipesan/
 * waktu tunggu) tidak punya sumber data sama sekali di database ini, jadi
 * yang diuji terutama: baris "Jumlah Lulusan" terhitung benar per TS-4/
 * TS-3/TS-2, kolom yang benar muncul per jenjang, dan jenjang di luar
 * D3/S1 (mis. S2) mendapat notice, bukan tabel kosong.
 *
 * Sesi admin (is_admin) dipakai, bukan korprodi: role 'prodi' mengunci
 * program_studi_id ke satu prodi tetap (lihat SaptoUserContext::
 * resolveProgramStudiId()), jadi tidak bisa dipakai untuk menguji tiga
 * prodi berbeda + kondisi "belum pilih prodi" dalam satu test class.
 */
class SaptoWaktuTungguLulusanTest extends TestCase
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
            ['id_prodi' => 'P3', 'kode_program_studi' => '55301', 'nama_program_studi' => 'Manajemen Informatika', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 2, 'nama_jenjang_pendidikan' => 'D3'],
            ['id_prodi' => 'P4', 'kode_program_studi' => '55401', 'nama_program_studi' => 'Ilmu Komputer', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 3, 'nama_jenjang_pendidikan' => 'S2'],
        ]);

        DB::table('list_mahasiswa_lulus_do')->insert([
            // P1 (S1): TS-4=2020 (2 lulus), TS-3=2021 (1), TS-2=2022 (3).
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

            // P3 (D3): TS-4=2020 (1), TS-2=2022 (2).
            $this->lulus('P3', '20201'),
            $this->lulus('P3', '20221'),
            $this->lulus('P3', '20221'),
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

    public function test_sarjana_variant_columns_and_counts(): void
    {
        $response = $this->getJson('/api/sapto/tables/waktu-tunggu/summary?ts=2024&program_studi_id=P1')
            ->assertOk()
            ->assertJsonPath('table_code', '8.d.1')
            ->assertJsonPath('table_name', 'Waktu Tunggu Lulusan')
            ->assertJsonPath('notice', null);

        $columnKeys = collect($response->json('columns'))->pluck('key')->all();
        $this->assertSame(
            ['no', 'tahun_lulus', 'jumlah_lulusan', 'jumlah_terlacak', 'wt_1', 'wt_2', 'wt_3'],
            $columnKeys,
            'Varian Sarjana tidak boleh punya kolom "jumlah_dipesan".'
        );
        $this->assertSame('WT < 6 Bulan', $response->json('columns.4.label'));
        $this->assertSame('6 ≤ WT ≤ 18 Bulan', $response->json('columns.5.label'));
        $this->assertSame('WT > 18 Bulan', $response->json('columns.6.label'));

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
            $this->assertSame(0, $row['wt_1']);
            $this->assertSame(0, $row['wt_2']);
            $this->assertSame(0, $row['wt_3']);
        }

        $jumlah = $rows[3];
        $this->assertNull($jumlah['no']);
        $this->assertSame('Jumlah', $jumlah['tahun_lulus']);
        $this->assertSame(6, $jumlah['jumlah_lulusan']);

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(6, $metrics['Total lulusan (TS-4..TS-2)']);
        $this->assertSame(3, $metrics['Lulusan TS-2']);
    }

    public function test_diploma_tiga_variant_has_dipesan_column(): void
    {
        $response = $this->getJson('/api/sapto/tables/waktu-tunggu/summary?ts=2024&program_studi_id=P3')
            ->assertOk();

        $columnKeys = collect($response->json('columns'))->pluck('key')->all();
        $this->assertSame(
            ['no', 'tahun_lulus', 'jumlah_lulusan', 'jumlah_terlacak', 'jumlah_dipesan', 'wt_1', 'wt_2', 'wt_3'],
            $columnKeys
        );
        $this->assertSame('Jumlah Lulusan yang Dipesan Sebelum Lulus', $response->json('columns.4.label'));
        $this->assertSame('WT < 3 Bulan', $response->json('columns.5.label'));
        $this->assertSame('3 ≤ WT ≤ 6 Bulan', $response->json('columns.6.label'));
        $this->assertSame('WT > 6 Bulan', $response->json('columns.7.label'));

        $rows = $response->json('rows');
        $this->assertSame(1, $rows[0]['jumlah_lulusan']); // TS-4 2020
        $this->assertSame(0, $rows[1]['jumlah_lulusan']); // TS-3 2021, tidak ada lulusan
        $this->assertSame(2, $rows[2]['jumlah_lulusan']); // TS-2 2022
        $this->assertSame(0, $rows[0]['jumlah_dipesan']);
    }

    public function test_no_program_studi_selected_returns_notice(): void
    {
        $this->getJson('/api/sapto/tables/waktu-tunggu/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('columns', [])
            ->assertJson(fn ($json) => $json->has('notice')->where('notice', fn ($notice) => str_contains($notice, 'per program studi'))->etc());
    }

    public function test_unsupported_jenjang_returns_notice_not_empty_table(): void
    {
        $response = $this->getJson('/api/sapto/tables/waktu-tunggu/summary?ts=2024&program_studi_id=P4')
            ->assertOk()
            ->assertJsonPath('rows', []);

        $this->assertStringContainsString('Diploma Tiga atau Sarjana', $response->json('notice'));
        $this->assertStringContainsString('S2', $response->json('notice'));
    }
}
