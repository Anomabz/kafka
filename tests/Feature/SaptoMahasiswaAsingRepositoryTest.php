<?php

namespace Tests\Feature;

use App\Services\SaptoMahasiswaAsingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SaptoMahasiswaAsingRepository dan endpoint /sapto/mahasiswa-asing yang
 * memakainya. kode_program_studi 55201/61101 dipilih supaya konsisten dengan
 * SaptoApiTest.php (memetakan ke fakultas-ilmu-komputer/fakultas-ekonomi
 * lewat database/data/program_studi.csv).
 */
class SaptoMahasiswaAsingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // TS-2/TS-1/TS di repository ini ikut tahun berjalan (now()->year),
        // bukan dibaca dari request — fixture di bawah ditulis dengan asumsi
        // TS=2025/TS-1=2024/TS-2=2023, jadi bekukan waktunya di sini.
        Carbon::setTestNow('2025-06-01');

        // TS sekarang satu nilai bersama (App\Services\SaptoTsResolver), bukan
        // lagi tahun berjalan milik repository ini. Fixture di bawah ditulis
        // untuk TS=2025, jadi kunci nilainya supaya tes tidak ikut berubah
        // saat data acuan di tabel lain bergeser.
        config(['sapto.ts' => 2025]);

        $this->withSession(['sso_user' => [
            'sub' => 'test', 'name' => 'Test', 'email' => 'test@example.test',
            'nomor_identitas' => '199999999999999001',
        ]]);
        Cache::flush();
        $this->seedMahasiswaAsingData();

        // Auto-scopes this session to P1 (kode 61101, Manajemen) via the
        // korprodi mapping — MahasiswaController::data_mahasiswa_asing is now
        // gated by SaptoUserContext like every other table endpoint.
        \App\Models\Penugasan::create([
            'peran' => 'korprodi',
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'lingkup' => '61101',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_endpoint_still_returns_identical_shape_after_extraction(): void
    {
        // Endpoint kini selalu discope ke satu prodi akun (P1) — P2 (Teknik
        // Elektro) di luar scope, jadi total_prodi/data cuma 1, bukan 2.
        $this->getJson('/sapto/mahasiswa-asing')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tahun_akademik.ts_2', '2023')
            ->assertJsonPath('tahun_akademik.ts_1', '2024')
            ->assertJsonPath('tahun_akademik.ts', '2025')
            ->assertJsonPath('total_prodi', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_repository_computes_active_and_foreign_counts_per_prodi(): void
    {
        $repository = app(SaptoMahasiswaAsingRepository::class);
        $summary = $repository->summary();

        $rows = collect($summary['data']);

        $p1 = $rows->firstWhere('id_prodi', 'P1');
        // P1: M1 (asing, masuk 2023, masih aktif) + M2 (lokal, masuk 2023, masih aktif).
        $this->assertSame(2, $p1->mhs_aktif_ts2);
        $this->assertSame(2, $p1->mhs_aktif_ts1);
        $this->assertSame(2, $p1->mhs_aktif_ts);
        $this->assertSame(1, $p1->asing_ft_ts2);
        $this->assertSame(1, $p1->asing_ft_ts1);
        $this->assertSame(1, $p1->asing_ft_ts);
        $this->assertSame('Fakultas Ekonomi', $p1->fakultas);
        $this->assertSame('fakultas-ekonomi', $p1->fakultas_id);

        $p2 = $rows->firstWhere('id_prodi', 'P2');
        // P2: M3 (asing, masuk 2024) -> tidak aktif di TS-2 (2023).
        $this->assertSame(0, $p2->asing_ft_ts2);
        $this->assertSame(1, $p2->asing_ft_ts1);
        $this->assertSame(1, $p2->asing_ft_ts);
        $this->assertSame('Fakultas Teknik', $p2->fakultas);
    }

    public function test_repository_filters_by_fakultas_id(): void
    {
        $repository = app(SaptoMahasiswaAsingRepository::class);
        $summary = $repository->summary('fakultas-ekonomi', null);

        $rows = collect($summary['data']);

        $this->assertSame(1, $summary['total_prodi']);
        $this->assertSame('P1', $rows->first()->id_prodi);
    }

    public function test_repository_filters_by_program_studi_id(): void
    {
        $repository = app(SaptoMahasiswaAsingRepository::class);
        $summary = $repository->summary(null, 'P2');

        $rows = collect($summary['data']);

        $this->assertSame(1, $summary['total_prodi']);
        $this->assertSame('P2', $rows->first()->id_prodi);
    }

    private function seedMahasiswaAsingData(): void
    {
        DB::table('program_studi')->insert([
            ['id_prodi' => 'P1', 'kode_program_studi' => '61101', 'nama_program_studi' => 'Manajemen', 'nama_jenjang_pendidikan' => 'S2'],
            ['id_prodi' => 'P2', 'kode_program_studi' => '20201', 'nama_program_studi' => 'Teknik Elektro', 'nama_jenjang_pendidikan' => 'S1'],
        ]);

        DB::table('negara')->insert([
            ['id_negara' => 'ID', 'nama_negara' => 'Indonesia'],
            ['id_negara' => 'MY', 'nama_negara' => 'Malaysia'],
        ]);

        DB::table('biodata_mahasiswa')->insert([
            ['id_mahasiswa' => 'M1', 'nama_mahasiswa' => 'Ali (Asing)', 'id_negara' => 'MY'],
            ['id_mahasiswa' => 'M2', 'nama_mahasiswa' => 'Budi (Lokal)', 'id_negara' => 'ID'],
            ['id_mahasiswa' => 'M3', 'nama_mahasiswa' => 'Chen (Asing)', 'id_negara' => 'MY'],
        ]);

        DB::table('list_riwayat_pendidikan_mahasiswa')->insert([
            ['id_mahasiswa' => 'M1', 'id_prodi' => 'P1', 'nim' => 'NIM1', 'nama_mahasiswa' => 'Ali (Asing)', 'id_periode_masuk' => '20231', 'id_periode_keluar' => null],
            ['id_mahasiswa' => 'M2', 'id_prodi' => 'P1', 'nim' => 'NIM2', 'nama_mahasiswa' => 'Budi (Lokal)', 'id_periode_masuk' => '20231', 'id_periode_keluar' => null],
            ['id_mahasiswa' => 'M3', 'id_prodi' => 'P2', 'nim' => 'NIM3', 'nama_mahasiswa' => 'Chen (Asing)', 'id_periode_masuk' => '20241', 'id_periode_keluar' => null],
        ]);
    }
}
