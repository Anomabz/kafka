<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 3.b.2) Penelitian DTPS. Sumber: sister_detail_penelitian (anggota
 * peneliti sebagai JSON) disaring ke dosen tetap program studi (DTPS) lewat
 * roster homebase list_penugasan_dosen per tahun, lalu diklasifikasi per
 * sumber pembiayaan.
 */
class SaptoPenelitianDtpsTest extends TestCase
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
            ['id_prodi' => 'P1', 'kode_program_studi' => '55201', 'nama_program_studi' => 'Teknik Informatika', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 1, 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'P2', 'kode_program_studi' => '61101', 'nama_program_studi' => 'Manajemen', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 1, 'nama_jenjang_pendidikan' => 'S1'],
        ]);

        // Auto-scopes this session to P1 (kode 55201) via the korprodi
        // mapping — X-SAPTO-* headers are no longer trusted for scope.
        \App\Models\Korprodi::create([
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'kode_program_studi' => '55201',
        ]);
    }

    private function anggota(string $idDosen): string
    {
        return json_encode([['jenis' => 'Dosen', 'nama' => 'Dosen '.$idDosen, 'id_sdm' => $idDosen]]);
    }

    public function test_summary_classifies_by_funding_source_and_scopes_to_dtps(): void
    {
        DB::table('list_penugasan_dosen')->insert([
            // D1: homebase P1 di TS-2/TS-1/TS -> DTPS P1 tiap tahun.
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2022/2023'],
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2023/2024'],
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2024/2025'],
            // D2: mengajar di P1 tapi bukan homebase -> bukan DTPS.
            ['id_dosen' => 'D2', 'nama_dosen' => 'Dr. Budi', 'nidn' => '222', 'jk' => 'L', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '0', 'nama_tahun_ajaran' => '2024/2025'],
            // D3: homebase, tapi di P2 -> DTPS P2, bukan P1.
            ['id_dosen' => 'D3', 'nama_dosen' => 'Dr. Candra', 'nidn' => '333', 'jk' => 'L', 'id_prodi' => 'P2', 'nama_program_studi' => 'Manajemen', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2024/2025'],
        ]);

        DB::table('sister_detail_penelitian')->insert([
            // A: dana PT -> pt_mandiri (TS). Disisipkan 2x untuk menguji dedup by id.
            ['id' => 'A', 'tahun_pelaksanaan' => '2024', 'dana_dikti' => '0', 'dana_perguruan_tinggi' => '1000000', 'dana_institusi_lain' => '0', 'jenis_skim' => null, 'anggota' => $this->anggota('D1')],
            ['id' => 'A', 'tahun_pelaksanaan' => '2024', 'dana_dikti' => '0', 'dana_perguruan_tinggi' => '1000000', 'dana_institusi_lain' => '0', 'jenis_skim' => null, 'anggota' => $this->anggota('D1')],
            // B: dana DIKTI -> dalam_negeri (TS).
            ['id' => 'B', 'tahun_pelaksanaan' => '2024', 'dana_dikti' => '5000000', 'dana_perguruan_tinggi' => '0', 'dana_institusi_lain' => '0', 'jenis_skim' => 'Penelitian Dana Lokal Perguruan Tinggi', 'anggota' => $this->anggota('D1')],
            // C: dana institusi lain + skim luar negeri -> luar_negeri saja (TS), bukan dalam_negeri.
            ['id' => 'C', 'tahun_pelaksanaan' => '2024', 'dana_dikti' => '0', 'dana_perguruan_tinggi' => '0', 'dana_institusi_lain' => '2000000', 'jenis_skim' => 'Kerjasama Luar Negeri dan Publikasi Internasional', 'anggota' => $this->anggota('D1')],
            // D: tanpa dana tercatat -> mandiri -> pt_mandiri (TS).
            ['id' => 'D', 'tahun_pelaksanaan' => '2024', 'dana_dikti' => '0', 'dana_perguruan_tinggi' => '0', 'dana_institusi_lain' => '0', 'jenis_skim' => null, 'anggota' => $this->anggota('D1')],
            // E: anggotanya D2 (bukan DTPS) -> tak terhitung sama sekali.
            ['id' => 'E', 'tahun_pelaksanaan' => '2024', 'dana_dikti' => '0', 'dana_perguruan_tinggi' => '500000', 'dana_institusi_lain' => '0', 'jenis_skim' => null, 'anggota' => $this->anggota('D2')],
            // F: anggotanya D3 (DTPS P2, bukan P1) -> tak terhitung saat scope P1.
            ['id' => 'F', 'tahun_pelaksanaan' => '2024', 'dana_dikti' => '0', 'dana_perguruan_tinggi' => '500000', 'dana_institusi_lain' => '0', 'jenis_skim' => null, 'anggota' => $this->anggota('D3')],
            // G: TS-1, dana PT -> pt_mandiri (TS-1).
            ['id' => 'G', 'tahun_pelaksanaan' => '2023', 'dana_dikti' => '0', 'dana_perguruan_tinggi' => '700000', 'dana_institusi_lain' => '0', 'jenis_skim' => null, 'anggota' => $this->anggota('D1')],
            // H: TS-2, dana DIKTI -> dalam_negeri (TS-2).
            ['id' => 'H', 'tahun_pelaksanaan' => '2022', 'dana_dikti' => '300000', 'dana_perguruan_tinggi' => '0', 'dana_institusi_lain' => '0', 'jenis_skim' => null, 'anggota' => $this->anggota('D1')],
        ]);

        $response = $this->getJson('/api/sapto/tables/penelitian-dtps/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '3.b.2')
            ->assertJsonPath('table_name', 'Penelitian DTPS');

        $rows = collect($response->json('rows'))->keyBy('sumber_pembiayaan');

        $ptMandiri = $rows->get('a) Perguruan Tinggi b) Mandiri');
        $this->assertSame(['ts_2' => 0, 'ts_1' => 1, 'ts' => 2, 'jumlah' => 3], [
            'ts_2' => $ptMandiri['ts_2'], 'ts_1' => $ptMandiri['ts_1'], 'ts' => $ptMandiri['ts'], 'jumlah' => $ptMandiri['jumlah'],
        ]);

        $dalamNegeri = $rows->get('Lembaga dalam negeri (di luar PT)');
        $this->assertSame(['ts_2' => 1, 'ts_1' => 0, 'ts' => 1, 'jumlah' => 2], [
            'ts_2' => $dalamNegeri['ts_2'], 'ts_1' => $dalamNegeri['ts_1'], 'ts' => $dalamNegeri['ts'], 'jumlah' => $dalamNegeri['jumlah'],
        ]);

        $luarNegeri = $rows->get('Lembaga luar negeri');
        $this->assertSame(['ts_2' => 0, 'ts_1' => 0, 'ts' => 1, 'jumlah' => 1], [
            'ts_2' => $luarNegeri['ts_2'], 'ts_1' => $luarNegeri['ts_1'], 'ts' => $luarNegeri['ts'], 'jumlah' => $luarNegeri['jumlah'],
        ]);

        $jumlah = $rows->get('Jumlah');
        $this->assertNull($jumlah['no']);
        $this->assertSame(['ts_2' => 1, 'ts_1' => 1, 'ts' => 4, 'jumlah' => 6], [
            'ts_2' => $jumlah['ts_2'], 'ts_1' => $jumlah['ts_1'], 'ts' => $jumlah['ts'], 'jumlah' => $jumlah['jumlah'],
        ]);

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(6, $metrics['Total judul penelitian DTPS (TS-2..TS)']);
        $this->assertSame(4, $metrics['Judul penelitian DTPS (TS)']);
        $this->assertSame(1, $metrics['Judul penelitian DTPS (TS-1)']);
    }

    public function test_ts_defaults_to_latest_year_with_homebase_data(): void
    {
        DB::table('list_penugasan_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2023/2024'],
            ['id_dosen' => 'D2', 'nama_dosen' => 'Dr. Budi', 'nidn' => '222', 'jk' => 'L', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2024/2025'],
        ]);

        $this->getJson('/api/sapto/tables/penelitian-dtps/summary')
            ->assertOk()
            ->assertJsonPath('ts', 2024)
            ->assertJsonPath('available_years', [2024, 2023]);
    }
}
