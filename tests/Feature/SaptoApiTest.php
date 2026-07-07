<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaptoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->seedLocalData();
    }

    public function test_universitas_can_read_program_studi_reference_data_from_local_db(): void
    {
        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/program-studi')
            ->assertOk()
            ->assertJsonPath('can_select_program_studi', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => 'P1'])
            ->assertJsonFragment(['id' => 'P2']);
    }

    public function test_prodi_role_uses_account_program_studi_context_from_local_db(): void
    {
        $this->withHeaders([
            'X-SAPTO-Role' => 'prodi',
            'X-SAPTO-Program-Studi-Id' => 'P1',
        ])->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary?ts=2026&program_studi_id=P2')
            ->assertOk()
            ->assertJsonPath('program_studi.id', 'P1')
            ->assertJsonPath('rows.0.dosen_id', 'D1')
            ->assertJsonPath('rows.0.ps_diakreditasi.ts', 1)
            ->assertJsonPath('rows.0.ps_lain_pt.ts', 1);
    }

    public function test_fakultas_role_limits_program_studi_and_summary_scope(): void
    {
        $this->withHeaders([
            'X-SAPTO-Role' => 'fakultas',
            'X-SAPTO-Fakultas-Id' => 'fakultas-ilmu-komputer',
        ])->getJson('/api/sapto/program-studi')
            ->assertOk()
            ->assertJsonPath('can_select_program_studi', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'P1')
            ->assertJsonPath('data.0.fakultas', 'Fakultas Ilmu Komputer');

        // Scope fakultas: "PS yang Diakreditasi" = mahasiswa di prodi fakultas
        // itu (M1 di P1), "PS Lain di PT" = mahasiswa dosen yang sama di prodi
        // luar fakultas (M2 di P2/Ekonomi). Sebelumnya keliru selalu 0.
        $this->withHeaders([
            'X-SAPTO-Role' => 'fakultas',
            'X-SAPTO-Fakultas-Id' => 'fakultas-ilmu-komputer',
        ])->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary?ts=2026')
            ->assertOk()
            ->assertJsonPath('fakultas.id', 'fakultas-ilmu-komputer')
            ->assertJsonPath('rows.0.dosen_id', 'D1')
            ->assertJsonPath('rows.0.ps_diakreditasi.ts', 1)
            ->assertJsonPath('rows.0.ps_lain_pt.ts', 1);

        // Detail "PS Lain di PT" untuk fakultas Ilmu Komputer harus berisi M2
        // (mahasiswa Manajemen/Ekonomi) — di luar scope fakultas.
        $this->withHeaders([
            'X-SAPTO-Role' => 'fakultas',
            'X-SAPTO-Fakultas-Id' => 'fakultas-ilmu-komputer',
        ])->getJson('/api/sapto/tables/dosen-pembimbing-ta/detail?ts=2026&dosen_id=D1&scope=ps_lain_pt&year_key=ts')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.nama_mahasiswa', 'Mhs Dua')
            ->assertJsonPath('items.0.program_studi_id', 'P2');

        // Detail "PS yang Diakreditasi" berisi M1 (Teknik Informatika).
        $this->withHeaders([
            'X-SAPTO-Role' => 'fakultas',
            'X-SAPTO-Fakultas-Id' => 'fakultas-ilmu-komputer',
        ])->getJson('/api/sapto/tables/dosen-pembimbing-ta/detail?ts=2026&dosen_id=D1&scope=ps_diakreditasi&year_key=ts')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.program_studi_id', 'P1');
    }

    public function test_summary_defaults_ts_to_latest_academic_year_in_database(): void
    {
        // Tanpa parameter ts, TS harus mengikuti tahun akademik terbaru yang
        // benar-benar ada di database (di seeder: 2026), bukan tahun kalender.
        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary')
            ->assertOk()
            ->assertJsonPath('ts', 2026)
            ->assertJsonPath('year_columns.ts', 2026)
            ->assertJsonPath('available_years', [2026]);
    }

    public function test_university_scope_splits_by_dominant_prodi(): void
    {
        // Di tingkat universitas, "PS yang Diakreditasi" = prodi utama dosen
        // (prodi tempat ia paling banyak membimbing) dan "PS Lain di PT" =
        // bimbingan di prodi lain. D1 membimbing M1 di P1 dan M2 di P2 (seri),
        // jadi prodi utama = P1 (id terkecil): diakreditasi=1, PS lain=1.
        $response = $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary?ts=2026')
            ->assertOk();

        $d1 = collect($response->json('rows'))->firstWhere('dosen_id', 'D1');

        $this->assertSame(1, $d1['ps_diakreditasi']['ts']);
        $this->assertSame(1, $d1['ps_lain_pt']['ts']);

        // Detail "PS Lain di PT" tingkat universitas berisi M2 (prodi P2, bukan
        // prodi utama). Sebelumnya selalu kosong.
        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/dosen-pembimbing-ta/detail?ts=2026&dosen_id=D1&scope=ps_lain_pt&year_key=ts')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.program_studi_id', 'P2');

        // Detail "PS yang Diakreditasi" berisi M1 (prodi utama P1).
        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/dosen-pembimbing-ta/detail?ts=2026&dosen_id=D1&scope=ps_diakreditasi&year_key=ts')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.program_studi_id', 'P1');
    }

    public function test_universitas_can_filter_by_fakultas(): void
    {
        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/fakultas')
            ->assertOk()
            ->assertJsonPath('can_select_fakultas', true)
            ->assertJsonFragment(['id' => 'fakultas-ilmu-komputer'])
            ->assertJsonFragment(['id' => 'fakultas-ekonomi']);

        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/program-studi?fakultas_id=fakultas-ekonomi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'P2');
    }

    public function test_summary_rejects_invalid_ts(): void
    {
        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary?ts=abc')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Parameter TS tidak valid.');
    }

    public function test_summary_counts_a_student_with_duplicate_supervision_rows_once(): void
    {
        // Data sinkronisasi kadang punya lebih dari satu baris untuk
        // mahasiswa+dosen+tahun yang sama (id_bimbing_mahasiswa/id_aktivitas
        // berbeda, id_registrasi_mahasiswa sama). Pastikan itu tidak
        // menggandakan hitungan "jumlah mahasiswa yang dibimbing".
        DB::table('dosen_pembimbing')->insert([
            'id_registrasi_mahasiswa' => 'M1',
            'id_bimbing_mahasiswa' => 'M1-duplicate',
            'nim' => 'NIM1',
            'nama_mahasiswa' => 'Mhs Satu',
            'id_dosen' => 'D1',
            'nama_dosen' => 'Dr. Budi',
            'nidn' => '0001',
            'nuptk' => null,
            'pembimbing_ke' => '1',
            'id_aktivitas' => 'A1-duplicate',
            'jenis_aktivitas' => 'Tugas akhir',
        ]);

        DB::table('list_aktivitas_mahasiswa')->insert([
            'id_aktivitas' => 'A1-duplicate',
            'id_prodi' => 'P1',
            'nama_prodi' => 'Teknik Informatika',
            'id_semester' => '20261',
            'judul' => 'Sistem Informasi Akademik (revisi)',
            'status_sync' => 'sudah sync',
        ]);

        $response = $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary?ts=2026')
            ->assertOk();

        $rows = collect($response->json('rows'));
        $d1 = $rows->firstWhere('dosen_id', 'D1');

        // M1 (2 baris aktivitas di P1) dihitung sekali. Prodi utama D1 = P1
        // (seri dengan P2, id terkecil), jadi diakreditasi=1 (M1), PS lain=1
        // (M2), bukan 3.
        $this->assertNotNull($d1);
        $this->assertSame(1, $d1['ps_diakreditasi']['ts']);
        $this->assertSame(1, $d1['ps_lain_pt']['ts']);

        // Detail "PS yang Diakreditasi" berisi M1 saja — dua baris aktivitasnya
        // menyatu jadi satu mahasiswa.
        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/dosen-pembimbing-ta/detail?ts=2026&dosen_id=D1&scope=ps_diakreditasi&year_key=ts')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.program_studi_id', 'P1');
    }

    private function seedLocalData(): void
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

        DB::table('dosen_pembimbing')->insert([
            [
                'id_registrasi_mahasiswa' => 'M1',
                'id_bimbing_mahasiswa' => 'M1',
                'nim' => 'NIM1',
                'nama_mahasiswa' => 'Mhs Satu',
                'id_dosen' => 'D1',
                'nama_dosen' => 'Dr. Budi',
                'nidn' => '0001',
                'nuptk' => null,
                'pembimbing_ke' => '1',
                'id_aktivitas' => 'A1',
                'jenis_aktivitas' => 'Skripsi',
            ],
            [
                'id_registrasi_mahasiswa' => 'M2',
                'id_bimbing_mahasiswa' => 'M2',
                'nim' => 'NIM2',
                'nama_mahasiswa' => 'Mhs Dua',
                'id_dosen' => 'D1',
                'nama_dosen' => 'Dr. Budi',
                'nidn' => '0001',
                'nuptk' => null,
                'pembimbing_ke' => '1',
                'id_aktivitas' => 'A2',
                'jenis_aktivitas' => 'Tesis',
            ],
            [
                // Pembimbing pendamping (bukan utama) — tidak boleh terhitung.
                'id_registrasi_mahasiswa' => 'M3',
                'id_bimbing_mahasiswa' => 'M3',
                'nim' => 'NIM3',
                'nama_mahasiswa' => 'Mhs Tiga',
                'id_dosen' => 'D2',
                'nama_dosen' => 'Dr. Citra',
                'nidn' => '0002',
                'nuptk' => null,
                'pembimbing_ke' => '2',
                'id_aktivitas' => 'A3',
                'jenis_aktivitas' => 'Skripsi',
            ],
        ]);

        DB::table('list_aktivitas_mahasiswa')->insert([
            [
                'id_aktivitas' => 'A1',
                'id_prodi' => 'P1',
                'nama_prodi' => 'Teknik Informatika',
                'id_semester' => '20261',
                'judul' => 'Sistem Informasi Akademik',
                'status_sync' => 'sudah sync',
            ],
            [
                'id_aktivitas' => 'A2',
                'id_prodi' => 'P2',
                'nama_prodi' => 'Manajemen',
                'id_semester' => '20261',
                'judul' => 'Analisis Kinerja Organisasi',
                'status_sync' => 'sudah sync',
            ],
            [
                'id_aktivitas' => 'A3',
                'id_prodi' => 'P1',
                'nama_prodi' => 'Teknik Informatika',
                'id_semester' => '20261',
                'judul' => 'Tidak Relevan (bukan pembimbing utama)',
                'status_sync' => 'sudah sync',
            ],
        ]);
    }
}
