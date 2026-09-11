<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 3.b.4) Publikasi Ilmiah DTPS. Sumber: sister_list_publikasi (satu
 * baris per penulis, digabung per id) disaring ke dosen tetap program
 * studi (DTPS) lewat roster homebase sister_list_penugasan per tahun
 * (penugasan terakhir yang masih aktif + jenjang S1/S2/S3), lalu
 * diklasifikasi per jenis publikasi.
 */
class SaptoPublikasiIlmiahDtpsTest extends TestCase
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

        \App\Models\Penugasan::create([
            'peran' => 'korprodi',
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'lingkup' => '55201',
        ]);
    }

    public function test_summary_classifies_by_jenis_publikasi_and_scopes_to_dtps(): void
    {
        DB::table('sister_list_penugasan')->insert([
            // D1: homebase P1, S2, aktif sejak 2020 -> DTPS P1.
            ['id' => 'PG1', 'id_sdm' => 'D1', 'jenjang_pendidikan' => 'S2', 'unit_kerja' => 'Teknik Informatika', 'tanggal_mulai' => '2020-01-01', 'tanggal_keluar' => null, 'apakah_penugasan_homebase' => '1', 'perguruan_tinggi' => 'Universitas Sriwijaya'],
            // D2: non-homebase -> bukan DTPS.
            ['id' => 'PG2', 'id_sdm' => 'D2', 'jenjang_pendidikan' => 'S2', 'unit_kerja' => 'Teknik Informatika', 'tanggal_mulai' => '2020-01-01', 'tanggal_keluar' => null, 'apakah_penugasan_homebase' => '0', 'perguruan_tinggi' => 'Universitas Sriwijaya'],
            // D3: homebase P2 -> DTPS P2, bukan P1.
            ['id' => 'PG3', 'id_sdm' => 'D3', 'jenjang_pendidikan' => 'S2', 'unit_kerja' => 'Manajemen', 'tanggal_mulai' => '2020-01-01', 'tanggal_keluar' => null, 'apakah_penugasan_homebase' => '1', 'perguruan_tinggi' => 'Universitas Sriwijaya'],
        ]);

        DB::table('sister_list_publikasi')->insert([
            // A: jurnal internasional bereputasi, D1 sebagai salah satu penulis -> TS.
            ['id' => 'A', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Jurnal internasional bereputasi', 'tanggal' => '2024-03-01'],
            ['id' => 'A', 'id_sdm' => 'OTHER-AUTHOR', 'jenis_publikasi' => 'Jurnal internasional bereputasi', 'tanggal' => '2024-03-01'],
            // B: jurnal nasional terakreditasi, D1 -> TS.
            ['id' => 'B', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Jurnal nasional terakreditasi', 'tanggal' => '2024-06-15'],
            // C: prosiding seminar nasional, D1 -> TS.
            ['id' => 'C', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Prosiding seminar nasional', 'tanggal' => '2024-09-01'],
            // D: jenis di luar cakupan tabel 3.b.4 (buku referensi) -> tak terhitung.
            ['id' => 'D', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Buku referensi', 'tanggal' => '2024-01-01'],
            // E: penulisnya D2 (bukan DTPS) -> tak terhitung.
            ['id' => 'E', 'id_sdm' => 'D2', 'jenis_publikasi' => 'Jurnal nasional', 'tanggal' => '2024-02-01'],
            // F: penulisnya D3 (DTPS P2, bukan P1) -> tak terhitung saat scope P1.
            ['id' => 'F', 'id_sdm' => 'D3', 'jenis_publikasi' => 'Jurnal nasional', 'tanggal' => '2024-02-01'],
            // G: TS-1, jurnal internasional, D1.
            ['id' => 'G', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Jurnal internasional', 'tanggal' => '2023-05-01'],
            // H: TS-2, koran/majalah populer, D1 -> media_nasional.
            ['id' => 'H', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Koran/majalah populer/majalah umum', 'tanggal' => '2022-07-01'],
        ]);

        $response = $this->getJson('/api/sapto/tables/publikasi-ilmiah-dtps/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '3.b.4')
            ->assertJsonPath('table_name', 'Publikasi Ilmiah DTPS');

        $rows = collect($response->json('rows'))->keyBy('jenis_publikasi');

        $jurnalBereputasi = $rows->get('Jurnal penelitian internasional bereputasi');
        $this->assertSame(['ts_2' => 0, 'ts_1' => 0, 'ts' => 1, 'jumlah' => 1], [
            'ts_2' => $jurnalBereputasi['ts_2'], 'ts_1' => $jurnalBereputasi['ts_1'], 'ts' => $jurnalBereputasi['ts'], 'jumlah' => $jurnalBereputasi['jumlah'],
        ]);

        $jurnalTerakreditasi = $rows->get('Jurnal penelitian nasional terakreditasi');
        $this->assertSame(1, $jurnalTerakreditasi['ts']);

        $seminarNasional = $rows->get('Seminar nasional');
        $this->assertSame(1, $seminarNasional['ts']);

        $jurnalInternasional = $rows->get('Jurnal penelitian internasional');
        $this->assertSame(1, $jurnalInternasional['ts_1']);

        $mediaNasional = $rows->get('Tulisan di media massa nasional');
        $this->assertSame(1, $mediaNasional['ts_2']);

        $seminarWilayah = $rows->get('Seminar wilayah/lokal/perguruan tinggi');
        $this->assertSame(['ts_2' => 0, 'ts_1' => 0, 'ts' => 0, 'jumlah' => 0], [
            'ts_2' => $seminarWilayah['ts_2'], 'ts_1' => $seminarWilayah['ts_1'], 'ts' => $seminarWilayah['ts'], 'jumlah' => $seminarWilayah['jumlah'],
        ]);

        $jumlah = $rows->get('Jumlah');
        $this->assertNull($jumlah['no']);
        $this->assertSame(['ts_2' => 1, 'ts_1' => 1, 'ts' => 3, 'jumlah' => 5], [
            'ts_2' => $jumlah['ts_2'], 'ts_1' => $jumlah['ts_1'], 'ts' => $jumlah['ts'], 'jumlah' => $jumlah['jumlah'],
        ]);

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(5, $metrics['Total judul publikasi DTPS (TS-2..TS)']);
        $this->assertSame(3, $metrics['Judul publikasi DTPS (TS)']);
        $this->assertSame(1, $metrics['Judul publikasi DTPS (TS-1)']);
    }

    public function test_excludes_titles_only_authored_by_biro_staff(): void
    {
        DB::table('sister_list_penugasan')->insert([
            // D1: homebase P1 -> DTPS.
            ['id' => 'PG1', 'id_sdm' => 'D1', 'jenjang_pendidikan' => 'S2', 'unit_kerja' => 'Teknik Informatika', 'tanggal_mulai' => '2020-01-01', 'tanggal_keluar' => null, 'apakah_penugasan_homebase' => '1', 'perguruan_tinggi' => 'Universitas Sriwijaya'],
            // STAFF1: homebase tapi unit_kerja tidak cocok prodi manapun -> bukan DTPS.
            ['id' => 'PG2', 'id_sdm' => 'STAFF1', 'jenjang_pendidikan' => 'S2', 'unit_kerja' => '(tidak diisi) Biro Umum dan Keuangan', 'tanggal_mulai' => '2020-01-01', 'tanggal_keluar' => null, 'apakah_penugasan_homebase' => '1', 'perguruan_tinggi' => 'Universitas Sriwijaya'],
        ]);

        DB::table('sister_list_publikasi')->insert([
            // Ditulis D1 (DTPS) -> ikut terhitung.
            ['id' => 'A', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Jurnal nasional terakreditasi', 'tanggal' => '2024-06-15'],
            // Ditulis staf biro saja -> tidak boleh terhitung sebagai DTPS.
            ['id' => 'B', 'id_sdm' => 'STAFF1', 'jenis_publikasi' => 'Koran/majalah populer/majalah umum', 'tanggal' => '2024-02-01'],
        ]);

        $response = $this->getJson('/api/sapto/tables/publikasi-ilmiah-dtps/summary?ts=2024')
            ->assertOk();

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(1, $metrics['Total judul publikasi DTPS (TS-2..TS)']);
        $this->assertSame(1, $metrics['Judul publikasi DTPS (TS)']);
    }

    public function test_ts_defaults_to_latest_year_with_homebase_data(): void
    {
        DB::table('list_penugasan_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2023/2024'],
            ['id_dosen' => 'D2', 'nama_dosen' => 'Dr. Budi', 'nidn' => '222', 'jk' => 'L', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2024/2025'],
        ]);

        $this->getJson('/api/sapto/tables/publikasi-ilmiah-dtps/summary')
            ->assertOk()
            ->assertJsonPath('ts', 2024)
            ->assertJsonPath('available_years', [2024, 2023]);
    }
}
