<?php

namespace Tests\Feature;

use App\Models\Penugasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 6.b. Sebelum 2026-09-30 repository membaca kolom yang tidak ada di
 * skema (list_bimbing_mahasiswa.id_prodi/nama_semester) dan tabel ini selalu
 * "Gagal dimuat". Tes memakai skema migrasi yang sama dengan produksi.
 */
class SaptoPenelitianRujukanTemaTest extends TestCase
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
            ['id_prodi' => 'S2IK', 'kode_program_studi' => '55101', 'nama_program_studi' => 'Ilmu Komputer', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 35, 'nama_jenjang_pendidikan' => 'S2'],
            ['id_prodi' => 'S1TI', 'kode_program_studi' => '55201', 'nama_program_studi' => 'Teknik Informatika', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 30, 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        Penugasan::create(['peran' => 'korprodi', 'nip' => '199999999999999001', 'nama' => 'Test', 'lingkup' => '55101']);

        // D1 homebase di S2IK (DTPS); D2 homebase di S1TI (bukan DTPS S2IK).
        DB::table('list_penugasan_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_dosen' => 'DOSEN SATU', 'nama_tahun_ajaran' => '2024/2025', 'id_prodi' => 'S2IK', 'a_sp_homebase' => '1'],
            ['id_dosen' => 'D1', 'nama_dosen' => 'DOSEN SATU', 'nama_tahun_ajaran' => '2025/2026', 'id_prodi' => 'S2IK', 'a_sp_homebase' => '1'],
            ['id_dosen' => 'D2', 'nama_dosen' => 'DOSEN DUA', 'nama_tahun_ajaran' => '2025/2026', 'id_prodi' => 'S1TI', 'a_sp_homebase' => '1'],
        ]);

        DB::table('list_aktivitas_mahasiswa')->insert([
            // Tesis yang sama tercatat di dua semester: harus jadi satu baris, tahun terakhir.
            ['id_aktivitas' => 'A1', 'id_prodi' => 'S2IK', 'nama_jenis_aktivitas' => 'Tesis', 'id_semester' => '20241', 'judul' => 'Deteksi Retina'],
            ['id_aktivitas' => 'A2', 'id_prodi' => 'S2IK', 'nama_jenis_aktivitas' => 'Tesis', 'id_semester' => '20251', 'judul' => 'Deteksi Retina'],
            // Dibimbing dosen non-DTPS: tidak masuk.
            ['id_aktivitas' => 'A3', 'id_prodi' => 'S2IK', 'nama_jenis_aktivitas' => 'Tesis', 'id_semester' => '20251', 'judul' => 'Jaringan IoT'],
            // Bukan tesis/disertasi: tidak masuk.
            ['id_aktivitas' => 'A4', 'id_prodi' => 'S2IK', 'nama_jenis_aktivitas' => 'Bimbingan akademis', 'id_semester' => '20251', 'judul' => 'Perwalian'],
            // Di luar jendela TS-2..TS: tidak masuk.
            ['id_aktivitas' => 'A5', 'id_prodi' => 'S2IK', 'nama_jenis_aktivitas' => 'Tesis', 'id_semester' => '20201', 'judul' => 'Lama'],
        ]);

        DB::table('dosen_pembimbing')->insert([
            ['id_aktivitas' => 'A1', 'id_dosen' => 'D1', 'nama_dosen' => 'DOSEN SATU', 'id_registrasi_mahasiswa' => 'M1', 'nama_mahasiswa' => 'MAHASISWA SATU', 'pembimbing_ke' => '1'],
            ['id_aktivitas' => 'A2', 'id_dosen' => 'D1', 'nama_dosen' => 'DOSEN SATU', 'id_registrasi_mahasiswa' => 'M1', 'nama_mahasiswa' => 'MAHASISWA SATU', 'pembimbing_ke' => '1'],
            ['id_aktivitas' => 'A2', 'id_dosen' => 'D1', 'nama_dosen' => 'DOSEN SATU', 'id_registrasi_mahasiswa' => 'M1', 'nama_mahasiswa' => 'MAHASISWA SATU', 'pembimbing_ke' => '1'],
            ['id_aktivitas' => 'A3', 'id_dosen' => 'D2', 'nama_dosen' => 'DOSEN DUA', 'id_registrasi_mahasiswa' => 'M2', 'nama_mahasiswa' => 'MAHASISWA DUA', 'pembimbing_ke' => '1'],
            ['id_aktivitas' => 'A4', 'id_dosen' => 'D1', 'nama_dosen' => 'DOSEN SATU', 'id_registrasi_mahasiswa' => 'M3', 'nama_mahasiswa' => 'MAHASISWA TIGA', 'pembimbing_ke' => '1'],
            ['id_aktivitas' => 'A5', 'id_dosen' => 'D1', 'nama_dosen' => 'DOSEN SATU', 'id_registrasi_mahasiswa' => 'M4', 'nama_mahasiswa' => 'MAHASISWA EMPAT', 'pembimbing_ke' => '1'],
        ]);
    }

    public function test_lists_theses_supervised_by_dtps_once_with_student_names(): void
    {
        $response = $this->getJson('/api/sapto/tables/penelitian-rujukan-tema/summary?ts=2025')->assertOk();

        $response->assertJsonPath('table_code', '6.b')
            ->assertJsonPath('source.partial', true)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.nama_dosen', 'DOSEN SATU')
            ->assertJsonPath('rows.0.nama_mahasiswa', 'MAHASISWA SATU')
            ->assertJsonPath('rows.0.judul_tesis', 'Deteksi Retina')
            ->assertJsonPath('rows.0.tahun', 2025)
            ->assertJsonPath('metrics.0.value', 1);
    }

    public function test_the_readiness_ring_no_longer_reports_the_table_as_failed(): void
    {
        $tabel = collect($this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk()->json('tables'))->keyBy('id');

        $this->assertNotSame('Gagal dimuat', $tabel['penelitian-rujukan-tema']['note']);
        $this->assertSame(1, $tabel['penelitian-rujukan-tema']['rows']);
    }
}
