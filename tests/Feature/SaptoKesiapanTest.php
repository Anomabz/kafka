<?php

namespace Tests\Feature;

use App\Models\Penugasan;
use App\Services\SaptoIpkLulusanRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GET /api/sapto/kesiapan -- cincin kesiapan data di Beranda mobile. Fokusnya
 * orkestrasi (klasifikasi status, isolasi galat per tabel, cache per scope),
 * bukan isi tiap tabel: itu sudah dites di test tabelnya masing-masing.
 */
class SaptoKesiapanTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS = ['lengkap', 'sebagian', 'kosong'];

    // Status yang tidak ikut dihitung dalam total.
    private const DI_LUAR_TOTAL = ['perlu_prodi', 'tidak_wajib'];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->sebagai('199999999999999001');

        DB::table('program_studi')->insert([
            ['id_prodi' => 'P1', 'kode_program_studi' => '55201', 'nama_program_studi' => 'Teknik Informatika', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 1, 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'P2', 'kode_program_studi' => '61101', 'nama_program_studi' => 'Manajemen', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 1, 'nama_jenjang_pendidikan' => 'S1'],
        ]);

        // Data seed ini hanya untuk P1.
        DB::table('list_mahasiswa_lulus_do')->insert([
            'nama_jenis_keluar' => 'Lulus',
            'id_prodi' => 'P1',
            'nama_program_studi' => 'Teknik Informatika',
            'angkatan' => '2021',
            'id_periode_keluar' => '20251',
            'ipk' => '3.50',
            'tgl_masuk_sp' => '01-09-2021',
            'tgl_sk_yudisium' => '01-09-2025',
        ]);

        // Dosen pembimbing TA (3.a.2) juga hanya untuk P1: berbeda dengan 8.a,
        // tabel ini benar-benar kosong (0 baris) untuk prodi tanpa data.
        DB::table('dosen_pembimbing')->insert([
            ['id_registrasi_mahasiswa' => 'M1', 'id_bimbing_mahasiswa' => 'M1', 'nim' => 'NIM1', 'nama_mahasiswa' => 'Mhs Satu', 'id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'pembimbing_ke' => '1', 'id_aktivitas' => 'A1', 'jenis_aktivitas' => 'Skripsi'],
        ]);
        DB::table('list_aktivitas_mahasiswa')->insert([
            ['id_aktivitas' => 'A1', 'id_prodi' => 'P1', 'nama_prodi' => 'Teknik Informatika', 'id_semester' => '20251', 'judul' => 'TA 2025', 'status_sync' => 'sudah sync'],
        ]);

        Penugasan::create(['peran' => 'korprodi', 'nip' => '199999999999999001', 'nama' => 'Korprodi P1', 'lingkup' => '55201']);
        Penugasan::create(['peran' => 'korprodi', 'nip' => '199999999999999002', 'nama' => 'Korprodi P2', 'lingkup' => '61101']);
    }

    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        $this->flushSession();

        $this->getJson('/api/sapto/kesiapan')->assertUnauthorized();
    }

    public function test_structure_and_totals_are_consistent(): void
    {
        $response = $this->getJson('/api/sapto/kesiapan?ts=2025')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('ts', 2025)
            ->assertJsonStructure([
                'success', 'ts', 'generated_at', 'last_sync_at',
                'totals' => ['total', 'lengkap', 'sebagian', 'kosong', 'perlu_prodi', 'tidak_wajib', 'terisi', 'percent'],
                'tables' => [['id', 'status', 'rows', 'note']],
            ]);

        $tables = collect($response->json('tables'));
        $totals = $response->json('totals');

        $this->assertSame($tables->count(), $totals['total'] + $totals['perlu_prodi'] + $totals['tidak_wajib']);
        $this->assertSame($tables->where('status', 'tidak_wajib')->count(), $totals['tidak_wajib']);
        $this->assertSame($totals['total'], $totals['lengkap'] + $totals['sebagian'] + $totals['kosong']);
        foreach (self::STATUS as $status) {
            $this->assertSame($tables->where('status', $status)->count(), $totals[$status]);
        }
        $this->assertSame($totals['lengkap'] + $totals['sebagian'], $totals['terisi']);
        $this->assertSame((int) round($totals['terisi'] / $totals['total'] * 100), $totals['percent']);
        $this->assertGreaterThanOrEqual(0, $totals['percent']);
        $this->assertLessThanOrEqual(100, $totals['percent']);

        $this->assertSame($tables->pluck('id')->unique()->count(), $tables->count(), 'id tabel harus unik');
        $this->assertTrue($tables->every(fn (array $t): bool => in_array($t['status'], [...self::STATUS, ...self::DI_LUAR_TOTAL], true)));
        $this->assertTrue($tables->every(fn (array $t): bool => is_int($t['rows']) && $t['rows'] >= 0));
        $this->assertNull($response->json('last_sync_at'));

        // Id memakai id tabel di routes/web.php, bukan slug endpoint.
        $ids = $tables->pluck('id');
        $this->assertTrue($ids->contains('ewmp-dosen-tetap'));
        $this->assertTrue($ids->contains('penelitian-melibatkan-mahasiswa'));
        $this->assertTrue($ids->contains('rekognisi-dosen'));
        $this->assertTrue($ids->contains('mahasiswa-asing'));
        $this->assertTrue($ids->contains('dosen-pembimbing-ta'));
        $this->assertFalse($ids->contains('ewmp-dosen'));
    }

    public function test_seeded_table_is_lengkap_and_rowless_table_is_kosong(): void
    {
        $tables = $this->tabel($this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk());

        $this->assertSame('lengkap', $tables['ipk-lulusan']['status']);
        $this->assertGreaterThan(0, $tables['ipk-lulusan']['rows']);
        $this->assertNull($tables['ipk-lulusan']['note']);
        $this->assertSame(['id' => 'dosen-pembimbing-ta', 'status' => 'lengkap', 'rows' => 1, 'note' => null], $tables['dosen-pembimbing-ta']);

        $this->assertSame('kosong', $tables['mahasiswa-asing']['status']);
        $this->assertSame(0, $tables['mahasiswa-asing']['rows']);
    }

    public function test_rows_with_source_partial_is_sebagian_and_asing_uses_data_envelope(): void
    {
        DB::table('negara')->insert([['id_negara' => 'ID', 'nama_negara' => 'Indonesia'], ['id_negara' => 'MY', 'nama_negara' => 'Malaysia']]);
        DB::table('biodata_mahasiswa')->insert(['id_mahasiswa' => 'MA1', 'nama_mahasiswa' => 'Foreign One', 'id_negara' => 'MY']);
        DB::table('list_riwayat_pendidikan_mahasiswa')->insert([
            'id_mahasiswa' => 'MA1', 'id_prodi' => 'P1', 'nim' => 'NIMASING1', 'nama_mahasiswa' => 'Foreign One',
            'id_periode_masuk' => '20231', 'id_periode_keluar' => null,
        ]);

        $tables = $this->tabel($this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk());

        // Tabel 2.b memakai envelope 'data' tanpa source -> dihitung, dan lengkap.
        $this->assertSame(['id' => 'mahasiswa-asing', 'status' => 'lengkap', 'rows' => 1, 'note' => null], $tables['mahasiswa-asing']);

        // Baris nyata dengan kolom yang sumbernya belum ada (source.partial)
        // -> "sebagian": 8.d.1 punya jumlah lulusan dari seed, kolom tracer kosong.
        $this->assertSame('sebagian', $tables['waktu-tunggu']['status']);
        $this->assertGreaterThan(0, $tables['waktu-tunggu']['rows']);
    }

    public function test_template_tables_without_source_data_are_kosong_not_terisi(): void
    {
        $tables = $this->tabel($this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk());

        // Baris templat (label + nol) tanpa sumber data: dulu dihitung "sebagian".
        foreach (['kepuasan-mahasiswa', 'kepuasan-pengguna', 'penggunaan-dana'] as $id) {
            $this->assertSame('kosong', $tables[$id]['status'], $id);
            $this->assertSame(0, $tables[$id]['rows'], $id);
            $this->assertNotEmpty($tables[$id]['note'], $id);
        }

        // Tabel agregat berbaris tetap yang semua jumlahnya 0.
        $this->assertSame('kosong', $tables['publikasi-mahasiswa']['status']);
    }

    public function test_tables_not_required_for_the_program_level_are_excluded(): void
    {
        // P1 adalah S1: 6.b (Magister/Doktor) dan 3.a.5 (D3/Sarjana Terapan)
        // bukan kewajibannya menurut templat LKPS (config/lkps.php).
        $response = $this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk();
        $tables = $this->tabel($response);

        foreach (['penelitian-rujukan-tema', 'dosen-industri', 'sitasi-mahasiswa', 'pagelaran-mahasiswa'] as $id) {
            $this->assertSame('tidak_wajib', $tables[$id]['status'], $id);
        }
        $this->assertSame('kosong', $tables['integrasi-pembelajaran']['status']);
        $this->assertSame(7, $response->json('totals.tidak_wajib'));
    }

    public function test_placeholder_and_unavailable_tables_are_kosong_with_note(): void
    {
        $tables = $this->tabel($this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk());

        $this->assertSame('kosong', $tables['integrasi-pembelajaran']['status']);
        $this->assertSame(0, $tables['integrasi-pembelajaran']['rows']);
        $this->assertNotEmpty($tables['integrasi-pembelajaran']['note']);
    }

    public function test_per_prodi_tables_are_not_counted_as_empty_outside_a_prodi_scope(): void
    {
        // Admin melihat lingkup universitas: tabel per-prodi (mis. 8.c Masa
        // Studi) meminta pilih prodi dulu. Itu bukan "belum terisi", jadi
        // dikeluarkan dari total supaya persentase tidak menyesatkan.
        $this->flushSession();
        $this->withSession(['sso_user' => ['sub' => 'admin', 'name' => 'Admin', 'nomor_identitas' => 'admin', 'is_admin' => true]]);

        $response = $this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk();
        $tables = $this->tabel($response);
        $totals = $response->json('totals');

        $this->assertSame('perlu_prodi', $tables['masa-studi']['status']);
        $this->assertGreaterThan(0, $totals['perlu_prodi']);
        $this->assertSame(count($tables), $totals['total'] + $totals['perlu_prodi']);
        $this->assertSame($totals['total'], $totals['lengkap'] + $totals['sebagian'] + $totals['kosong']);
    }

    public function test_failing_table_does_not_break_the_response(): void
    {
        $this->mock(SaptoIpkLulusanRepository::class, function ($mock): void {
            $mock->shouldReceive('summary')->andThrow(new \RuntimeException('DB down'));
        });

        $response = $this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk();
        $tables = $this->tabel($response);

        $this->assertSame(['id' => 'ipk-lulusan', 'status' => 'kosong', 'rows' => 0, 'note' => 'Gagal dimuat'], $tables['ipk-lulusan']);
        // Tabel lain tetap dihitung, dan total tetap konsisten.
        $this->assertSame('kosong', $tables['integrasi-pembelajaran']['status']);
        $this->assertNotSame('Gagal dimuat', $tables['integrasi-pembelajaran']['note']);
        $totals = $response->json('totals');
        $this->assertSame($totals['total'], $totals['lengkap'] + $totals['sebagian'] + $totals['kosong']);
    }

    public function test_cache_is_not_shared_between_scopes(): void
    {
        // P1 punya dosen pembimbing TA, P2 tidak. Bila kunci cache lupa memuat scope,
        // korprodi P2 (yang kedua) akan menerima hasil P1 dari cache.
        $p1 = $this->tabel($this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk());
        $this->assertSame('lengkap', $p1['dosen-pembimbing-ta']['status']);

        $this->sebagai('199999999999999002');
        $p2 = $this->tabel($this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk());
        $this->assertSame('kosong', $p2['dosen-pembimbing-ta']['status']);

        // Kembali ke P1: masih hasil P1 (dari cache), bukan hasil P2.
        $this->sebagai('199999999999999001');
        $p1Lagi = $this->tabel($this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk());
        $this->assertSame('lengkap', $p1Lagi['dosen-pembimbing-ta']['status']);
    }

    public function test_result_is_cached_for_same_scope(): void
    {
        $pertama = $this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk()->json('generated_at');

        // Data berubah setelah cache terisi: respons kedua harus tetap yang lama.
        DB::table('list_mahasiswa_lulus_do')->delete();
        $kedua = $this->getJson('/api/sapto/kesiapan?ts=2025')->assertOk();

        $this->assertSame($pertama, $kedua->json('generated_at'));
        $this->assertSame('lengkap', $this->tabel($kedua)['ipk-lulusan']['status']);
    }

    public function test_invalid_ts_is_rejected_with_400(): void
    {
        $this->getJson('/api/sapto/kesiapan?ts=abc')->assertStatus(400);
    }

    public function test_account_without_penugasan_is_forbidden_not_a_wall_of_failures(): void
    {
        $this->sebagai('000000000000000000');

        $this->getJson('/api/sapto/kesiapan?ts=2025')->assertForbidden();
    }

    public function test_last_sync_at_reads_the_latest_finished_sync_batch(): void
    {
        DB::table('job_batches')->insert([
            'id' => 'b1', 'name' => 'pd-unsri-sync', 'total_jobs' => 1, 'pending_jobs' => 0, 'failed_jobs' => 0,
            'failed_job_ids' => '[]', 'created_at' => 1_790_000_000, 'finished_at' => 1_790_000_100,
        ]);

        $this->getJson('/api/sapto/kesiapan?ts=2025')
            ->assertOk()
            ->assertJsonPath('last_sync_at', now()->setTimestamp(1_790_000_100)->toIso8601String());
    }

    public function test_every_summary_route_is_registered_in_the_readiness_list(): void
    {
        $service = file_get_contents(app_path('Services/SaptoKesiapanService.php'));

        $tanpaWakil = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => preg_match('#^api/sapto/tables/[^/]+/summary$#', $route->uri()) === 1)
            ->map(fn ($route): string => class_basename(explode('@', $route->getActionName())[0]))
            ->reject(fn (string $controller): bool => str_contains($service, "Api\\{$controller}::class"))
            ->values()
            ->all();

        $this->assertSame([], $tanpaWakil, 'Tabel baru harus ditambahkan ke SaptoKesiapanService::TABLES.');
    }

    private function sebagai(string $nip): void
    {
        $this->flushSession();
        $this->withSession(['sso_user' => [
            'sub' => $nip, 'name' => 'Test', 'email' => "{$nip}@example.test", 'nomor_identitas' => $nip,
        ]]);
    }

    /**
     * @return array<string, array<string, mixed>> dikunci per id tabel
     */
    private function tabel(\Illuminate\Testing\TestResponse $response): array
    {
        return collect($response->json('tables'))->keyBy('id')->all();
    }
}
