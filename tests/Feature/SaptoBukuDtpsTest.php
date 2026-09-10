<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 3.b.7) Bagian IV: Buku ber-ISBN, Book Chapter. Sumber
 * sister_detail_publikasi (mandiri, dedup by id, penulis dari JSON `penulis`)
 * disaring ke DTPS lewat roster sister_list_penugasan (sama seperti Tabel
 * 3.b.4) -- lihat SaptoBukuDtpsRepository. Beda bentuk dari tabel 3.b lain:
 * listing per judul, bukan matriks hitung.
 */
class SaptoBukuDtpsTest extends TestCase
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

        // Label "Keterangan" diambil dari tabel referensi ini, bukan
        // di-hardcode -- lihat SaptoBukuDtpsRepository::jenisLabels(). Nama
        // asli di sumber utk kode 13 adalah "Buku lainnya", bukan "Buku".
        DB::table('jenis_publikasi')->insert([
            ['id' => 13, 'nama' => 'Buku lainnya'],
            ['id' => 14, 'nama' => 'Book chapter nasional'],
        ]);
    }

    private function penulis(string $idSdm): string
    {
        return json_encode([['jenis' => 'Dosen', 'nama' => 'Dosen '.$idSdm, 'id_sdm' => $idSdm]]);
    }

    public function test_lists_buku_and_book_chapter_with_dtps_filter(): void
    {
        $blank = ['judul_artikel' => null, 'penerbit' => null, 'isbn' => null];

        DB::table('sister_detail_publikasi')->insert([
            // A: Buku (13), D1 -> masuk, TS.
            ['id' => 'A', 'id_list' => 'A', 'id_jenis_publikasi' => '13', 'judul' => 'Buku Struktur Data', 'penerbit' => 'Penerbit Unsri', 'isbn' => '978-1-234', 'tanggal' => '2024-03-01', 'penulis' => $this->penulis('D1')] + $blank,
            // B: Book chapter nasional (14), D1 -> judul_artikel dipakai, "dalam buku: ...".
            ['id' => 'B', 'id_list' => 'B', 'id_jenis_publikasi' => '14', 'judul' => 'Kumpulan Esai Teknologi', 'judul_artikel' => 'Bab Kecerdasan Buatan', 'penerbit' => 'Penerbit Nasional', 'tanggal' => '2023-08-01', 'penulis' => $this->penulis('D1')] + $blank,
            // C: jenis di luar cakupan (jurnal, kode 21) -> tak terhitung.
            ['id' => 'C', 'id_list' => 'C', 'id_jenis_publikasi' => '21', 'judul' => 'Artikel Jurnal', 'tanggal' => '2024-01-01', 'penulis' => $this->penulis('D1')] + $blank,
            // D: penulisnya D2 (bukan DTPS) -> tak terhitung.
            ['id' => 'D', 'id_list' => 'D', 'id_jenis_publikasi' => '13', 'judul' => 'Buku Lain', 'tanggal' => '2024-01-01', 'penulis' => $this->penulis('D2')] + $blank,
            // E: di luar rentang TS-2..TS (TS-3) -> tak terhitung.
            ['id' => 'E', 'id_list' => 'E', 'id_jenis_publikasi' => '13', 'judul' => 'Buku Lama', 'tanggal' => '2021-01-01', 'penulis' => $this->penulis('D1')] + $blank,
        ]);

        $response = $this->getJson('/api/sapto/tables/buku-dtps/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '3.b.7')
            ->assertJsonPath('notice', null);

        $columnKeys = collect($response->json('columns'))->pluck('key')->all();
        $this->assertSame(['no', 'luaran', 'tahun', 'keterangan'], $columnKeys);

        $rows = collect($response->json('rows'));
        $this->assertSame(2, $rows->count());

        // Diurutkan tahun desc -> A (2024) duluan, lalu B (2023).
        $this->assertSame(1, $rows[0]['no']);
        $this->assertSame('Buku Struktur Data', $rows[0]['luaran']);
        $this->assertSame(2024, $rows[0]['tahun']);
        $this->assertSame('Buku lainnya, Penerbit Unsri, ISBN 978-1-234', $rows[0]['keterangan']);

        $this->assertSame('Bab Kecerdasan Buatan (dalam buku: Kumpulan Esai Teknologi)', $rows[1]['luaran']);
        $this->assertSame(2023, $rows[1]['tahun']);
        $this->assertSame('Book chapter nasional, Penerbit Nasional', $rows[1]['keterangan']);

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(2, $metrics['Total judul (TS-2..TS)']);
        $this->assertSame(1, $metrics['Judul (TS)']);
    }
}
