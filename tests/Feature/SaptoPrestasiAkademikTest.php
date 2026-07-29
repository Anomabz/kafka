<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 8.b.1) Prestasi Akademik Mahasiswa. Daftar baris prestasi TS-2/TS-1/
 * TS, prodi tiap baris dicari lewat id_mahasiswa -> list_mahasiswa (baris
 * id_periode terbaru menang -- lihat basis SaptoPrestasiRepository). Hanya
 * jenis_prestasi "Sains"; Olahraga/Seni/Lain-lain masuk 8.b.2 (lihat
 * SaptoPrestasiNonAkademikTest).
 *
 * Sesi admin dipakai (bukan korprodi): role 'prodi' mengunci program_studi_id
 * ke satu prodi tetap, jadi tidak bisa dipakai untuk menguji beberapa prodi/
 * scope universitas dalam satu test class (sama seperti alasan di
 * SaptoWaktuTungguLulusanTest).
 */
class SaptoPrestasiAkademikTest extends TestCase
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
            ['id_prodi' => 'P2', 'kode_program_studi' => '61101', 'nama_program_studi' => 'Manajemen', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 1, 'nama_jenjang_pendidikan' => 'S1'],
        ]);
    }

    private function prestasi(string $idMahasiswa, string $tingkat, ?string $peringkat, string $tahun, string $nama = 'Lomba Uji', string $jenis = 'Sains'): array
    {
        return [
            'id_prestasi' => (string) \Illuminate\Support\Str::uuid(),
            'id_mahasiswa' => $idMahasiswa,
            'nama_prestasi' => $nama,
            'nama_jenis_prestasi' => $jenis,
            'nama_tingkat_prestasi' => $tingkat,
            'peringkat' => $peringkat,
            'tahun_prestasi' => $tahun,
        ];
    }

    public function test_rows_scoped_to_prodi_via_latest_mahasiswa_record(): void
    {
        // M1: dua baris list_mahasiswa, periode terbaru (20241) di P1 ->
        // prestasinya harus ikut P1, bukan periode lama (20191) di P2.
        DB::table('list_mahasiswa')->insert([
            ['id_mahasiswa' => 'M1', 'id_prodi' => 'P2', 'id_periode' => '20191'],
            ['id_mahasiswa' => 'M1', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M2', 'id_prodi' => 'P2', 'id_periode' => '20241'],
        ]);

        DB::table('list_prestasi_mahasiswa')->insert([
            $this->prestasi('M1', 'Nasional', '1', '2024', 'Lomba KTI Nasional'),
            $this->prestasi('M2', 'Nasional', '2', '2024', 'Lomba KTI Nasional P2'),
        ]);

        $responseP1 = $this->getJson('/api/sapto/tables/prestasi-akademik/summary?ts=2024&program_studi_id=P1')
            ->assertOk()
            ->assertJsonPath('table_code', '8.b.1')
            ->assertJsonPath('table_name', 'Prestasi Akademik Mahasiswa');

        $rowsP1 = $responseP1->json('rows');
        $this->assertCount(1, $rowsP1);
        $this->assertSame('Lomba KTI Nasional', $rowsP1[0]['nama_kegiatan']);

        $rowsP2 = $this->getJson('/api/sapto/tables/prestasi-akademik/summary?ts=2024&program_studi_id=P2')
            ->assertOk()->json('rows');
        $this->assertCount(1, $rowsP2);
        $this->assertSame('Lomba KTI Nasional P2', $rowsP2[0]['nama_kegiatan']);
    }

    public function test_tingkat_classification_and_prestasi_label(): void
    {
        DB::table('list_mahasiswa')->insert([
            ['id_mahasiswa' => 'M1', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M2', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M3', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M4', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M5', 'id_prodi' => 'P1', 'id_periode' => '20241'],
        ]);

        DB::table('list_prestasi_mahasiswa')->insert([
            $this->prestasi('M1', 'Internasional', '1', '2024', 'A'),
            $this->prestasi('M2', 'Nasional', '2', '2024', 'B'),
            $this->prestasi('M3', 'Propinsi', '3', '2024', 'C'),   // -> wilayah
            $this->prestasi('M4', 'Lainnya', '1', '2024', 'D'),    // -> wilayah (keputusan: catch-all)
            $this->prestasi('M5', 'Nasional', null, '2024', 'E'),  // peringkat kosong -> "-"
        ]);

        $rows = collect($this->getJson('/api/sapto/tables/prestasi-akademik/summary?ts=2024&program_studi_id=P1')
            ->assertOk()->json('rows'))->keyBy('nama_kegiatan');

        $this->assertSame('✓', $rows['A']['tingkat_internasional']);
        $this->assertSame('', $rows['A']['tingkat_nasional']);
        $this->assertSame('Juara 1', $rows['A']['prestasi_dicapai']);

        $this->assertSame('✓', $rows['B']['tingkat_nasional']);
        $this->assertSame('Juara 2', $rows['B']['prestasi_dicapai']);

        $this->assertSame('✓', $rows['C']['tingkat_lokal']);
        $this->assertSame('', $rows['C']['tingkat_nasional']);

        $this->assertSame('✓', $rows['D']['tingkat_lokal'], '"Lainnya" harus dianggap Lokal/Wilayah.');

        $this->assertSame('-', $rows['E']['prestasi_dicapai'], 'Peringkat kosong harus tampil "-".');
    }

    public function test_year_outside_ts_2_to_ts_window_is_excluded(): void
    {
        DB::table('list_mahasiswa')->insert([
            ['id_mahasiswa' => 'M1', 'id_prodi' => 'P1', 'id_periode' => '20241'],
        ]);

        DB::table('list_prestasi_mahasiswa')->insert([
            $this->prestasi('M1', 'Nasional', '1', '2021', 'Terlalu lama'), // TS-3, di luar jendela
            $this->prestasi('M1', 'Nasional', '1', '2022', 'TS-2'),
            $this->prestasi('M1', 'Nasional', '1', '2024', 'TS'),
        ]);

        $rows = collect($this->getJson('/api/sapto/tables/prestasi-akademik/summary?ts=2024&program_studi_id=P1')
            ->assertOk()->json('rows'))->pluck('nama_kegiatan');

        $this->assertTrue($rows->contains('TS-2'));
        $this->assertTrue($rows->contains('TS'));
        $this->assertFalse($rows->contains('Terlalu lama'));
    }

    public function test_only_sains_included_others_excluded(): void
    {
        DB::table('list_mahasiswa')->insert([
            ['id_mahasiswa' => 'M1', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M2', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M3', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M4', 'id_prodi' => 'P1', 'id_periode' => '20241'],
        ]);

        DB::table('list_prestasi_mahasiswa')->insert([
            $this->prestasi('M1', 'Nasional', '1', '2024', 'Lomba KTI', 'Sains'),
            $this->prestasi('M2', 'Nasional', '1', '2024', 'Futsal', 'Olahraga'),
            $this->prestasi('M3', 'Nasional', '1', '2024', 'Band Competition', 'Seni'),
            $this->prestasi('M4', 'Nasional', '1', '2024', 'Debat', 'Lain-lain'),
        ]);

        $rows = collect($this->getJson('/api/sapto/tables/prestasi-akademik/summary?ts=2024&program_studi_id=P1')
            ->assertOk()->json('rows'))->pluck('nama_kegiatan');

        $this->assertSame(['Lomba KTI'], $rows->all(), 'Cuma jenis "Sains" yang boleh masuk 8.b.1.');
    }

    public function test_university_wide_scope_aggregates_all_prodi(): void
    {
        DB::table('list_mahasiswa')->insert([
            ['id_mahasiswa' => 'M1', 'id_prodi' => 'P1', 'id_periode' => '20241'],
            ['id_mahasiswa' => 'M2', 'id_prodi' => 'P2', 'id_periode' => '20241'],
        ]);

        DB::table('list_prestasi_mahasiswa')->insert([
            $this->prestasi('M1', 'Nasional', '1', '2024', 'Prestasi P1'),
            $this->prestasi('M2', 'Internasional', '1', '2024', 'Prestasi P2'),
        ]);

        $response = $this->getJson('/api/sapto/tables/prestasi-akademik/summary?ts=2024&program_studi_id=all')
            ->assertOk();

        $this->assertCount(2, $response->json('rows'));

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(2, $metrics['Total prestasi (TS-2..TS)']);
        $this->assertSame(1, $metrics['Nasional']);
        $this->assertSame(1, $metrics['Internasional']);
    }
}
