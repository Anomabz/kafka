<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 3.b.4) Pagelaran/Pameran/Presentasi/Publikasi Ilmiah DTPS (varian
 * VOKASI). DTPS ditentukan dari sister_list_penugasan (jenjang D3/D4/Sarjana
 * Terapan/Magister Terapan/Doktor Terapan). Berbagi perhitungan dengan varian
 * akademik lewat SaptoPublikasiDtpsRepository; yang diuji di sini terutama
 * perbedaannya: baris 8-10 pagelaran (selalu 0, sumber belum ada) dan
 * koran/majalah yang TIDAK ikut dihitung.
 */
class SaptoPagelaranDtpsTest extends TestCase
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
            ['id_prodi' => 'P1', 'kode_program_studi' => '55201', 'nama_program_studi' => 'Teknologi Informasi', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 1, 'nama_jenjang_pendidikan' => 'D3'],
        ]);

        \App\Models\Korprodi::create([
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'kode_program_studi' => '55201',
        ]);

        DB::table('sister_list_penugasan')->insert([
            // D1: homebase P1 (Teknologi Informasi), jenjang D3 -> DTPS.
            ['id' => 'PG1', 'id_sdm' => 'D1', 'jenjang_pendidikan' => 'D3', 'unit_kerja' => 'Teknologi Informasi', 'tanggal_mulai' => '2020-01-01', 'tanggal_keluar' => null, 'apakah_penugasan_homebase' => '1', 'perguruan_tinggi' => 'Universitas Sriwijaya'],
            // D2: bukan homebase -> bukan DTPS.
            ['id' => 'PG2', 'id_sdm' => 'D2', 'jenjang_pendidikan' => 'D3', 'unit_kerja' => 'Teknologi Informasi', 'tanggal_mulai' => '2020-01-01', 'tanggal_keluar' => null, 'apakah_penugasan_homebase' => '0', 'perguruan_tinggi' => 'Universitas Sriwijaya'],
        ]);
    }

    public function test_summary_classifies_jurnal_and_seminar_like_the_academic_variant(): void
    {
        DB::table('sister_list_publikasi')->insert([
            ['id' => 'A', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Jurnal internasional bereputasi', 'tanggal' => '2024-03-01'],
            ['id' => 'A', 'id_sdm' => 'LAIN', 'jenis_publikasi' => 'Jurnal internasional bereputasi', 'tanggal' => '2024-03-01'],
            ['id' => 'B', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Jurnal nasional terakreditasi', 'tanggal' => '2024-06-15'],
            ['id' => 'C', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Jurnal nasional', 'tanggal' => '2024-07-01'],
            ['id' => 'D', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Prosiding seminar nasional', 'tanggal' => '2024-09-01'],
            ['id' => 'E', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Poster seminar internasional', 'tanggal' => '2023-05-01'],
            ['id' => 'F', 'id_sdm' => 'D2', 'jenis_publikasi' => 'Jurnal nasional', 'tanggal' => '2024-02-01'],
        ]);

        $response = $this->getJson('/api/sapto/tables/pagelaran-dtps/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '3.b.4')
            ->assertJsonPath('table_name', 'Pagelaran/Pameran/Presentasi/Publikasi Ilmiah DTPS');

        $rows = collect($response->json('rows'))->keyBy('jenis_publikasi');

        $this->assertSame(1, $rows['Jurnal penelitian internasional bereputasi']['ts']);
        $this->assertSame(1, $rows['Jurnal penelitian nasional terakreditasi']['ts']);
        $this->assertSame(1, $rows['Jurnal penelitian tidak terakreditasi']['ts']);
        $this->assertSame(1, $rows['Seminar nasional']['ts']);
        $this->assertSame(1, $rows['Seminar internasional']['ts_1']);
    }

    public function test_pagelaran_rows_exist_but_are_always_zero(): void
    {
        DB::table('sister_list_publikasi')->insert([
            ['id' => 'A', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Jurnal nasional terakreditasi', 'tanggal' => '2024-06-15'],
        ]);

        $rows = collect($this->getJson('/api/sapto/tables/pagelaran-dtps/summary?ts=2024')
            ->assertOk()->json('rows'))->keyBy('jenis_publikasi');

        foreach ([
            'Pagelaran/pameran/presentasi dalam forum di tingkat wilayah',
            'Pagelaran/pameran/presentasi dalam forum di tingkat nasional',
            'Pagelaran/pameran/presentasi dalam forum di tingkat internasional',
            'Seminar wilayah/lokal/perguruan tinggi',
        ] as $label) {
            $this->assertArrayHasKey($label, $rows, "Baris '{$label}' harus tetap ada di tabel.");
            $this->assertSame(0, $rows[$label]['jumlah'], "Baris '{$label}' belum punya sumber data, harus 0.");
        }

        // Baris media massa milik varian akademik tidak boleh muncul di sini.
        $this->assertArrayNotHasKey('Tulisan di media massa nasional', $rows);
    }

    /**
     * Perbedaan pokok dengan varian akademik: koran/majalah populer dihitung
     * sebagai "media massa nasional" di sana, tapi di tabel vokasi tidak ada
     * barisnya sehingga tidak boleh ikut terhitung di mana pun.
     */
    public function test_koran_majalah_is_not_counted_in_the_vocational_variant(): void
    {
        DB::table('sister_list_publikasi')->insert([
            ['id' => 'K', 'id_sdm' => 'D1', 'jenis_publikasi' => 'Koran/majalah populer/majalah umum', 'tanggal' => '2024-04-01'],
        ]);

        $rows = collect($this->getJson('/api/sapto/tables/pagelaran-dtps/summary?ts=2024')
            ->assertOk()->json('rows'))->keyBy('jenis_publikasi');

        $this->assertSame(0, $rows['Jumlah']['jumlah']);

        // Judul yang sama tetap terhitung di varian akademik.
        $akademik = collect($this->getJson('/api/sapto/tables/publikasi-ilmiah-dtps/summary?ts=2024')
            ->assertOk()->json('rows'))->keyBy('jenis_publikasi');

        $this->assertSame(1, $akademik['Tulisan di media massa nasional']['ts']);
    }
}
