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
        $this->withSession(['sso_user' => [
            'sub' => 'test', 'name' => 'Test', 'email' => 'test@example.test',
            'nomor_identitas' => '199999999999999001',
        ]]);

        $this->seedLocalData();

        // Auto-scopes this session to P1 (kode 55201, Teknik Informatika) via
        // the korprodi mapping SaptoUserContext now derives scope from —
        // X-SAPTO-* headers are no longer trusted for role/scope.
        \App\Models\Korprodi::create([
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'kode_program_studi' => '55201',
        ]);
    }

    /**
     * Fakultas/universitas-wide access (seeing beyond one's own korprodi
     * scope) is intentionally disabled until that entitlement data exists —
     * see SaptoUserContext::fromRequest(). These tests exercised that old,
     * header-trusting behavior; skipped rather than deleted so the coverage
     * is easy to restore once fakultas/universitas entitlements land.
     */
    public function test_universitas_can_read_program_studi_reference_data_from_local_db(): void
    {
        $this->markTestSkipped('Universitas-wide scope is pending entitlement data — see SaptoUserContext::fromRequest().');
    }

    public function test_prodi_role_uses_account_program_studi_context_from_local_db(): void
    {
        $this->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary?ts=2026&program_studi_id=P2')
            ->assertOk()
            ->assertJsonPath('program_studi.id', 'P1')
            ->assertJsonPath('rows.0.dosen_id', 'D1')
            ->assertJsonPath('rows.0.ps_diakreditasi.ts', 1)
            ->assertJsonPath('rows.0.ps_lain_pt.ts', 1);
    }

    public function test_fakultas_role_limits_program_studi_and_summary_scope(): void
    {
        $this->markTestSkipped('Fakultas-wide scope is pending entitlement data — see SaptoUserContext::fromRequest().');
    }

    public function test_summary_defaults_ts_to_latest_academic_year_in_database(): void
    {
        // Tanpa parameter ts, TS harus mengikuti tahun akademik terbaru yang
        // benar-benar ada di database (di seeder: 2026), bukan tahun kalender.
        $this->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary')
            ->assertOk()
            ->assertJsonPath('ts', 2026)
            ->assertJsonPath('year_columns.ts', 2026)
            ->assertJsonPath('available_years', [2026]);
    }

    public function test_university_scope_splits_by_dominant_prodi(): void
    {
        $this->markTestSkipped('Universitas-wide scope is pending entitlement data — see SaptoUserContext::fromRequest().');
    }

    public function test_universitas_can_filter_by_fakultas(): void
    {
        $this->markTestSkipped('Universitas-wide scope is pending entitlement data — see SaptoUserContext::fromRequest().');
    }

    public function test_summary_rejects_invalid_ts(): void
    {
        $this->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary?ts=abc')
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

        $response = $this->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary?ts=2026')
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
        $this->getJson('/api/sapto/tables/dosen-pembimbing-ta/detail?ts=2026&dosen_id=D1&scope=ps_diakreditasi&year_key=ts')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.program_studi_id', 'P1');
    }

    public function test_summary_and_detail_exclude_kerja_praktik_mislabeled_as_thesis(): void
    {
        // Data sinkronisasi kadang menaruh aktivitas kerja praktik pada slot
        // ber-jenis_aktivitas "Skripsi" dengan judul harfiah "Kerja Praktik".
        // Tabel 3.a.2 harus tetap hanya berisi tugas akhir -> baris seperti ini
        // tidak boleh menambah hitungan maupun muncul di detail.
        DB::table('dosen_pembimbing')->insert([
            'id_registrasi_mahasiswa' => 'M9',
            'id_bimbing_mahasiswa' => 'M9',
            'nim' => 'NIM9',
            'nama_mahasiswa' => 'Mhs Sembilan',
            'id_dosen' => 'D1',
            'nama_dosen' => 'Dr. Budi',
            'nidn' => '0001',
            'nuptk' => null,
            'pembimbing_ke' => '1',
            'id_aktivitas' => 'A9',
            'jenis_aktivitas' => 'Skripsi',
        ]);

        DB::table('list_aktivitas_mahasiswa')->insert([
            'id_aktivitas' => 'A9',
            'id_prodi' => 'P1',
            'nama_prodi' => 'Teknik Informatika',
            'id_semester' => '20261',
            'judul' => 'Kerja Praktik',
            'status_sync' => 'sudah sync',
        ]);

        $d1 = collect($this->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary?ts=2026')
            ->assertOk()
            ->json('rows'))->firstWhere('dosen_id', 'D1');

        // M9 tidak menambah diakreditasi (tetap 1 = M1), bukan 2.
        $this->assertSame(1, $d1['ps_diakreditasi']['ts']);

        // Detail juga tidak memuat M9 / judul "Kerja Praktik".
        $items = collect($this->getJson('/api/sapto/tables/dosen-pembimbing-ta/detail?ts=2026&dosen_id=D1&scope=ps_diakreditasi&year_key=ts')
            ->assertOk()
            ->json('items'));

        $this->assertFalse($items->contains('mahasiswa_id', 'M9'));
        $this->assertFalse($items->contains(fn (array $i): bool => str_contains(strtolower($i['judul_tugas_akhir']), 'kerja prakt')));
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
