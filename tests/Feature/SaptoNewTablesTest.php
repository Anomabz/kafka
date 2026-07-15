<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 8.a (IPK Lulusan, format per tahun lulus), 8.c (Masa Studi, matriks
 * kohort per prodi), dan 3.a.1 (Dosen Tetap PT). Tabel sumber dibuat oleh
 * migration guarded khusus test (di produksi sudah ada dari sinkronisasi).
 */
class SaptoNewTablesTest extends TestCase
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
        $this->seedReference();
        $this->seedLulusan();
        $this->seedIntake();
        $this->seedPenugasan();

        // Auto-scopes this session to P1 (kode 55201) via the korprodi
        // mapping — X-SAPTO-* headers are no longer trusted for scope.
        \App\Models\Korprodi::create([
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'kode_program_studi' => '55201',
        ]);
    }

    public function test_ipk_lulusan_rows_per_graduation_year(): void
    {
        $response = $this->getJson('/api/sapto/tables/ipk-lulusan/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '8.a')
            // Kolom mengikuti format BAN-PT: tahun lulus + IPK min/rata/maks.
            ->assertJsonPath('columns.1.label', 'Tahun Lulus')
            ->assertJsonPath('columns.3.label', 'IPK Min');

        $rows = collect($response->json('rows'));

        // 3 baris: TS-2 (2022, kosong), TS-1 (2023), TS (2024).
        $this->assertSame(3, $rows->count());

        $ts2 = $rows->firstWhere('tahun_lulus', '2022 (TS-2)');
        $this->assertSame(0, $ts2['jumlah_lulusan']);
        $this->assertNull($ts2['ipk_min']);

        $ts1 = $rows->firstWhere('tahun_lulus', '2023 (TS-1)');
        $this->assertSame(1, $ts1['jumlah_lulusan']);

        // TS (2024), scope P1: 3 lulusan (P1 4.0, P1 3.5, P1 IPK 0; P2 3.8 di
        // luar scope). IPK 0 & baris "Putus Studi" tak masuk statistik -> min
        // 3.5, maks 4.0.
        $tsRow = $rows->firstWhere('tahun_lulus', '2024 (TS)');
        $this->assertSame(3, $tsRow['jumlah_lulusan']);
        $this->assertEquals(3.5, $tsRow['ipk_min']);
        $this->assertEquals(4.0, $tsRow['ipk_maks']);
    }

    public function test_ipk_lulusan_recovers_graduation_year_from_tanggal_keluar(): void
    {
        // Baris "Lulus" tanpa id_periode_keluar tapi punya tanggal_keluar
        // (DD-MM-YYYY) harus tetap terhitung pada tahun lulusnya, bukan di-drop.
        DB::table('list_mahasiswa_lulus_do')->insert([
            'nama_jenis_keluar' => 'Lulus',
            'id_prodi' => 'P1',
            'nama_program_studi' => 'Teknik Informatika',
            'angkatan' => '2020',
            'id_periode_keluar' => null,
            'ipk' => '3.60',
            'tgl_masuk_sp' => '01-09-2020',
            'tanggal_keluar' => '15-06-2024',
        ]);

        $tsRow = collect($this->getJson('/api/sapto/tables/ipk-lulusan/summary?ts=2024')
            ->assertOk()
            ->json('rows'))->firstWhere('tahun_lulus', '2024 (TS)');

        // 3 dari seeder (scope P1) + 1 hasil pemulihan dari tanggal_keluar = 4.
        $this->assertSame(4, $tsRow['jumlah_lulusan']);
    }

    public function test_ipk_lulusan_respects_fakultas_scope(): void
    {
        $this->markTestSkipped('Fakultas-wide scope is pending entitlement data — see SaptoUserContext::fromRequest().');
    }

    public function test_masa_studi_requires_program_studi(): void
    {
        $this->markTestSkipped('Every session now always resolves to a specific program studi — see SaptoUserContext::fromRequest().');
    }

    public function test_masa_studi_cohort_matrix_for_selected_prodi(): void
    {
        $response = $this->getJson('/api/sapto/tables/masa-studi/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '8.c')
            ->assertJsonPath('program_studi.id', 'P1');

        $rows = collect($response->json('rows'));

        // Jenjang S1 -> kohort TS-6..TS-3 (2018..2021).
        $c2019 = $rows->firstWhere('tahun_masuk', '2019 (TS-5)');
        $this->assertSame(3, $c2019['diterima']);        // 3 mahasiswa baru 2019
        $this->assertSame(1, $c2019['g_1']);             // 1 lulus 2023 (akhir TS-1)
        $this->assertSame(1, $c2019['g_0']);             // 1 lulus 2024 (akhir TS)
        $this->assertSame(2, $c2019['lulus_sd_ts']);
        $this->assertEquals(4.25, $c2019['masa_studi_rata']);

        $c2020 = $rows->firstWhere('tahun_masuk', '2020 (TS-4)');
        $this->assertSame(2, $c2020['diterima']);
        $this->assertSame(2, $c2020['g_0']);             // 2 lulus 2024
        $this->assertEquals(4.0, $c2020['masa_studi_rata']);
    }

    public function test_dosen_tetap_lists_homebase_roster(): void
    {
        DB::table('riwayat_pendidikan_dosen')->insert([
            // D1 punya S2 dan S3 -> di kartu dihitung S3 saja, bukan dua-duanya.
            ['id_dosen' => 'D1', 'nama_jenjang_pendidikan' => 'S2', 'nama_bidang_studi' => 'Ilmu Komputer', 'nama_perguruan_tinggi' => 'Universitas Indonesia', 'tahun_lulus' => '2009'],
            ['id_dosen' => 'D1', 'nama_jenjang_pendidikan' => 'S3', 'nama_bidang_studi' => 'Ilmu Komputer', 'nama_perguruan_tinggi' => 'Universitas Gadjah Mada', 'tahun_lulus' => '2015'],
        ]);

        $response = $this->getJson('/api/sapto/tables/dosen-tetap-pt/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '3.a.1')
            // Kolom mengikuti format BAN-PT Tabel 3.a.1.
            ->assertJsonPath('columns.8.label', 'Jabatan Akademik');

        $rows = collect($response->json('rows'));

        // Scope P1: hanya D1 homebase (D2 homebase-nya P2, di luar scope; D3
        // bukan homebase). Duplikat D1 dihitung sekali.
        $this->assertSame(1, $rows->count());
        $this->assertEqualsCanonicalizing(['D1'], $rows->pluck('dosen_id')->all());

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(1, $metrics['Total dosen tetap']);
        $this->assertSame(0, $metrics['Pendidikan S2 (Magister)']);
        $this->assertSame(1, $metrics['Pendidikan S3 (Doktor)']);
    }

    public function test_dosen_tetap_includes_education_jabatan_and_sertifikat(): void
    {
        DB::table('riwayat_pendidikan_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_jenjang_pendidikan' => 'S1', 'nama_bidang_studi' => 'Teknik Informatika', 'nama_perguruan_tinggi' => 'Universitas Sriwijaya', 'tahun_lulus' => '2005'],
            ['id_dosen' => 'D1', 'nama_jenjang_pendidikan' => 'S2', 'nama_bidang_studi' => 'Ilmu Komputer', 'nama_perguruan_tinggi' => 'Universitas Indonesia', 'tahun_lulus' => '2009'],
            ['id_dosen' => 'D1', 'nama_jenjang_pendidikan' => 'S3', 'nama_bidang_studi' => 'Ilmu Komputer', 'nama_perguruan_tinggi' => 'Universitas Gadjah Mada', 'tahun_lulus' => '2015'],
        ]);

        DB::table('riwayat_fungsional_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_jabatan_fungsional' => 'Lektor', 'mulai_sk_jabatan' => '2016-01-01'],
            // Baris terbaru -> harus menang atas 'Lektor', dan "Profesor" dipetakan ke "Guru Besar".
            ['id_dosen' => 'D1', 'nama_jabatan_fungsional' => 'Profesor', 'mulai_sk_jabatan' => '2020-01-01'],
        ]);

        DB::table('riwayat_sertifikasi_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_jenis_sertifikasi' => 'Sertifikasi Dosen', 'tahun_sertifikasi' => '2017'],
            ['id_dosen' => 'D1', 'nama_jenis_sertifikasi' => 'Sertifikasi Kompetensi', 'tahun_sertifikasi' => '2021'],
            // Asesor BKD sengaja tak dipetakan ke kolom pendidik atau kompetensi.
            ['id_dosen' => 'D1', 'nama_jenis_sertifikasi' => 'Sertifikasi Asesor BKD', 'tahun_sertifikasi' => '2022'],
        ]);

        // D4 homebase P1, tanpa riwayat sama sekali -> menguji kolom tambahan
        // default null ("-" di UI). D2 (homebase P2) di luar scope P1.
        DB::table('list_penugasan_dosen')->insert([
            'id_dosen' => 'D4', 'nama_dosen' => 'Dr. Dewi', 'nidn' => '444', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2024/2025',
        ]);

        $rows = collect($this->getJson('/api/sapto/tables/dosen-tetap-pt/summary?ts=2024')
            ->assertOk()
            ->json('rows'));

        $d1 = $rows->firstWhere('dosen_id', 'D1');
        $this->assertSame('Ilmu Komputer - Universitas Indonesia', $d1['pendidikan_magister']);
        $this->assertSame('Ilmu Komputer - Universitas Gadjah Mada', $d1['pendidikan_doktor']);
        $this->assertSame('Ilmu Komputer', $d1['bidang_keahlian']);
        $this->assertSame('Guru Besar', $d1['jabatan_akademik']);
        $this->assertSame('Sertifikasi Dosen (2017)', $d1['sertifikat_pendidik']);
        $this->assertSame('Sertifikasi Kompetensi (2021)', $d1['sertifikat_kompetensi']);

        // D4 tak punya riwayat sama sekali -> semua kolom tambahan null ("-" di UI).
        $d4 = $rows->firstWhere('dosen_id', 'D4');
        $this->assertNull($d4['pendidikan_magister']);
        $this->assertNull($d4['jabatan_akademik']);
        $this->assertNull($d4['sertifikat_pendidik']);
    }

    public function test_dosen_tetap_respects_prodi_scope(): void
    {
        // Scope kini ditentukan server-side per akun (korprodi), bukan header
        // klien — pakai sesi korprodi P2 terpisah untuk menguji sisi lain.
        $this->withSession(['sso_user' => [
            'sub' => 'test-p2', 'name' => 'Test P2', 'email' => 'test-p2@example.test',
            'nomor_identitas' => '199999999999999002',
        ]]);
        \App\Models\Korprodi::create([
            'nip' => '199999999999999002',
            'nama' => 'Test Korprodi P2',
            'kode_program_studi' => '61101',
        ]);

        $rows = collect($this->getJson('/api/sapto/tables/dosen-tetap-pt/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('program_studi.id', 'P2')
            ->json('rows'));

        $this->assertSame(1, $rows->count());
        $this->assertSame('D2', $rows->first()['dosen_id']);
    }

    public function test_ipk_lulusan_defaults_ts_to_latest_year(): void
    {
        // Seeder punya lulusan 2024 & 2023 -> tahun terbaru (2024) jadi default.
        $this->getJson('/api/sapto/tables/ipk-lulusan/summary')
            ->assertOk()
            ->assertJsonPath('ts', 2024)
            ->assertJsonPath('available_years', [2024, 2023]);
    }

    private function seedReference(): void
    {
        DB::table('program_studi')->insert([
            [
                'id_prodi' => 'P1',
                'kode_program_studi' => '55201',
                'nama_program_studi' => 'Teknik Informatika',
                'status' => 'AKTIF',
                'id_jenjang_pendidikan' => 1,
                'nama_jenjang_pendidikan' => 'S1',
            ],
            [
                'id_prodi' => 'P2',
                'kode_program_studi' => '61101',
                'nama_program_studi' => 'Manajemen',
                'status' => 'AKTIF',
                'id_jenjang_pendidikan' => 1,
                'nama_jenjang_pendidikan' => 'S1',
            ],
        ]);
    }

    private function seedLulusan(): void
    {
        DB::table('list_mahasiswa_lulus_do')->insert([
            // (prodi, angkatan, ipk, masuk, yudisium, periode_keluar, jenis)
            $this->lulus('P1', '2019', '3.00', '01-09-2019', '01-09-2023', '20231'), // lulus 2023, masa 4.0
            $this->lulus('P1', '2019', '4.00', '01-09-2019', '01-03-2024', '20241'), // lulus 2024, masa 4.5
            $this->lulus('P1', '2020', '3.50', '01-09-2020', '01-09-2024', '20241'), // lulus 2024, masa 4.0
            $this->lulus('P2', '2020', '3.80', '01-09-2020', '01-09-2024', '20241'), // P2 lulus 2024
            $this->lulus('P1', '2020', '0', '', '', '20241'),                        // IPK 0, lulus 2024
            // Bukan "Lulus": diabaikan.
            $this->lulus('P1', '2019', '2.50', '01-09-2019', '01-09-2023', '20241', 'Putus Studi'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function lulus(string $prodi, string $angkatan, string $ipk, string $masuk, string $yudisium, string $periode, string $jenisKeluar = 'Lulus'): array
    {
        return [
            'nama_jenis_keluar' => $jenisKeluar,
            'id_prodi' => $prodi,
            'nama_program_studi' => $prodi === 'P1' ? 'Teknik Informatika' : 'Manajemen',
            'angkatan' => $angkatan,
            'id_periode_keluar' => $periode,
            'ipk' => $ipk,
            'tgl_masuk_sp' => $masuk,
            'tgl_sk_yudisium' => $yudisium,
        ];
    }

    private function seedIntake(): void
    {
        $rows = [];
        // 3 mahasiswa baru P1 angkatan 2019, 2 angkatan 2020.
        foreach ([['2019', 3], ['2020', 2]] as [$year, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    'id_prodi' => 'P1',
                    'nama_jenis_daftar' => 'Peserta didik baru',
                    'id_periode_masuk' => $year.'1',
                ];
            }
        }
        // Mahasiswa pindahan (bukan "Peserta didik baru") -> tak dihitung diterima.
        $rows[] = ['id_prodi' => 'P1', 'nama_jenis_daftar' => 'Pindahan', 'id_periode_masuk' => '20191'];

        DB::table('list_riwayat_pendidikan_mahasiswa')->insert($rows);
    }

    private function seedPenugasan(): void
    {
        DB::table('list_penugasan_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2024/2025'],
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2024/2025'],
            ['id_dosen' => 'D2', 'nama_dosen' => 'Dr. Budi', 'nidn' => '222', 'jk' => 'L', 'id_prodi' => 'P2', 'nama_program_studi' => 'Manajemen', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2024/2025'],
            ['id_dosen' => 'D3', 'nama_dosen' => 'Dr. Candra', 'nidn' => '333', 'jk' => 'L', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '0', 'nama_tahun_ajaran' => '2024/2025'],
        ]);
    }
}
