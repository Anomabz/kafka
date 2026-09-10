<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 3.b.7) Bagian III: Teknologi Tepat Guna, Produk, Karya Seni,
 * Rekayasa Sosial. Sumber sister_detail_kekayaan_intelektual (mandiri,
 * dedup by id, penulis dari JSON `penulis`) disaring ke DTPS lewat roster
 * sister_list_penugasan (sama seperti Tabel 3.b.4 & Bagian IV) -- lihat
 * SaptoTeknologiDtpsRepository. Baris difilter kategori_capaian_luaran='1'
 * ATAU jenis_publikasi karya seni (51-55) / penemuan teknologi (29).
 */
class SaptoTeknologiDtpsTest extends TestCase
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

        \App\Models\Korprodi::create([
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'kode_program_studi' => '55201',
        ]);

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

    public function test_lists_teknologi_and_karya_seni_with_dtps_filter(): void
    {
        DB::table('sister_detail_kekayaan_intelektual')->insert([
            // A: Produk Teknologi Tepat Guna (kategori '1'), D1 -> masuk, TS.
            ['id' => 'A', 'id_list' => 'A', 'id_kategori_capaian_luaran' => '1', 'kategori_capaian_luaran' => 'Produk Teknologi Tepat Guna', 'id_jenis_publikasi' => '9999', 'jenis_publikasi' => 'Lain-lain', 'judul' => 'Alat Pengering Padi Otomatis', 'tanggal' => '2024-03-01', 'penulis' => $this->penulis('D1')],
            // B: Karya desain (jenis '55'), kategori lain (bukan '1') -> tetap masuk lewat OR jenis_publikasi.
            ['id' => 'B', 'id_list' => 'B', 'id_kategori_capaian_luaran' => '2', 'kategori_capaian_luaran' => 'Jenis Luaran Lainnya', 'id_jenis_publikasi' => '55', 'jenis_publikasi' => 'Karya desain', 'judul' => 'Desain Batik Kontemporer', 'tanggal' => '2023-08-01', 'penulis' => $this->penulis('D1')],
            // C: kategori & jenis di luar cakupan -> tak terhitung.
            ['id' => 'C', 'id_list' => 'C', 'id_kategori_capaian_luaran' => '3', 'kategori_capaian_luaran' => 'Publikasi', 'id_jenis_publikasi' => '21', 'jenis_publikasi' => 'Jurnal nasional', 'judul' => 'Artikel Jurnal', 'tanggal' => '2024-01-01', 'penulis' => $this->penulis('D1')],
            // D: penulisnya D2 (bukan DTPS) -> tak terhitung.
            ['id' => 'D', 'id_list' => 'D', 'id_kategori_capaian_luaran' => '1', 'kategori_capaian_luaran' => 'Produk Teknologi Tepat Guna', 'id_jenis_publikasi' => null, 'jenis_publikasi' => null, 'judul' => 'Produk Lain', 'tanggal' => '2024-01-01', 'penulis' => $this->penulis('D2')],
            // E: di luar rentang TS-2..TS (TS-3) -> tak terhitung.
            ['id' => 'E', 'id_list' => 'E', 'id_kategori_capaian_luaran' => '1', 'kategori_capaian_luaran' => 'Produk Teknologi Tepat Guna', 'id_jenis_publikasi' => null, 'jenis_publikasi' => null, 'judul' => 'Produk Lama', 'tanggal' => '2021-01-01', 'penulis' => $this->penulis('D1')],
        ]);

        $response = $this->getJson('/api/sapto/tables/teknologi-dtps/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '3.b.7')
            ->assertJsonPath('notice', null);

        $columnKeys = collect($response->json('columns'))->pluck('key')->all();
        $this->assertSame(['no', 'luaran', 'tahun', 'keterangan'], $columnKeys);

        $rows = collect($response->json('rows'));
        $this->assertSame(2, $rows->count());

        // Diurutkan tahun desc -> A (2024) duluan, lalu B (2023).
        $this->assertSame(1, $rows[0]['no']);
        $this->assertSame('Alat Pengering Padi Otomatis', $rows[0]['luaran']);
        $this->assertSame(2024, $rows[0]['tahun']);
        $this->assertSame('Produk Teknologi Tepat Guna, Lain-lain', $rows[0]['keterangan']);

        $this->assertSame('Desain Batik Kontemporer', $rows[1]['luaran']);
        $this->assertSame(2023, $rows[1]['tahun']);
        $this->assertSame('Jenis Luaran Lainnya, Karya desain', $rows[1]['keterangan']);

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(2, $metrics['Total judul (TS-2..TS)']);
        $this->assertSame(1, $metrics['Judul (TS)']);
    }
}
