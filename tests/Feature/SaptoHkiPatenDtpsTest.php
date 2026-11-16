<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 3.b.7) Bagian I: HKI (Paten, Paten Sederhana). Sumber
 * sister_detail_kekayaan_intelektual (mandiri, dedup by id, penulis dari
 * JSON `penulis`) disaring ke DTPS homebase list_penugasan_dosen (penulis via sister_list_kekayaan_intelektual) (sama
 * seperti Tabel 3.b.4, Bagian III & IV) -- lihat SaptoHkiPatenDtpsRepository.
 * Baris difilter id_jenis_publikasi 41/42 (Paten nasional/internasional).
 */
class SaptoHkiPatenDtpsTest extends TestCase
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
        ]);

        \App\Models\Penugasan::create([
            'peran' => 'korprodi',
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'lingkup' => '55201',
        ]);

        // DTPS = homebase di list_penugasan_dosen (id_dosen == id_sdm SISTER,
        // terverifikasi di produksi 2026-09-30: 709/709 penulis HKI cocok).
        foreach (['2022/2023', '2023/2024', '2024/2025'] as $ta) {
            DB::table('list_penugasan_dosen')->insert([
                ['id_dosen' => 'D1', 'nama_dosen' => 'Dosen D1', 'nama_tahun_ajaran' => $ta, 'id_prodi' => 'P1', 'a_sp_homebase' => '1'],
                ['id_dosen' => 'D2', 'nama_dosen' => 'Dosen D2', 'nama_tahun_ajaran' => $ta, 'id_prodi' => 'P1', 'a_sp_homebase' => '0'],
            ]);
        }

        DB::table('sister_list_penugasan')->insert([
            // D1: homebase P1, S1 -> DTPS.
            ['id' => 'PG1', 'id_sdm' => 'D1', 'jenjang_pendidikan' => 'S1', 'unit_kerja' => 'Teknik Informatika', 'tanggal_mulai' => '2020-01-01', 'tanggal_keluar' => null, 'apakah_penugasan_homebase' => '1', 'perguruan_tinggi' => 'Universitas Sriwijaya'],
            // D2: bukan homebase -> bukan DTPS.
            ['id' => 'PG2', 'id_sdm' => 'D2', 'jenjang_pendidikan' => 'S1', 'unit_kerja' => 'Teknik Informatika', 'tanggal_mulai' => '2020-01-01', 'tanggal_keluar' => null, 'apakah_penugasan_homebase' => '0', 'perguruan_tinggi' => 'Universitas Sriwijaya'],
        ]);
    }

    private function penulis(string $idSdm): string
    {
        return json_encode([['jenis' => 'Dosen', 'nama' => 'Dosen '.$idSdm, 'id_sdm' => $idSdm]]);
    }

    public function test_lists_hki_paten_with_dtps_filter(): void
    {
        $blank = ['nomor_paten' => null, 'pemberi_paten' => null];

        DB::table('sister_detail_kekayaan_intelektual')->insert([
            // A: Paten nasional (41), D1, nomor + pemberi lengkap -> masuk, TS.
            ['id' => 'A', 'id_list' => 'A', 'id_jenis_publikasi' => '41', 'jenis_publikasi' => 'Paten nasional', 'nomor_paten' => 'IDP000012345', 'pemberi_paten' => 'DJKI Kemenkumham', 'judul' => 'Alat Penyaring Air Portabel', 'tanggal' => '2024-03-01', 'penulis' => $this->penulis('D1')],
            // B: Paten internasional (42), D1, tanpa nomor paten -> masuk, TS-1.
            ['id' => 'B', 'id_list' => 'B', 'id_jenis_publikasi' => '42', 'jenis_publikasi' => 'Paten internasional', 'judul' => 'Metode Pengolahan Limbah Organik', 'tanggal' => '2023-08-01', 'penulis' => $this->penulis('D1')] + $blank,
            // C: jenis di luar cakupan (hak cipta, kode 43) -> tak terhitung.
            ['id' => 'C', 'id_list' => 'C', 'id_jenis_publikasi' => '43', 'jenis_publikasi' => 'Hak cipta nasional', 'judul' => 'Karya Cipta Lain', 'tanggal' => '2024-01-01', 'penulis' => $this->penulis('D1')] + $blank,
            // D: penulisnya D2 (bukan DTPS) -> tak terhitung.
            ['id' => 'D', 'id_list' => 'D', 'id_jenis_publikasi' => '41', 'jenis_publikasi' => 'Paten nasional', 'judul' => 'Paten Lain', 'tanggal' => '2024-01-01', 'penulis' => $this->penulis('D2')] + $blank,
            // E: di luar rentang TS-2..TS (TS-3) -> tak terhitung.
            ['id' => 'E', 'id_list' => 'E', 'id_jenis_publikasi' => '41', 'jenis_publikasi' => 'Paten nasional', 'judul' => 'Paten Lama', 'tanggal' => '2021-01-01', 'penulis' => $this->penulis('D1')] + $blank,
        ]);

        // Penulis tiap HKI dibaca repository lewat detail.id_list -> sister_list_kekayaan_intelektual.id_sdm.
        foreach (DB::table('sister_detail_kekayaan_intelektual')->get(['id_list', 'penulis']) as $d) {
            foreach (json_decode($d->penulis, true) as $a) {
                DB::table('sister_list_kekayaan_intelektual')->insert(['id' => $d->id_list, 'id_sdm' => $a['id_sdm']]);
            }
        }

        $response = $this->getJson('/api/sapto/tables/hki-paten-dtps/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '3.b.7')
            ->assertJsonPath('notice', null);

        $columnKeys = collect($response->json('columns'))->pluck('key')->all();
        $this->assertSame(['no', 'luaran', 'tahun', 'keterangan'], $columnKeys);

        $rows = collect($response->json('rows'));
        $this->assertSame(2, $rows->count());

        // Diurutkan tahun desc -> A (2024) duluan, lalu B (2023).
        $this->assertSame(1, $rows[0]['no']);
        $this->assertSame('Alat Penyaring Air Portabel', $rows[0]['luaran']);
        $this->assertSame(2024, $rows[0]['tahun']);
        $this->assertSame('Paten nasional, No. IDP000012345, DJKI Kemenkumham', $rows[0]['keterangan']);

        $this->assertSame('Metode Pengolahan Limbah Organik', $rows[1]['luaran']);
        $this->assertSame(2023, $rows[1]['tahun']);
        // Tanpa nomor_paten -> bagian "No. ..." tidak muncul, bukan "No. ,".
        $this->assertSame('Paten internasional', $rows[1]['keterangan']);

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(2, $metrics['Total judul (TS-2..TS)']);
        $this->assertSame(1, $metrics['Judul (TS)']);
    }
}
