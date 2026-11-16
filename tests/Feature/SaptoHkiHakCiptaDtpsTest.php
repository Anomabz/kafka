<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 3.b.7) Bagian II: HKI (Hak Cipta, Desain Produk Industri, dll.).
 * Sumber sister_detail_kekayaan_intelektual, DTPS lewat roster
 * sister_list_penugasan (sama seperti Tabel 3.b.4, Bagian I/III/IV) --
 * lihat SaptoHkiHakCiptaDtpsRepository. Baris difilter id_jenis_publikasi
 * 43/44 (Hak cipta) DAN id_kategori_capaian_luaran BUKAN '1' (Produk
 * Teknologi Tepat Guna, sudah dihitung Bagian III).
 */
class SaptoHkiHakCiptaDtpsTest extends TestCase
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

    public function test_lists_hak_cipta_and_excludes_teknologi_tepat_guna_category(): void
    {
        $blank = ['nomor_paten' => null, 'pemberi_paten' => null, 'id_kategori_capaian_luaran' => null];

        DB::table('sister_detail_kekayaan_intelektual')->insert([
            // A: Hak cipta nasional (43), kategori HKI (4), D1 -> masuk, TS.
            ['id' => 'A', 'id_list' => 'A', 'id_jenis_publikasi' => '43', 'jenis_publikasi' => 'Hak cipta nasional', 'id_kategori_capaian_luaran' => '4', 'nomor_paten' => '000157949', 'pemberi_paten' => 'Kemenkumham RI', 'judul' => 'Revitalisasi Seni Pertunjukan', 'tanggal' => '2024-03-01', 'penulis' => $this->penulis('D1')],
            // B: Hak cipta internasional (44), tanpa kategori, D1 -> masuk, TS-1.
            ['id' => 'B', 'id_list' => 'B', 'id_jenis_publikasi' => '44', 'jenis_publikasi' => 'Hak cipta internasional', 'judul' => 'Desain Produk Kerajinan', 'tanggal' => '2023-08-01', 'penulis' => $this->penulis('D1')] + $blank,
            // C: Hak cipta nasional (43) TAPI kategori '1' (Produk TTG) -> harus DIKECUALIKAN (sudah masuk Bagian III).
            ['id' => 'C', 'id_list' => 'C', 'id_jenis_publikasi' => '43', 'jenis_publikasi' => 'Hak cipta nasional', 'id_kategori_capaian_luaran' => '1', 'judul' => 'Produk TTG dengan Hak Cipta', 'tanggal' => '2024-01-01', 'penulis' => $this->penulis('D1')] + $blank,
            // D: jenis di luar cakupan (paten, kode 41) -> tak terhitung.
            ['id' => 'D', 'id_list' => 'D', 'id_jenis_publikasi' => '41', 'jenis_publikasi' => 'Paten nasional', 'judul' => 'Paten Lain', 'tanggal' => '2024-01-01', 'penulis' => $this->penulis('D1')] + $blank,
            // E: penulisnya D2 (bukan DTPS) -> tak terhitung.
            ['id' => 'E', 'id_list' => 'E', 'id_jenis_publikasi' => '43', 'jenis_publikasi' => 'Hak cipta nasional', 'judul' => 'Hak Cipta Lain', 'tanggal' => '2024-01-01', 'penulis' => $this->penulis('D2')] + $blank,
            // F: di luar rentang TS-2..TS (TS-3) -> tak terhitung.
            ['id' => 'F', 'id_list' => 'F', 'id_jenis_publikasi' => '43', 'jenis_publikasi' => 'Hak cipta nasional', 'judul' => 'Hak Cipta Lama', 'tanggal' => '2021-01-01', 'penulis' => $this->penulis('D1')] + $blank,
        ]);

        // Penulis tiap HKI dibaca repository lewat detail.id_list -> sister_list_kekayaan_intelektual.id_sdm.
        foreach (DB::table('sister_detail_kekayaan_intelektual')->get(['id_list', 'penulis']) as $d) {
            foreach (json_decode($d->penulis, true) as $a) {
                DB::table('sister_list_kekayaan_intelektual')->insert(['id' => $d->id_list, 'id_sdm' => $a['id_sdm']]);
            }
        }

        $response = $this->getJson('/api/sapto/tables/hki-hak-cipta-dtps/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '3.b.7')
            ->assertJsonPath('notice', null);

        $columnKeys = collect($response->json('columns'))->pluck('key')->all();
        $this->assertSame(['no', 'luaran', 'tahun', 'keterangan'], $columnKeys);

        $rows = collect($response->json('rows'));
        $this->assertSame(2, $rows->count());

        // Diurutkan tahun desc -> A (2024) duluan, lalu B (2023).
        $this->assertSame(1, $rows[0]['no']);
        $this->assertSame('Revitalisasi Seni Pertunjukan', $rows[0]['luaran']);
        $this->assertSame(2024, $rows[0]['tahun']);
        $this->assertSame('Hak cipta nasional, No. 000157949, Kemenkumham RI', $rows[0]['keterangan']);

        $this->assertSame('Desain Produk Kerajinan', $rows[1]['luaran']);
        $this->assertSame(2023, $rows[1]['tahun']);
        $this->assertSame('Hak cipta internasional', $rows[1]['keterangan']);

        // C (kategori Produk TTG) tidak boleh nongol sama sekali di sini.
        $this->assertFalse($rows->contains('luaran', 'Produk TTG dengan Hak Cipta'));

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(2, $metrics['Total judul (TS-2..TS)']);
        $this->assertSame(1, $metrics['Judul (TS)']);
    }
}
