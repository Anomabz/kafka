<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaptoApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'services.pangkalan_data.base_url' => 'https://pangkalan-data.unsri.ac.id',
            'services.pangkalan_data.email' => 'user@example.test',
            'services.pangkalan_data.password' => 'secret',
            'sapto.api.program_studi_max_pages' => 1,
            'sapto.api.data_max_pages' => 1,
            'sapto.api.activity_lookup_limit' => 10,
        ]);
    }

    public function test_universitas_can_read_program_studi_reference_data_from_api(): void
    {
        $this->fakePangkalanDataApi();

        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/program-studi')
            ->assertOk()
            ->assertJsonPath('can_select_program_studi', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => 'P1'])
            ->assertJsonFragment(['id' => 'P2']);
    }

    public function test_prodi_role_uses_account_program_studi_context_from_api(): void
    {
        $this->fakePangkalanDataApi();

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
        $this->fakePangkalanDataApi();

        $this->withHeaders([
            'X-SAPTO-Role' => 'fakultas',
            'X-SAPTO-Fakultas-Id' => 'fakultas-ilmu-komputer',
        ])->getJson('/api/sapto/program-studi')
            ->assertOk()
            ->assertJsonPath('can_select_program_studi', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'P1')
            ->assertJsonPath('data.0.fakultas', 'Fakultas Ilmu Komputer');

        $this->withHeaders([
            'X-SAPTO-Role' => 'fakultas',
            'X-SAPTO-Fakultas-Id' => 'fakultas-ilmu-komputer',
        ])->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary?ts=2026')
            ->assertOk()
            ->assertJsonPath('fakultas.id', 'fakultas-ilmu-komputer')
            ->assertJsonPath('rows.0.dosen_id', 'D1')
            ->assertJsonPath('rows.0.ps_diakreditasi.ts', 1)
            ->assertJsonPath('rows.0.ps_lain_pt.ts', 0);
    }

    public function test_universitas_can_filter_by_fakultas(): void
    {
        $this->fakePangkalanDataApi();

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

    public function test_summary_rejects_invalid_ts_before_calling_api(): void
    {
        Http::fake();

        $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/tables/dosen-pembimbing-ta/summary?ts=abc')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Parameter TS tidak valid.');

        Http::assertNothingSent();
    }

    private function fakePangkalanDataApi(): void
    {
        $profile = [
            'id_perguruan_tinggi' => 'PT-UNSRI',
            'kode_perguruan_tinggi' => '001009',
            'nama_perguruan_tinggi' => 'Universitas Sriwijaya',
        ];

        $programStudis = [
            [
                'id_perguruan_tinggi' => 'PT-UNSRI',
                'id_prodi' => 'P1',
                'kode_program_studi' => '55201',
                'nama_program_studi' => 'Teknik Informatika',
                'nama_jenjang_pendidikan' => 'S1',
                'nama_perguruan_tinggi' => 'Universitas Sriwijaya',
                'kode_perguruan_tinggi' => '001009',
            ],
            [
                'id_perguruan_tinggi' => 'PT-UNSRI',
                'id_prodi' => 'P2',
                'kode_program_studi' => '61101',
                'nama_program_studi' => 'Manajemen',
                'nama_jenjang_pendidikan' => 'S1',
                'nama_perguruan_tinggi' => 'Universitas Sriwijaya',
                'kode_perguruan_tinggi' => '001009',
            ],
            [
                'id_perguruan_tinggi' => 'PT-OTHER',
                'id_prodi' => 'P3',
                'kode_program_studi' => '11111',
                'nama_program_studi' => 'Bukan Unsri',
                'nama_jenjang_pendidikan' => 'S1',
                'nama_perguruan_tinggi' => 'Universitas Lain',
                'kode_perguruan_tinggi' => '999999',
            ],
        ];

        $bimbingMahasiswa = [
            [
                'id_bimbing_mahasiswa' => 'M1',
                'id_dosen' => 'D1',
                'nidn' => '0001',
                'nama_dosen' => 'Dr. Budi',
                'pembimbing_ke' => '1',
                'id_aktivitas' => 'A1',
                'nama_kategori_kegiatan' => 'Skripsi (pembimbing utama)',
            ],
            [
                'id_bimbing_mahasiswa' => 'M2',
                'id_dosen' => 'D1',
                'nidn' => '0001',
                'nama_dosen' => 'Dr. Budi',
                'pembimbing_ke' => '1',
                'id_aktivitas' => 'A2',
                'nama_kategori_kegiatan' => 'Tesis (pembimbing utama)',
            ],
            [
                'id_bimbing_mahasiswa' => 'M3',
                'id_dosen' => 'D2',
                'nidn' => '0002',
                'nama_dosen' => 'Dr. Citra',
                'pembimbing_ke' => '2',
                'id_aktivitas' => 'A3',
                'nama_kategori_kegiatan' => 'Skripsi (pembimbing pendamping)',
            ],
        ];

        $activities = [
            'A1' => [
                'id_aktivitas' => 'A1',
                'id_prodi' => 'P1',
                'nama_prodi' => 'Teknik Informatika',
                'id_semester' => '20261',
                'judul' => 'Sistem Informasi Akademik',
                'status_sync' => 'sudah sync',
            ],
            'A2' => [
                'id_aktivitas' => 'A2',
                'id_prodi' => 'P2',
                'nama_prodi' => 'Manajemen',
                'id_semester' => '20261',
                'judul' => 'Analisis Kinerja Organisasi',
                'status_sync' => 'sudah sync',
            ],
        ];

        Http::fake(function (Request $request) use ($activities, $bimbingMahasiswa, $profile, $programStudis) {
            $url = $request->url();

            if (str_contains($url, '/api/login')) {
                return Http::response(['data' => ['token' => 'fake-token']], 200);
            }

            if (str_contains($url, '/api/feeder/profil-pt')) {
                return Http::response(['data' => [$profile]], 200);
            }

            if (str_contains($url, '/api/feeder/all-prodi')) {
                return Http::response($this->paginated($programStudis), 200);
            }

            if (str_contains($url, '/api/feeder/bimbing-mahasiswa')) {
                return Http::response($this->paginated($bimbingMahasiswa), 200);
            }

            if (str_contains($url, '/api/feeder/list-aktivitas-mahasiswa')) {
                $activityId = data_get($request->data(), 'filter[id_aktivitas]')
                    ?? data_get($request->data(), 'filter.id_aktivitas');

                return Http::response($this->paginated(isset($activities[$activityId]) ? [$activities[$activityId]] : []), 200);
            }

            return Http::response(['message' => 'Unexpected API request.'], 404);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function paginated(array $rows): array
    {
        return [
            'data' => $rows,
            'links' => [
                'first' => '/?page=1',
                'last' => '/?page=1',
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 500,
                'total' => count($rows),
            ],
        ];
    }
}
