<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tabel 8.c) Masa Studi Lulusan. Sumber list_mahasiswa (satu baris per
 * mahasiswa: id_periode masuk, id_periode_keluar + status keluar) -- lihat
 * SaptoMasaStudiRepository untuk alasan pindah dari
 * list_riwayat_pendidikan_mahasiswa + list_mahasiswa_lulus_do.
 *
 * "Diterima" dihitung langsung dari id_periode per prodi; "lulus"/"masa
 * studi" dari id_periode_keluar bila statusnya "Lulus", dengan fallback ke
 * tanggal_keluar untuk baris yang id_periode_keluar-nya kosong (~18% baris
 * Lulus di data nyata) -- fallback itu tetap menghitung baris ke kolom
 * "Lulus Akhir TS-x" tapi TIDAK ke rata-rata masa studi (butuh presisi
 * semester dari id_periode_keluar).
 */
class SaptoMasaStudiTest extends TestCase
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
            ['id_prodi' => 'P1', 'kode_program_studi' => '55201', 'nama_program_studi' => 'Teknik Informatika', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 30, 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'P2', 'kode_program_studi' => '55301', 'nama_program_studi' => 'Manajemen Informatika', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 22, 'nama_jenjang_pendidikan' => 'D3'],
        ]);

        \App\Models\Penugasan::create([
            'peran' => 'korprodi',
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'lingkup' => '55201',
        ]);
    }

    private function mhs(string $prodi, string $periodeMasuk, string $status, ?string $periodeKeluar = null, ?string $tanggalKeluar = null): array
    {
        return [
            'id_registrasi_mahasiswa' => (string) Str::uuid(),
            'id_prodi' => $prodi,
            'id_periode' => $periodeMasuk,
            'nama_status_mahasiswa' => $status,
            'id_periode_keluar' => $periodeKeluar,
            'tanggal_keluar' => $tanggalKeluar,
        ];
    }

    public function test_cohort_matrix_diterima_grad_buckets_and_masa_studi(): void
    {
        DB::table('list_mahasiswa')->insert([
            // Angkatan 2019 (S1 -> kohort TS-6..TS-3 utk ts=2024, jadi 2019 = TS-5): 3 diterima.
            $this->mhs('P1', '20191', 'Lulus', '20231'),      // idx 4046-4038+1=9 -> 4.5 thn, lulus 2023 (TS-1)
            $this->mhs('P1', '20191', 'Lulus', '20241'),      // idx 4048-4038+1=11 -> 5.5 thn, lulus 2024 (TS)
            $this->mhs('P1', '20191', 'Aktif'),                // belum lulus -> cuma nambah diterima
            // Angkatan 2020 (TS-4): 2 diterima, dua-duanya lulus 2024 (TS), masa 4.5 thn.
            $this->mhs('P1', '20201', 'Lulus', '20241'),      // idx 4048-4040+1=9 -> 4.5 thn
            $this->mhs('P1', '20201', 'Lulus', '20241'),      // idx 4048-4040+1=9 -> 4.5 thn
            // Di luar prodi (P2, D3) -> tak boleh ikut terhitung di scope P1.
            $this->mhs('P2', '20191', 'Lulus', '20241'),
        ]);

        $response = $this->getJson('/api/sapto/tables/masa-studi/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '8.c')
            ->assertJsonPath('program_studi.id', 'P1');

        $rows = collect($response->json('rows'));

        $c2019 = $rows->firstWhere('tahun_masuk', '2019 (TS-5)');
        $this->assertSame(3, $c2019['diterima']);
        $this->assertSame(1, $c2019['g_1']); // lulus 2023 -> Akhir TS-1
        $this->assertSame(1, $c2019['g_0']); // lulus 2024 -> Akhir TS
        $this->assertSame(2, $c2019['lulus_sd_ts']);
        $this->assertEquals(5.0, $c2019['masa_studi_rata']); // (4.5+5.5)/2

        $c2020 = $rows->firstWhere('tahun_masuk', '2020 (TS-4)');
        $this->assertSame(2, $c2020['diterima']);
        $this->assertSame(2, $c2020['g_0']);
        $this->assertSame(2, $c2020['lulus_sd_ts']);
        $this->assertEquals(4.5, $c2020['masa_studi_rata']);
    }

    public function test_missing_id_periode_keluar_falls_back_to_tanggal_keluar_for_grad_bucket_only(): void
    {
        DB::table('list_mahasiswa')->insert([
            // id_periode_keluar kosong (~18% kasus nyata) -> tahun lulus dipulihkan
            // dari tanggal_keluar (DD-MM-YYYY), tapi TIDAK ikut rata-rata masa
            // studi karena idx_lulus tidak bisa dihitung tanpa id_periode_keluar.
            $this->mhs('P1', '20211', 'Lulus', null, '15-06-2024'),
        ]);

        $rows = collect($this->getJson('/api/sapto/tables/masa-studi/summary?ts=2024')->assertOk()->json('rows'));

        $c2021 = $rows->firstWhere('tahun_masuk', '2021 (TS-3)');
        $this->assertSame(1, $c2021['diterima']);
        $this->assertSame(1, $c2021['g_0']); // tetap terhitung lulus 2024 lewat fallback tanggal
        $this->assertNull($c2021['masa_studi_rata']); // tapi tidak ikut rata-rata (idx_lulus tak ada)
    }

    public function test_columns_follow_d3_jenjang_pattern(): void
    {
        $this->withSession(['sso_user' => [
            'sub' => 'test-d3', 'name' => 'Test D3', 'email' => 'test-d3@example.test',
            'nomor_identitas' => '199999999999999002',
        ]]);
        \App\Models\Penugasan::create([
            'peran' => 'korprodi',
            'nip' => '199999999999999002',
            'nama' => 'Test Korprodi D3',
            'lingkup' => '55301',
        ]);

        $response = $this->getJson('/api/sapto/tables/masa-studi/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('program_studi.id', 'P2');

        // D3 -> kohort TS-4/TS-3/TS-2 (3 baris), kolom lulus Akhir TS-4..TS (5 kolom).
        $columnKeys = collect($response->json('columns'))->pluck('key')->all();
        $this->assertSame(
            ['no', 'tahun_masuk', 'diterima', 'g_4', 'g_3', 'g_2', 'g_1', 'g_0', 'lulus_sd_ts', 'masa_studi_rata'],
            $columnKeys
        );
        $this->assertSame(3, collect($response->json('rows'))->count());
    }
}
