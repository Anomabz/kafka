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
        $this->seedReference();
        $this->seedLulusan();
        $this->seedIntake();
        $this->seedPenugasan();
    }

    public function test_ipk_lulusan_rows_per_graduation_year(): void
    {
        $response = $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/ipk-lulusan/summary?ts=2024')
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

        // TS (2024): 4 lulusan (P1 4.0, P1 3.5, P2 3.8, P1 IPK 0). IPK 0 & baris
        // "Putus Studi" tak masuk statistik -> min 3.5, maks 4.0.
        $tsRow = $rows->firstWhere('tahun_lulus', '2024 (TS)');
        $this->assertSame(4, $tsRow['jumlah_lulusan']);
        $this->assertEquals(3.5, $tsRow['ipk_min']);
        $this->assertEquals(4.0, $tsRow['ipk_maks']);
    }

    public function test_ipk_lulusan_respects_fakultas_scope(): void
    {
        // Fakultas Ilmu Komputer -> hanya P1. TS 2024 P1: 4.0, 3.5, & IPK 0 ->
        // jumlah 3 (P2 3.8 tidak masuk karena beda fakultas).
        $tsRow = collect($this->withHeaders([
            'X-SAPTO-Role' => 'fakultas',
            'X-SAPTO-Fakultas-Id' => 'fakultas-ilmu-komputer',
        ])->getJson('/api/sapto/tables/ipk-lulusan/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('fakultas.id', 'fakultas-ilmu-komputer')
            ->json('rows'));

        $this->assertSame(3, collect($tsRow)->firstWhere('tahun_lulus', '2024 (TS)')['jumlah_lulusan']);
    }

    public function test_masa_studi_requires_program_studi(): void
    {
        // Tanpa prodi terpilih -> pesan pengarah, tabel kosong.
        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/masa-studi/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('notice', fn ($notice): bool => is_string($notice) && str_contains($notice, 'program studi'));
    }

    public function test_masa_studi_cohort_matrix_for_selected_prodi(): void
    {
        $response = $this->withHeaders([
            'X-SAPTO-Role' => 'prodi',
            'X-SAPTO-Program-Studi-Id' => 'P1',
        ])->getJson('/api/sapto/tables/masa-studi/summary?ts=2024')
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
        $response = $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/dosen-tetap-pt/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('table_code', '3.a.1')
            // Kolom mengikuti format BAN-PT Tabel 3.a.1.
            ->assertJsonPath('columns.8.label', 'Jabatan Akademik');

        $rows = collect($response->json('rows'));

        // D1 (P) & D2 (L) homebase; D3 bukan homebase; duplikat D1 dihitung sekali.
        $this->assertSame(2, $rows->count());
        $this->assertEqualsCanonicalizing(['D1', 'D2'], $rows->pluck('dosen_id')->all());

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(2, $metrics['Total dosen tetap']);
        $this->assertSame(1, $metrics['Laki-laki']);
        $this->assertSame(1, $metrics['Perempuan']);
    }

    public function test_dosen_tetap_respects_prodi_scope(): void
    {
        $rows = collect($this->withHeaders([
            'X-SAPTO-Role' => 'prodi',
            'X-SAPTO-Program-Studi-Id' => 'P2',
        ])->getJson('/api/sapto/tables/dosen-tetap-pt/summary?ts=2024')
            ->assertOk()
            ->assertJsonPath('program_studi.id', 'P2')
            ->json('rows'));

        $this->assertSame(1, $rows->count());
        $this->assertSame('D2', $rows->first()['dosen_id']);
    }

    public function test_ipk_lulusan_defaults_ts_to_latest_year(): void
    {
        // Seeder punya lulusan 2024 & 2023 -> tahun terbaru (2024) jadi default.
        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/ipk-lulusan/summary')
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
