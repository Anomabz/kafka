<?php

namespace Tests\Feature;

use App\Models\Penugasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 8.f.4 dan 8.f.1 (luaran/publikasi yang dihasilkan MAHASISWA).
 * Sebelum 2026-09-30, 8.f.4 menampilkan karya DOSEN (salinan 3.b.7) dan 8.f.1
 * tidak punya endpoint. Penulis mahasiswa dikenali dari id_peserta_didik /
 * nomor_induk_peserta_didik di JSON `penulis` SISTER, prodinya dari
 * list_mahasiswa.
 */
class SaptoLuaranMahasiswaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->withSession(['sso_user' => [
            'sub' => 'test', 'name' => 'Test', 'email' => 'test@example.test', 'nomor_identitas' => '199999999999999001',
        ]]);

        DB::table('program_studi')->insert([
            ['id_prodi' => 'P1', 'kode_program_studi' => '55201', 'nama_program_studi' => 'Teknik Informatika', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 30, 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'P2', 'kode_program_studi' => '61201', 'nama_program_studi' => 'Manajemen', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 30, 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        Penugasan::create(['peran' => 'korprodi', 'nip' => '199999999999999001', 'nama' => 'Test', 'lingkup' => '55201']);

        DB::table('list_mahasiswa')->insert([
            ['id_mahasiswa' => 'PD1', 'nim' => '0901', 'nama_mahasiswa' => 'MHS INFORMATIKA', 'id_prodi' => 'P1'],
            ['id_mahasiswa' => 'PD2', 'nim' => '0902', 'nama_mahasiswa' => 'MHS MANAJEMEN', 'id_prodi' => 'P2'],
        ]);

        $dosen = ['nama' => 'DOSEN', 'jenis' => 'Dosen', 'id_sdm' => 'S1', 'id_peserta_didik' => null, 'nomor_induk_peserta_didik' => null];
        $mhs = fn (string $pd, string $nim, string $nama): array => ['nama' => $nama, 'jenis' => 'Dosen', 'id_sdm' => null, 'id_peserta_didik' => $pd, 'nomor_induk_peserta_didik' => $nim];

        DB::table('sister_detail_kekayaan_intelektual')->insert([
            // Paten dengan mahasiswa P1 -> masuk (tercatat dua kali di sumber: harus satu baris).
            ['id' => 'K1', 'id_jenis_publikasi' => '41', 'judul' => 'Alat Ukur', 'tanggal' => '2024-05-01', 'nomor_paten' => 'P-001', 'id_kategori_capaian_luaran' => null, 'penulis' => json_encode([$dosen, $mhs('PD1', '0901', 'MHS INFORMATIKA')])],
            ['id' => 'K1', 'id_jenis_publikasi' => '41', 'judul' => 'Alat Ukur', 'tanggal' => '2024-05-01', 'nomor_paten' => 'P-001', 'id_kategori_capaian_luaran' => null, 'penulis' => json_encode([$dosen, $mhs('PD1', '0901', 'MHS INFORMATIKA')])],
            // Paten dosen saja -> TIDAK masuk (dulu masuk).
            ['id' => 'K2', 'id_jenis_publikasi' => '41', 'judul' => 'Paten Dosen', 'tanggal' => '2024-06-01', 'nomor_paten' => null, 'id_kategori_capaian_luaran' => null, 'penulis' => json_encode([$dosen])],
            // Paten dengan mahasiswa prodi lain -> tidak masuk untuk P1.
            ['id' => 'K3', 'id_jenis_publikasi' => '41', 'judul' => 'Paten Manajemen', 'tanggal' => '2024-06-01', 'nomor_paten' => null, 'id_kategori_capaian_luaran' => null, 'penulis' => json_encode([$dosen, $mhs('PD2', '0902', 'MHS MANAJEMEN')])],
            // Paten berkategori "Teknologi Tepat Guna": hanya di Bagian-1, bukan Bagian-3.
            ['id' => 'K4', 'id_jenis_publikasi' => '42', 'judul' => 'Paten TTG', 'tanggal' => '2025-01-01', 'nomor_paten' => null, 'id_kategori_capaian_luaran' => '1', 'penulis' => json_encode([$mhs('PD1', '0901', 'MHS INFORMATIKA')])],
        ]);

        DB::table('sister_detail_publikasi')->insert([
            ['id' => 'J1', 'id_jenis_publikasi' => '22', 'jenis_publikasi' => 'Jurnal nasional terakreditasi', 'judul' => 'Artikel A', 'tanggal' => '2025-02-01', 'penulis' => json_encode([$dosen, $mhs('PD1', '0901', 'MHS INFORMATIKA')])],
            ['id' => 'J2', 'id_jenis_publikasi' => '32', 'jenis_publikasi' => 'Prosiding seminar internasional', 'judul' => 'Artikel B', 'tanggal' => '2023-02-01', 'penulis' => json_encode([$dosen, $mhs('', '0901', 'MHS INFORMATIKA')])],
            ['id' => 'J3', 'id_jenis_publikasi' => '22', 'jenis_publikasi' => 'Jurnal nasional terakreditasi', 'judul' => 'Artikel Dosen', 'tanggal' => '2025-02-01', 'penulis' => json_encode([$dosen])],
        ]);
    }

    public function test_paten_mahasiswa_lists_only_works_with_student_authors_of_the_program(): void
    {
        $this->getJson('/api/sapto/tables/hki-paten-mahasiswa/summary?ts=2025')->assertOk()
            ->assertJsonPath('table_code', '8.f.4')
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.luaran', 'Paten TTG')
            ->assertJsonPath('rows.1.luaran', 'Alat Ukur')
            ->assertJsonPath('rows.1.nama_mahasiswa', 'MHS INFORMATIKA')
            ->assertJsonPath('rows.1.keterangan', 'Paten nasional · No. P-001')
            ->assertJsonPath('metrics.2.value', 1)
            ->assertJsonMissingPath('rows.0._mhs');
    }

    public function test_teknologi_mahasiswa_does_not_double_count_patents(): void
    {
        $this->getJson('/api/sapto/tables/teknologi-mahasiswa/summary?ts=2025')->assertOk()
            ->assertJsonCount(0, 'rows');
    }

    public function test_publikasi_mahasiswa_counts_titles_with_student_authors_by_nim_or_id(): void
    {
        $rows = collect($this->getJson('/api/sapto/tables/publikasi-mahasiswa/summary?ts=2025')->assertOk()
            ->assertJsonPath('table_code', '8.f.1')
            ->assertJsonPath('source.template', false)
            ->json('rows'))->keyBy('jenis_publikasi');

        $this->assertSame(1, $rows['Jurnal penelitian nasional terakreditasi']['ts']);
        // Dikenali lewat NIM saja (id_peserta_didik kosong).
        $this->assertSame(1, $rows['Seminar internasional']['ts_2']);
        $this->assertSame(2, $rows['Jumlah']['jumlah']);
    }

    public function test_program_without_student_outputs_is_empty(): void
    {
        Penugasan::query()->delete();
        Penugasan::create(['peran' => 'korprodi', 'nip' => '199999999999999001', 'nama' => 'Test', 'lingkup' => '61201']);

        $this->getJson('/api/sapto/tables/hki-paten-mahasiswa/summary?ts=2025')->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.luaran', 'Paten Manajemen');
        $this->getJson('/api/sapto/tables/publikasi-mahasiswa/summary?ts=2025')->assertOk()
            ->assertJsonPath('source.template', true);
    }
}
