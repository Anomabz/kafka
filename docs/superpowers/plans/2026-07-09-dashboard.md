# Dashboard SAPTO Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a dashboard landing page that summarizes 5 BAN-PT tables (the only 5 out of 46 that have real data) with key metrics + trend sparklines, scoped by the same Role/TS the rest of the app uses, replacing Tabel 3.a.2 as the default page shown on login.

**Architecture:** One new backend endpoint (`GET /api/sapto/dashboard/summary`) backed by a `SaptoDashboardRepository` that composes the 4 existing per-table repositories plus a newly-extracted `SaptoMahasiswaAsingRepository` (pulled out of `MahasiswaController`, which today has raw SQL with no repository). Each of the 5 domains is wrapped in its own try/catch so one failing domain doesn't break the other 4 cards. The frontend adds one new section (`#dashboard-page`) following the exact show/hide pattern already used by `#table-page` / `#mahasiswa-asing-page` / `#placeholder-page` in `sapto.blade.php` — no new routing, no new JS architecture.

**Tech Stack:** Laravel 12 (PHP 8.3), Blade, vanilla JS (no framework, no build-time JS bundling beyond Vite/Tailwind), MySQL in production / SQLite `:memory:` in tests, Tailwind CSS v4.

## Global Constraints

- Every backend change must produce IDENTICAL behavior for existing endpoints (`GET /sapto/mahasiswa-asing`, `GET /sapto/mahasiswa-asing/detail`, and all 4 existing `/api/sapto/tables/*/summary` endpoints) — this plan only *adds* a repository layer under `MahasiswaController`, it does not change its response shape.
- Tests extend `Tests\TestCase` (forces isolated SQLite `:memory:` — see `tests/TestCase.php`) and use `RefreshDatabase` + `Cache::flush()` in `setUp()`, matching `tests/Feature/SaptoNewTablesTest.php` and `tests/Feature/SaptoApiTest.php`.
- Run tests with: `php artisan test --filter=<ClassName>` (confirmed working locally, PHP 8.3.6).
- New repositories follow the existing constructor-injection pattern (no service container bindings needed — Laravel resolves concrete classes automatically, same as `SaptoDosenTetapRepository` etc. today).
- No new frontend dependency (no chart library) — sparklines are a hand-rolled inline SVG polyline, 3 data points.
- Frontend changes must not touch `app.js` (it's a 1-line no-op — all page JS lives inline in `resources/views/sapto.blade.php`, per the existing pattern).

---

## Task 1: Extract `SaptoMahasiswaAsingRepository` from `MahasiswaController`

`MahasiswaController::data_mahasiswa_asing()` currently runs a raw SQL query directly in the controller with no repository, and doesn't support fakultas/prodi filtering server-side (today filtering happens 100% client-side in `sapto.blade.php`'s `maVisibleRows()`). The dashboard needs this domain's numbers scoped server-side like the other 4 domains, so this task extracts the query into a repository (behavior-preserving for the existing route) and adds optional scope filtering.

**Files:**
- Create: `app/Services/SaptoMahasiswaAsingRepository.php`
- Modify: `app/Http/Controllers/Api/Sapto/MahasiswaController.php`
- Test: `tests/Feature/SaptoMahasiswaAsingRepositoryTest.php`

**Interfaces:**
- Produces: `App\Services\SaptoMahasiswaAsingRepository::summary(?string $fakultasId = null, ?string $programStudiId = null): array` returning `{tahun_akademik: {ts_2: string, ts_1: string, ts: string}, total_prodi: int, data: array<int, object>}` where each `data` element is a `stdClass` with properties `id_prodi, kode_program_studi, program_studi, mhs_aktif_ts2, mhs_aktif_ts1, mhs_aktif_ts, asing_ft_ts2, asing_ft_ts1, asing_ft_ts, asing_pt_ts2, asing_pt_ts1, asing_pt_ts, fakultas, fakultas_id` (identical shape to what `MahasiswaController::data_mahasiswa_asing()` returns today). Task 2 consumes this method.

- [ ] **Step 1: Write the failing test for the extracted repository**

Create `tests/Feature/SaptoMahasiswaAsingRepositoryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\SaptoMahasiswaAsingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        Cache::flush();
        $this->seedMahasiswaAsingData();
    }

    public function test_endpoint_still_returns_identical_shape_after_extraction(): void
    {
        $this->getJson('/sapto/mahasiswa-asing')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tahun_akademik.ts_2', '2023')
            ->assertJsonPath('tahun_akademik.ts_1', '2024')
            ->assertJsonPath('tahun_akademik.ts', '2025')
            ->assertJsonPath('total_prodi', 2)
            ->assertJsonCount(2, 'data');
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=SaptoMahasiswaAsingRepositoryTest`
Expected: FAIL — `Class "App\Services\SaptoMahasiswaAsingRepository" not found`

- [ ] **Step 3: Create the repository**

Create `app/Services/SaptoMahasiswaAsingRepository.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tabel 2.b) Mahasiswa Asing (format BAN-PT LKPS). Extracted from
 * MahasiswaController::data_mahasiswa_asing() (2026-07-09) so
 * SaptoDashboardRepository can reuse the same query + faculty mapping
 * instead of duplicating it.
 *
 * Beda dari repository tabel lain: TS TIDAK dibaca dari request — window
 * TS-2/TS-1/TS masih hardcode 2023/2024/2025, sudah keterbatasan yang ada
 * sebelum ekstraksi ini (lihat komentar di data_mahasiswa_asing() lama).
 *
 * "Aktif pada tahun X" = sudah terdaftar (id_periode_masuk <= X) dan belum
 * keluar sebelum tahun X berakhir (id_periode_keluar kosong, atau keluarnya
 * di tahun setelah X).
 */
class SaptoMahasiswaAsingRepository
{
    /**
     * @return array{tahun_akademik: array{ts_2: string, ts_1: string, ts: string}, total_prodi: int, data: array<int, object>}
     */
    public function summary(?string $fakultasId = null, ?string $programStudiId = null): array
    {
        $ts = '2025';
        $ts1 = '2024';
        $ts2 = '2023';

        $aktifTs2 = "SUBSTRING(lrpm.id_periode_masuk, 1, 4) <= '$ts2' AND (lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' OR SUBSTRING(lrpm.id_periode_keluar, 1, 4) > '$ts2')";
        $aktifTs1 = "SUBSTRING(lrpm.id_periode_masuk, 1, 4) <= '$ts1' AND (lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' OR SUBSTRING(lrpm.id_periode_keluar, 1, 4) > '$ts1')";
        $aktifTs = "SUBSTRING(lrpm.id_periode_masuk, 1, 4) <= '$ts'  AND (lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' OR SUBSTRING(lrpm.id_periode_keluar, 1, 4) > '$ts')";

        // Tabel 2.b (format resmi BAN-PT LKPS): "Jumlah Mahasiswa Aktif" adalah
        // TOTAL mahasiswa aktif SEMUA kewarganegaraan (basis/pembagi), BUKAN
        // hanya mahasiswa asing. Hanya kolom "Mahasiswa Asing Penuh Waktu/Paruh
        // Waktu" di bawah yang dibatasi ke kewarganegaraan asing.
        $asing = "n.id_negara IS NOT NULL AND n.id_negara <> 'ID'";

        $data = DB::select("
            SELECT
                ps.id_prodi,
                ps.kode_program_studi,
                CONCAT(ps.nama_jenjang_pendidikan, ' ', ps.nama_program_studi) AS program_studi,
                COALESCE(SUM(CASE WHEN $aktifTs2 THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts2,
                COALESCE(SUM(CASE WHEN $aktifTs1 THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts1,
                COALESCE(SUM(CASE WHEN $aktifTs  THEN 1 ELSE 0 END), 0) AS mhs_aktif_ts,
                COALESCE(SUM(CASE WHEN $aktifTs2 AND $asing THEN 1 ELSE 0 END), 0) AS asing_ft_ts2,
                COALESCE(SUM(CASE WHEN $aktifTs1 AND $asing THEN 1 ELSE 0 END), 0) AS asing_ft_ts1,
                COALESCE(SUM(CASE WHEN $aktifTs  AND $asing THEN 1 ELSE 0 END), 0) AS asing_ft_ts,
                0 AS asing_pt_ts2,
                0 AS asing_pt_ts1,
                0 AS asing_pt_ts
            FROM program_studi ps
            LEFT JOIN list_riwayat_pendidikan_mahasiswa lrpm
                ON lrpm.id_prodi = ps.id_prodi
            LEFT JOIN biodata_mahasiswa bm
                ON bm.id_mahasiswa = lrpm.id_mahasiswa
            LEFT JOIN negara n
                ON n.id_negara = bm.id_negara
            WHERE ps.nama_program_studi IS NOT NULL
            GROUP BY
                ps.id_prodi,
                ps.kode_program_studi,
                ps.nama_program_studi,
                ps.nama_jenjang_pendidikan
            HAVING (asing_ft_ts2 + asing_ft_ts1 + asing_ft_ts) > 0
            ORDER BY
                ps.nama_program_studi ASC
        ");

        $facultyMap = $this->facultyByProdiCode();

        $rows = array_map(function (object $row) use ($facultyMap): object {
            $faculty = $facultyMap[$row->kode_program_studi] ?? 'Belum Dipetakan';
            $row->fakultas = $faculty;
            $row->fakultas_id = Str::slug($faculty) ?: 'belum-dipetakan';

            return $row;
        }, $data);

        // Scope fakultas/prodi: sama seperti maVisibleRows() di klien
        // (resources/views/sapto.blade.php), dipindah ke sini supaya
        // SaptoDashboardRepository bisa discope tanpa fetch-semua-lalu-
        // filter-di-JS. Endpoint /sapto/mahasiswa-asing yang sudah ada tetap
        // memanggil summary() tanpa argumen (null, null = tidak difilter),
        // jadi perilakunya tidak berubah.
        $rows = array_values(array_filter($rows, function (object $row) use ($fakultasId, $programStudiId): bool {
            if ($fakultasId !== null && $row->fakultas_id !== $fakultasId) {
                return false;
            }

            if ($programStudiId !== null && (string) $row->id_prodi !== $programStudiId) {
                return false;
            }

            return true;
        }));

        return [
            'tahun_akademik' => ['ts_2' => $ts2, 'ts_1' => $ts1, 'ts' => $ts],
            'total_prodi' => count($rows),
            'data' => $rows,
        ];
    }

    /**
     * Peta kode_program_studi -> nama fakultas, dibaca dari
     * database/data/program_studi.csv (satu-satunya sumber pemetaan
     * fakultas yang tersedia lokal; tabel program_studi tidak menyimpannya).
     *
     * @return array<string, string>
     */
    private function facultyByProdiCode(): array
    {
        $path = database_path('data/program_studi.csv');

        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '') ?: [];
        $map = [];

        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($headers) !== count($values)) {
                continue;
            }

            $record = array_combine($headers, $values);
            $code = trim((string) ($record['kode'] ?? ''));

            if ($code === '') {
                continue;
            }

            $map[$code] = $this->normalizeFaculty((string) ($record['fakultas'] ?? ''));
        }

        fclose($handle);

        return $map;
    }

    /**
     * Rapikan nama fakultas mentah dari CSV menjadi label yang konsisten,
     * mis. "KEDOKTERANM" -> "Fakultas Kedokteran", "PASCASARJANA" ->
     * "Pascasarjana". Selaras dengan PangkalanDataApiClient::normalizeFaculty().
     */
    private function normalizeFaculty(string $faculty): string
    {
        $faculty = trim((string) preg_replace('/\s+/', ' ', Str::upper(Str::ascii($faculty))));

        if ($faculty === 'KEDOKTERANM') {
            $faculty = 'KEDOKTERAN';
        }

        if ($faculty === '') {
            return 'Belum Dipetakan';
        }

        if ($faculty === 'PASCASARJANA') {
            return 'Pascasarjana';
        }

        if (str_starts_with($faculty, 'FAKULTAS ')) {
            $faculty = trim(substr($faculty, 9));
        }

        return 'Fakultas '.Str::title(Str::lower($faculty));
    }
}
```

- [ ] **Step 4: Update `MahasiswaController` to delegate to the repository**

Modify `app/Http/Controllers/Api/Sapto/MahasiswaController.php`. Replace the entire file:

```php
<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Http\Controllers\Controller;
use App\Services\SaptoMahasiswaAsingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MahasiswaController extends Controller
{
    /**
     * FUNGSI 1: Rekapitulasi Matriks Angka (Sesuai 11 Kolom Borang Excel BAN-PT)
     * URL: http://10.8.5.67:8001/sapto/mahasiswa-asing
     */
    public function data_mahasiswa_asing(Request $request, SaptoMahasiswaAsingRepository $repository)
    {
        $summary = $repository->summary();

        return response()->json([
            'success' => true,
            'tahun_akademik' => $summary['tahun_akademik'],
            'total_prodi' => $summary['total_prodi'],
            'data' => $summary['data'],
        ]);
    }

    /**
     * FUNGSI 2: Detail List Mahasiswa Pas Diklik (Mendukung filter id_prodi ATAU nama_prodi)
     * URL Contoh 1: http://10.8.5.67:8001/sapto/mahasiswa-asing/detail?id_prodi=UUID_DISINI
     * URL Contoh 2: http://10.8.5.67:8001/sapto/mahasiswa-asing/detail?nama_prodi=Administrasi Publik&tahun=2025
     */
    public function detail_mahasiswa_asing(Request $request)
    {
        $idProdi   = $request->query('id_prodi');   // Menangkap parameter ID (UUID)
        $namaProdi = $request->query('nama_prodi'); // Menangkap parameter Nama Prodi
        $tahun     = $request->query('tahun');      // Menangkap parameter Tahun Angkatan (Opsional)

        // Validasi awal: Harus mengirimkan salah satu parameter identitas prodi
        if (!$idProdi && !$namaProdi) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal, salah satu parameter id_prodi atau nama_prodi wajib dikirim!'
            ], 400);
        }

        $query = "
            SELECT
                lrpm.nim,
                lrpm.nama_mahasiswa AS nama,
                CONCAT(ps.nama_jenjang_pendidikan, ' ', ps.nama_program_studi) AS program_studi,
                SUBSTRING(lrpm.id_periode_masuk, 1, 4) AS angkatan,
                CASE
                    WHEN lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' THEN 'Aktif'
                    ELSE COALESCE(lrpm.keterangan_keluar, 'Keluar')
                END AS status,
                IFNULL(n.nama_negara, 'Belum Diisi') AS kewarganegaraan
            FROM list_riwayat_pendidikan_mahasiswa lrpm
            LEFT JOIN program_studi ps ON ps.id_prodi = lrpm.id_prodi
            LEFT JOIN biodata_mahasiswa bm ON bm.id_mahasiswa = lrpm.id_mahasiswa
            LEFT JOIN negara n ON n.id_negara = bm.id_negara
            WHERE 1=1
        ";

        $params = [];

        // Pilihan Filter 1: Jika menggunakan ID Prodi (UUID)
        if ($idProdi) {
            $query .= " AND lrpm.id_prodi = ? ";
            $params[] = $idProdi;
        }
        // Pilihan Filter 2: Jika menggunakan Nama Prodi (Menggunakan klausa LIKE agar fleksibel)
        elseif ($namaProdi) {
            $query .= " AND CONCAT(ps.nama_jenjang_pendidikan, ' ', ps.nama_program_studi) LIKE ? ";
            $params[] = '%' . $namaProdi . '%';
        }

        // Spesifik per tahun TS / TS-1 / TS-2 yang diklik. "Aktif pada tahun X"
        // harus sama persis dengan logika di data_mahasiswa_asing() supaya
        // jumlah baris di sini selalu cocok dengan angka yang diklik: sudah
        // terdaftar sebelum/di tahun X, dan belum keluar sebelum tahun X
        // berakhir. Tidak dibatasi ke angkatan 2023-2025 seperti sebelumnya,
        // karena mahasiswa angkatan lama yang masih aktif di tahun X tetap
        // harus muncul.
        if ($tahun) {
            $query .= " AND SUBSTRING(lrpm.id_periode_masuk, 1, 4) <= ? AND (lrpm.id_periode_keluar IS NULL OR lrpm.id_periode_keluar = '' OR SUBSTRING(lrpm.id_periode_keluar, 1, 4) > ?) ";
            $params[] = $tahun;
            $params[] = $tahun;
        }

        /* * NOTE UNTUK TESTING LOKAL:
         * Aturan pembimbing mewajibkan penyaringan mahasiswa asing murni mumpuni lewat ID Negara:
         * "AND n.id_negara IS NOT NULL AND n.id_negara <> 'ID'"
         * * Jika saat ini data tabel biodata_mahasiswa lu masih kosong dan lu ingin menguji kelancaran 
         * visual data mahasiswa lokal Indonesia terlebih dahulu, silakan beri tanda komentar (--) 
         * pada dua baris filter negara di bawah ini.
         */
        $query .= " AND n.id_negara IS NOT NULL ";
        $query .= " AND n.id_negara <> 'ID' ";

        $query .= " ORDER BY lrpm.id_periode_masuk DESC, lrpm.nama_mahasiswa ASC ";

        $data = DB::select($query, $params);

        return response()->json([
            'success' => true,
            'total_mahasiswa' => count($data),
            'filter_digunakan' => [
                'id_prodi' => $idProdi,
                'nama_prodi' => $namaProdi,
                'tahun' => $tahun
            ],
            'data' => $data
        ]);
    }
}
```

Note what changed vs. the original: `data_mahasiswa_asing()` now takes `SaptoMahasiswaAsingRepository $repository` (Laravel injects it automatically, same pattern as `DosenTetapController::summary(Request $request, SaptoDosenTetapRepository $repository)`) and delegates instead of running the query inline. `detail_mahasiswa_asing()` and the two private helper methods (`facultyByProdiCode()`, `normalizeFaculty()`) are removed from the controller — the private helpers moved into the repository in Step 3, and `detail_mahasiswa_asing()` is untouched/copy-pasted verbatim (out of scope for this plan — the dashboard doesn't need per-student detail).

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=SaptoMahasiswaAsingRepositoryTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS (all existing tests still green — this step only refactored where the query lives, not what it computes)

- [ ] **Step 7: Commit**

```bash
git add app/Services/SaptoMahasiswaAsingRepository.php app/Http/Controllers/Api/Sapto/MahasiswaController.php tests/Feature/SaptoMahasiswaAsingRepositoryTest.php
git commit -m "refactor(sapto): extract SaptoMahasiswaAsingRepository from MahasiswaController

Pulls the raw-SQL mahasiswa asing query out of the controller into a
repository (matching the pattern every other SAPTO table already uses),
and adds optional fakultas/prodi scoping needed by the dashboard summary.
The existing /sapto/mahasiswa-asing endpoint is behavior-identical.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 2: `SaptoDashboardRepository` + `DashboardController` + route

Composes the 4 existing per-table repositories plus the newly-extracted `SaptoMahasiswaAsingRepository` into a single `GET /api/sapto/dashboard/summary` response with 5 cards, each independently error-isolated.

**Files:**
- Create: `app/Services/SaptoDashboardRepository.php`
- Create: `app/Http/Controllers/Api/Sapto/DashboardController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/SaptoDashboardTest.php`

**Interfaces:**
- Consumes: `SaptoDosenTetapRepository::summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array` (returns `metrics` key), `SaptoDosenPembimbingTaRepository::summary(...): array` (returns `totals` + `year_columns` keys, NOT `metrics`), `SaptoIpkLulusanRepository::summary(...): array` (returns `metrics` + `rows[].ipk_rata`), `SaptoMasaStudiRepository::summary(...): array` (returns `metrics` + `rows[].masa_studi_rata`, or `notice` set + empty `rows`/`metrics` when no program studi is selected), `SaptoMahasiswaAsingRepository::summary(?string $fakultasId, ?string $programStudiId): array` (from Task 1, no `$ts` param).
- Produces: `App\Services\SaptoDashboardRepository::summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array{ts: int|null, cards: array<int, array{id: string, table_code: string, title: string, error: bool, notice: ?string, metrics: array<int, array{label: string, value: mixed}>, trend: ?array{labels: array<int,string>, values: array<int,float>}}>}`. Task 3 (frontend) consumes this shape via `GET /api/sapto/dashboard/summary`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/SaptoDashboardTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\SaptoIpkLulusanRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/sapto/dashboard/summary — komposisi 5 kartu dari 5 repository yang
 * sudah ada. Fokus tes ini adalah orkestrasi (urutan kartu, isolasi galat per
 * domain, passthrough notice, scoping), BUKAN mengetes ulang perhitungan tiap
 * domain — itu sudah dites di SaptoNewTablesTest/SaptoApiTest/
 * SaptoMahasiswaAsingRepositoryTest.
 */
class SaptoDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seedAllFiveDomains();
    }

    public function test_dashboard_returns_five_cards_in_order(): void
    {
        $response = $this->withHeaders([
            'X-SAPTO-Role' => 'prodi',
            'X-SAPTO-Program-Studi-Id' => 'P1',
        ])->getJson('/api/sapto/dashboard/summary?ts=2025')
            ->assertOk();

        $ids = collect($response->json('cards'))->pluck('id')->all();

        $this->assertSame([
            'dosen-tetap-pt',
            'dosen-pembimbing-ta',
            'ipk-lulusan',
            'masa-studi',
            'mahasiswa-asing',
        ], $ids);
    }

    public function test_dosen_pembimbing_card_has_metrics_and_three_point_trend(): void
    {
        $response = $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/dashboard/summary?ts=2025')
            ->assertOk();

        $card = collect($response->json('cards'))->firstWhere('id', 'dosen-pembimbing-ta');

        $this->assertFalse($card['error']);
        $this->assertNull($card['notice']);
        $this->assertSame('Total dosen pembimbing', $card['metrics'][0]['label']);
        $this->assertSame(1, $card['metrics'][0]['value']); // D1 only
        $this->assertCount(3, $card['trend']['labels']);
        $this->assertCount(3, $card['trend']['values']);
        $this->assertSame(['2023', '2024', '2025'], $card['trend']['labels']);
    }

    public function test_masa_studi_card_shows_notice_without_program_studi_then_real_data_when_selected(): void
    {
        $withoutProdi = $this->withHeader('X-SAPTO-Role', 'universitas')
            ->getJson('/api/sapto/dashboard/summary?ts=2025')
            ->assertOk();

        $card = collect($withoutProdi->json('cards'))->firstWhere('id', 'masa-studi');
        $this->assertNotNull($card['notice']);
        $this->assertStringContainsString('program studi', $card['notice']);
        $this->assertSame([], $card['metrics']);

        $withProdi = $this->withHeaders([
            'X-SAPTO-Role' => 'prodi',
            'X-SAPTO-Program-Studi-Id' => 'P1',
        ])->getJson('/api/sapto/dashboard/summary?ts=2025')
            ->assertOk();

        $cardWithProdi = collect($withProdi->json('cards'))->firstWhere('id', 'masa-studi');
        $this->assertNull($cardWithProdi['notice']);
        $this->assertNotEmpty($cardWithProdi['metrics']);
    }

    public function test_one_domain_failure_does_not_break_other_cards(): void
    {
        $this->mock(SaptoIpkLulusanRepository::class, function ($mock): void {
            $mock->shouldReceive('summary')->andThrow(new \RuntimeException('DB down'));
        });

        $response = $this->withHeaders([
            'X-SAPTO-Role' => 'prodi',
            'X-SAPTO-Program-Studi-Id' => 'P1',
        ])->getJson('/api/sapto/dashboard/summary?ts=2025')
            ->assertOk();

        $cards = collect($response->json('cards'))->keyBy('id');

        $this->assertTrue($cards['ipk-lulusan']['error']);
        $this->assertSame([], $cards['ipk-lulusan']['metrics']);

        // Domain lain tetap normal.
        $this->assertFalse($cards['dosen-tetap-pt']['error']);
        $this->assertFalse($cards['dosen-pembimbing-ta']['error']);
        $this->assertFalse($cards['mahasiswa-asing']['error']);
    }

    private function seedAllFiveDomains(): void
    {
        DB::table('program_studi')->insert([
            ['id_prodi' => 'P1', 'kode_program_studi' => '55201', 'nama_program_studi' => 'Teknik Informatika', 'status' => 'AKTIF', 'id_jenjang_pendidikan' => 1, 'nama_jenjang_pendidikan' => 'S1'],
        ]);

        // Dosen Tetap PT (3.a.1)
        DB::table('list_penugasan_dosen')->insert([
            ['id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'jk' => 'P', 'id_prodi' => 'P1', 'nama_program_studi' => 'Teknik Informatika', 'a_sp_homebase' => '1', 'nama_tahun_ajaran' => '2025/2026'],
        ]);

        // Dosen Pembimbing TA (3.a.2) — satu mahasiswa per tahun TS-2/TS-1/TS.
        DB::table('dosen_pembimbing')->insert([
            ['id_registrasi_mahasiswa' => 'M1', 'id_bimbing_mahasiswa' => 'M1', 'nim' => 'NIM1', 'nama_mahasiswa' => 'Mhs Satu', 'id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'pembimbing_ke' => '1', 'id_aktivitas' => 'A1', 'jenis_aktivitas' => 'Skripsi'],
            ['id_registrasi_mahasiswa' => 'M2', 'id_bimbing_mahasiswa' => 'M2', 'nim' => 'NIM2', 'nama_mahasiswa' => 'Mhs Dua', 'id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'pembimbing_ke' => '1', 'id_aktivitas' => 'A2', 'jenis_aktivitas' => 'Skripsi'],
            ['id_registrasi_mahasiswa' => 'M3', 'id_bimbing_mahasiswa' => 'M3', 'nim' => 'NIM3', 'nama_mahasiswa' => 'Mhs Tiga', 'id_dosen' => 'D1', 'nama_dosen' => 'Dr. Ana', 'nidn' => '111', 'pembimbing_ke' => '1', 'id_aktivitas' => 'A3', 'jenis_aktivitas' => 'Skripsi'],
        ]);
        DB::table('list_aktivitas_mahasiswa')->insert([
            ['id_aktivitas' => 'A1', 'id_prodi' => 'P1', 'nama_prodi' => 'Teknik Informatika', 'id_semester' => '20231', 'judul' => 'TA 2023', 'status_sync' => 'sudah sync'],
            ['id_aktivitas' => 'A2', 'id_prodi' => 'P1', 'nama_prodi' => 'Teknik Informatika', 'id_semester' => '20241', 'judul' => 'TA 2024', 'status_sync' => 'sudah sync'],
            ['id_aktivitas' => 'A3', 'id_prodi' => 'P1', 'nama_prodi' => 'Teknik Informatika', 'id_semester' => '20251', 'judul' => 'TA 2025', 'status_sync' => 'sudah sync'],
        ]);

        // IPK Lulusan (8.a) + Masa Studi (8.c) share list_mahasiswa_lulus_do.
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
        DB::table('list_riwayat_pendidikan_mahasiswa')->insert([
            'id_prodi' => 'P1',
            'nama_jenis_daftar' => 'Peserta didik baru',
            'id_periode_masuk' => '20211',
        ]);

        // Mahasiswa Asing (2.b)
        DB::table('negara')->insert(['id_negara' => 'ID', 'nama_negara' => 'Indonesia']);
        DB::table('negara')->insert(['id_negara' => 'MY', 'nama_negara' => 'Malaysia']);
        DB::table('biodata_mahasiswa')->insert(['id_mahasiswa' => 'MA1', 'nama_mahasiswa' => 'Foreign One', 'id_negara' => 'MY']);
        DB::table('list_riwayat_pendidikan_mahasiswa')->insert([
            'id_mahasiswa' => 'MA1',
            'id_prodi' => 'P1',
            'nim' => 'NIMASING1',
            'nama_mahasiswa' => 'Foreign One',
            'id_periode_masuk' => '20231',
            'id_periode_keluar' => null,
        ]);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=SaptoDashboardTest`
Expected: FAIL — route `/api/sapto/dashboard/summary` doesn't exist (404) / `SaptoDashboardRepository` not found

- [ ] **Step 3: Create `SaptoDashboardRepository`**

Create `app/Services/SaptoDashboardRepository.php`:

```php
<?php

namespace App\Services;

use Throwable;

/**
 * Ringkasan lintas 5 tabel BAN-PT yang sudah punya data nyata (dari 46 total),
 * untuk landing page dashboard. Tiap kartu memanggil repository tabel yang
 * sudah ada (satu sumber kebenaran per domain, tidak menduplikasi hitungan)
 * dan membungkusnya jadi {id, table_code, title, metrics, trend, notice,
 * error}. Kegagalan satu domain (exception repository) tidak menjatuhkan
 * kartu lain — kartu itu saja dapat error:true, metrics/trend kosong.
 *
 * Mahasiswa Asing beda dari 4 domain lain: tidak menerima $ts (backend-nya
 * hardcode TS-2/TS-1/TS 2023/2024/2025 — lihat SaptoMahasiswaAsingRepository).
 */
class SaptoDashboardRepository
{
    public function __construct(
        private readonly SaptoDosenTetapRepository $dosenTetap,
        private readonly SaptoDosenPembimbingTaRepository $dosenPembimbingTa,
        private readonly SaptoIpkLulusanRepository $ipkLulusan,
        private readonly SaptoMasaStudiRepository $masaStudi,
        private readonly SaptoMahasiswaAsingRepository $mahasiswaAsing,
    ) {}

    /**
     * @return array{ts: int|null, cards: array<int, array<string, mixed>>}
     */
    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        return [
            'ts' => $ts,
            'cards' => [
                $this->dosenTetapCard($ts, $fakultasId, $programStudiId),
                $this->dosenPembimbingTaCard($ts, $fakultasId, $programStudiId),
                $this->ipkLulusanCard($ts, $fakultasId, $programStudiId),
                $this->masaStudiCard($ts, $fakultasId, $programStudiId),
                $this->mahasiswaAsingCard($fakultasId, $programStudiId),
            ],
        ];
    }

    private function dosenTetapCard(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        return $this->safeCard('dosen-tetap-pt', '3.a.1', 'Dosen Tetap Perguruan Tinggi', function () use ($ts, $fakultasId, $programStudiId): array {
            $summary = $this->dosenTetap->summary($ts, $fakultasId, $programStudiId);
            $byLabel = collect($summary['metrics'])->pluck('value', 'label');

            return [
                'metrics' => [
                    ['label' => 'Total dosen tetap', 'value' => $byLabel['Total dosen tetap'] ?? 0],
                    ['label' => 'Laki-laki', 'value' => $byLabel['Laki-laki'] ?? 0],
                    ['label' => 'Perempuan', 'value' => $byLabel['Perempuan'] ?? 0],
                ],
                'trend' => null,
                'notice' => null,
            ];
        });
    }

    private function dosenPembimbingTaCard(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        return $this->safeCard('dosen-pembimbing-ta', '3.a.2', 'Dosen Pembimbing Utama Tugas Akhir', function () use ($ts, $fakultasId, $programStudiId): array {
            $summary = $this->dosenPembimbingTa->summary($ts, $fakultasId, $programStudiId);
            $totals = $summary['totals'];
            $years = $summary['year_columns'];

            return [
                'metrics' => [
                    ['label' => 'Total dosen pembimbing', 'value' => count($summary['rows'])],
                    ['label' => 'Mhs bimbingan PS diakreditasi (TS)', 'value' => $totals['ps_diakreditasi']['ts']],
                    ['label' => 'Mhs bimbingan PS lain di PT (TS)', 'value' => $totals['ps_lain_pt']['ts']],
                ],
                'trend' => [
                    'labels' => [(string) $years['ts_2'], (string) $years['ts_1'], (string) $years['ts']],
                    'values' => [
                        (float) $totals['ps_diakreditasi']['ts_2'],
                        (float) $totals['ps_diakreditasi']['ts_1'],
                        (float) $totals['ps_diakreditasi']['ts'],
                    ],
                ],
                'notice' => null,
            ];
        });
    }

    private function ipkLulusanCard(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        return $this->safeCard('ipk-lulusan', '8.a', 'IPK Lulusan', function () use ($ts, $fakultasId, $programStudiId): array {
            $summary = $this->ipkLulusan->summary($ts, $fakultasId, $programStudiId);
            $byLabel = collect($summary['metrics'])->pluck('value', 'label');
            $rows = collect($summary['rows']);

            return [
                'metrics' => [
                    ['label' => 'Total lulusan (TS-2..TS)', 'value' => $byLabel['Total lulusan (TS-2..TS)'] ?? 0],
                    ['label' => 'Jumlah lulusan (TS)', 'value' => $byLabel['Jumlah lulusan (TS)'] ?? 0],
                    ['label' => 'IPK rata-rata (TS)', 'value' => $byLabel['IPK rata-rata (TS)'] ?? 0],
                ],
                'trend' => [
                    'labels' => $rows->map(fn (array $row): string => explode(' ', (string) $row['tahun_lulus'])[0])->all(),
                    'values' => $rows->map(fn (array $row): float => $this->trendPoint($row['ipk_rata']))->all(),
                ],
                'notice' => null,
            ];
        });
    }

    private function masaStudiCard(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        return $this->safeCard('masa-studi', '8.c', 'Masa Studi Lulusan', function () use ($ts, $fakultasId, $programStudiId): array {
            $summary = $this->masaStudi->summary($ts, $fakultasId, $programStudiId);

            if ($summary['notice'] !== null) {
                return ['metrics' => [], 'trend' => null, 'notice' => $summary['notice']];
            }

            $byLabel = collect($summary['metrics'])->pluck('value', 'label');
            $recentRows = collect($summary['rows'])->slice(-3)->values();

            return [
                'metrics' => [
                    ['label' => 'Rata-rata masa studi (thn)', 'value' => $byLabel['Rata-rata masa studi (thn)'] ?? 0],
                    ['label' => 'Total lulusan (kohort)', 'value' => $byLabel['Total lulusan (kohort)'] ?? 0],
                    ['label' => 'Total diterima (kohort)', 'value' => $byLabel['Total diterima (kohort)'] ?? 0],
                ],
                'trend' => [
                    'labels' => $recentRows->map(fn (array $row): string => explode(' ', (string) $row['tahun_masuk'])[0])->all(),
                    'values' => $recentRows->map(fn (array $row): float => $this->trendPoint($row['masa_studi_rata']))->all(),
                ],
                'notice' => null,
            ];
        });
    }

    private function mahasiswaAsingCard(?string $fakultasId, ?string $programStudiId): array
    {
        return $this->safeCard('mahasiswa-asing', '2.b', 'Mahasiswa Asing', function () use ($fakultasId, $programStudiId): array {
            $summary = $this->mahasiswaAsing->summary($fakultasId, $programStudiId);
            $rows = $summary['data'];

            $ft = array_sum(array_map(fn (object $row): int => (int) $row->asing_ft_ts, $rows));
            $ft1 = array_sum(array_map(fn (object $row): int => (int) $row->asing_ft_ts1, $rows));
            $ft2 = array_sum(array_map(fn (object $row): int => (int) $row->asing_ft_ts2, $rows));
            $pt = array_sum(array_map(fn (object $row): int => (int) $row->asing_pt_ts, $rows));

            return [
                'metrics' => [
                    ['label' => 'Total program studi', 'value' => count($rows)],
                    ['label' => 'Mahasiswa asing aktif (TS)', 'value' => $ft + $pt],
                    ['label' => 'Penuh waktu (TS)', 'value' => $ft],
                ],
                'trend' => [
                    'labels' => [
                        $summary['tahun_akademik']['ts_2'],
                        $summary['tahun_akademik']['ts_1'],
                        $summary['tahun_akademik']['ts'],
                    ],
                    'values' => [(float) $ft2, (float) $ft1, (float) $ft],
                ],
                'notice' => null,
            ];
        });
    }

    /**
     * @param  callable(): array{metrics: array, trend: ?array, notice: ?string}  $resolve
     */
    private function safeCard(string $id, string $tableCode, string $title, callable $resolve): array
    {
        $base = ['id' => $id, 'table_code' => $tableCode, 'title' => $title, 'error' => false];

        try {
            return array_merge($base, $resolve());
        } catch (Throwable) {
            return array_merge($base, ['metrics' => [], 'trend' => null, 'notice' => null, 'error' => true]);
        }
    }

    private function trendPoint(int|float|null $value): float
    {
        return $value ?? 0.0;
    }
}
```

- [ ] **Step 4: Create `DashboardController`**

Create `app/Http/Controllers/Api/Sapto/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Services\SaptoDashboardRepository;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends SaptoTableController
{
    public function summary(Request $request, SaptoDashboardRepository $repository): JsonResponse
    {
        $ts = $this->validateTs($request);
        $context = SaptoUserContext::fromRequest($request);

        return response()->json($repository->summary(
            $ts,
            $context->resolveFakultasId($request),
            $context->resolveProgramStudiId($request),
        ));
    }
}
```

- [ ] **Step 5: Add the route**

Modify `routes/api.php`. Add the import and the route inside the existing group:

```php
<?php

use App\Http\Controllers\Api\Sapto\DashboardController;
use App\Http\Controllers\Api\Sapto\DosenPembimbingTaController;
use App\Http\Controllers\Api\Sapto\DosenTetapController;
use App\Http\Controllers\Api\Sapto\FakultasController;
use App\Http\Controllers\Api\Sapto\IpkLulusanController;
use App\Http\Controllers\Api\Sapto\MasaStudiController;
use App\Http\Controllers\Api\Sapto\ProgramStudiController;
use App\Http\Controllers\Api\Sapto\MahasiswaController;
use Illuminate\Support\Facades\Route;

// 'web' adds the session/cookie stack so the SSO login (session-based) is
// visible here; 'sso.auth' rejects unauthenticated calls with 401.
Route::middleware(['web', 'sso.auth'])->prefix('sapto')->group(function (): void {
    Route::get('/fakultas', FakultasController::class);
    Route::get('/program-studi', ProgramStudiController::class);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    Route::prefix('tables/dosen-pembimbing-ta')->group(function (): void {
        Route::get('/summary', [DosenPembimbingTaController::class, 'summary']);
        Route::get('/detail', [DosenPembimbingTaController::class, 'detail']);
    });

    Route::get('/tables/dosen-tetap-pt/summary', [DosenTetapController::class, 'summary']);
    Route::get('/tables/ipk-lulusan/summary', [IpkLulusanController::class, 'summary']);
    Route::get('/tables/masa-studi/summary', [MasaStudiController::class, 'summary']);
});
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=SaptoDashboardTest`
Expected: PASS (4 tests)

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: PASS (all tests, no regressions)

- [ ] **Step 8: Commit**

```bash
git add app/Services/SaptoDashboardRepository.php app/Http/Controllers/Api/Sapto/DashboardController.php routes/api.php tests/Feature/SaptoDashboardTest.php
git commit -m "feat(sapto): add GET /api/sapto/dashboard/summary

Composes the 5 BAN-PT tables that have real data into one summary
endpoint for the new dashboard landing page. Each domain is wrapped in
its own try/catch so one repository failing doesn't break the other
4 cards.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 3: Frontend — dashboard section, sidebar link, becomes the default page

Adds `#dashboard-page` following the exact same show/hide pattern as `#table-page`/`#mahasiswa-asing-page`/`#placeholder-page`, a pinned "Dashboard" sidebar link, and makes it the page shown on first load instead of Tabel 3.a.2.

**Files:**
- Create: `resources/views/components/dashboard-page.blade.php`
- Modify: `resources/views/components/sidebar.blade.php`
- Modify: `resources/views/sapto.blade.php`
- Modify: `resources/views/layouts/sapto.blade.php`

**Interfaces:**
- Consumes: `GET /api/sapto/dashboard/summary` (Task 2) returning `{ts, cards: [{id, table_code, title, error, notice, metrics: [{label, value}], trend: {labels, values}|null}]}`.
- Consumes existing globals from `sapto.blade.php`: `state.{role,ts,fakultasId,programStudiId}`, `fetchJson()`, `formatNumber()`, `escapeHtml()`, `activateTable()`, `contextHeaders()`.
- Produces: `TABLE_HANDLERS['dashboard']`, `loadDashboard()`, `renderDashboard()`, `renderDashboardCard()`, `dashboardSparkline()`, `renderDashboardError()`, `dashboardUrl()`, `setDashboardLoading()` — used only within this file, no other task depends on their names.

This task has no automated test (no JS test runner in this project — `package.json` only has `vite`/`vite build`, confirmed in Global Constraints). Verification is manual via the running dev container, described in Step 8.

- [ ] **Step 1: Create the dashboard page Blade partial**

Create `resources/views/components/dashboard-page.blade.php`:

```blade
{{--
    resources/views/components/dashboard-page.blade.php

    Landing page: ringkasan 5 tabel BAN-PT yang sudah punya data nyata (dari
    46 total). Data diisi oleh loadDashboard()/renderDashboard() di
    sapto.blade.php dari GET /api/sapto/dashboard/summary. Urutan array ini
    cuma menentukan urutan render di markup — JS mencocokkan tiap kartu ke
    payload lewat data-card-id, bukan index, jadi urutan tidak perlu sama
    persis dengan urutan "cards" di response.
--}}
@php
    $dashboardCards = [
        ['id' => 'dosen-tetap-pt', 'tableId' => 'dosen-tetap-pt'],
        ['id' => 'dosen-pembimbing-ta', 'tableId' => 'dosen-pembimbing-ta'],
        ['id' => 'ipk-lulusan', 'tableId' => 'ipk-lulusan'],
        ['id' => 'masa-studi', 'tableId' => 'masa-studi'],
        ['id' => 'mahasiswa-asing', 'tableId' => 'mahasiswa-asing'],
    ];
@endphp

<section id="dashboard-page">
    @include('components.filter-bar')

    <div id="dashboard-status" class="mb-3 text-[12px] text-muted sm:mb-4"></div>

    <div class="grid grid-cols-1 gap-3 sm:gap-4 lg:grid-cols-2">
        @foreach ($dashboardCards as $card)
            <button
                type="button"
                class="dashboard-card glass glass-hover rounded-2xl p-4 text-left"
                data-card-id="{{ $card['id'] }}"
                data-table-id="{{ $card['tableId'] }}"
            >
                <div class="flex items-start justify-between gap-2">
                    <h3 class="dashboard-card-title text-[14px] font-bold text-ink"></h3>
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M7 17 17 7M7 7h10v10" />
                    </svg>
                </div>
                <div class="dashboard-card-metrics mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3"></div>
                <div class="dashboard-card-trend mt-3"></div>
            </button>
        @endforeach
    </div>
</section>
```

- [ ] **Step 2: Add the "Dashboard" sidebar link**

Modify `resources/views/components/sidebar.blade.php`. Change:

```blade
    <nav id="menu" class="menu flex-1 space-y-4 overflow-y-auto px-3 py-3" aria-label="Menu tabel SAPTO">
        @foreach ($groups as $groupKey => $items)
```

to:

```blade
    <nav id="menu" class="menu flex-1 space-y-4 overflow-y-auto px-3 py-3" aria-label="Menu tabel SAPTO">
        <div class="space-y-0.5">
            <button
                type="button"
                data-table-id="dashboard"
                class="menu-item {{ ($activeTableId ?? 'dashboard') === 'dashboard' ? 'is-active' : '' }}"
            >
                <svg class="mr-2 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 9.5 12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z" />
                </svg>
                Dashboard
            </button>
        </div>

        @foreach ($groups as $groupKey => $items)
```

(No other change to this file — the `@foreach` block and everything after it stays exactly as-is.) This new button is a `.menu-item` outside any `[data-menu-group]` wrapper, so it's automatically wired by the existing generic `document.querySelectorAll('.menu-item')` click-handler and `is-active` toggle in `sapto.blade.php` (no JS change needed for the link itself), and it's automatically exempt from the sidebar search filter (which only touches `.menu-item`s inside `[data-menu-group]`).

- [ ] **Step 3: Include the new section and change the default table**

Modify `resources/views/sapto.blade.php`. In the `@section('content')` block, change:

```blade
@section('content')
    <section id="table-page">
        @include('components.filter-bar')
```

to:

```blade
@section('content')
    @include('components.dashboard-page')

    <section id="table-page" class="hidden">
        @include('components.filter-bar')
```

(`table-page` now starts hidden, since `dashboard-page` is the new default visible section.)

- [ ] **Step 4: Add `els.dashboardPage`/`els.dashboardStatus`/`els.dashboardCards` and change the default `currentTableId`**

In the same file, in the `const els = { ... }` object, add (right after the existing `placeholderTitle` line):

```js
        placeholderPage: document.getElementById('placeholder-page'),
        placeholderTitle: document.getElementById('placeholder-title'),
        dashboardPage: document.getElementById('dashboard-page'),
        dashboardStatus: document.getElementById('dashboard-status'),
        dashboardCards: document.querySelectorAll('.dashboard-card'),
```

Change:

```js
    let currentTableId = @json($activeTableId ?? 'dosen-pembimbing-ta');
```

to:

```js
    let currentTableId = @json($activeTableId ?? 'dashboard');
```

- [ ] **Step 5: Add the dashboard TABLE_HANDLERS entry and wire it into `activateTable()`**

Change:

```js
    const TABLE_HANDLERS = {
        'dosen-pembimbing-ta': {
            description: 'Data jumlah mahasiswa yang dibimbing oleh dosen sebagai pembimbing utama tugas akhir berdasarkan program studi dan tahun TS.',
            show() {
                els.tablePage.classList.remove('hidden');
                loadSummary();
            },
        },
        'mahasiswa-asing': {
```

to:

```js
    const TABLE_HANDLERS = {
        'dashboard': {
            description: 'Ringkasan angka kunci dari 5 tabel BAN-PT yang sudah tersedia datanya.',
            show() {
                els.dashboardPage.classList.remove('hidden');
                loadDashboard();
            },
        },
        'dosen-pembimbing-ta': {
            description: 'Data jumlah mahasiswa yang dibimbing oleh dosen sebagai pembimbing utama tugas akhir berdasarkan program studi dan tahun TS.',
            show() {
                els.tablePage.classList.remove('hidden');
                loadSummary();
            },
        },
        'mahasiswa-asing': {
```

Then change `activateTable()` — currently:

```js
    function activateTable(tableId) {
        currentTableId = tableId;

        document.querySelectorAll('.menu-item').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.tableId === tableId);
        });

        const table = tables.find((item) => item.id === tableId);
        els.pageTitle.textContent = table?.name || 'SAPTO';

        els.tablePage.classList.add('hidden');
        els.maPage.classList.add('hidden');
        els.placeholderPage.classList.add('hidden');

        const handler = TABLE_HANDLERS[tableId];
```

to:

```js
    function activateTable(tableId) {
        currentTableId = tableId;

        document.querySelectorAll('.menu-item').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.tableId === tableId);
        });

        // 'dashboard' bukan salah satu dari 46 tabel BAN-PT di array `tables`,
        // jadi judulnya di-hardcode, tidak dicari lewat tables.find().
        const table = tables.find((item) => item.id === tableId);
        els.pageTitle.textContent = tableId === 'dashboard' ? 'Dashboard' : (table?.name || 'SAPTO');

        els.tablePage.classList.add('hidden');
        els.maPage.classList.add('hidden');
        els.placeholderPage.classList.add('hidden');
        els.dashboardPage.classList.add('hidden');

        const handler = TABLE_HANDLERS[tableId];
```

- [ ] **Step 6: Add the dashboard load/render functions and wire up card clicks**

Add this block right before the line `els.role.addEventListener('change', async () => {` (i.e., right after the `escapeAttr` function definition):

```js
    function dashboardUrl() {
        const params = new URLSearchParams();

        if (state.ts !== null) {
            params.set('ts', state.ts);
        }

        if (state.role === 'universitas' && state.fakultasId !== 'all') {
            params.set('fakultas_id', state.fakultasId);
        }

        if (state.role !== 'prodi' && state.programStudiId !== 'all') {
            params.set('program_studi_id', state.programStudiId);
        }

        return `/api/sapto/dashboard/summary?${params}`;
    }

    async function loadDashboard() {
        setDashboardLoading();

        try {
            const payload = await fetchJson(dashboardUrl());
            renderDashboard(payload);
        } catch (error) {
            renderDashboardError(error.message);
        }
    }

    function setDashboardLoading() {
        els.dashboardStatus.textContent = 'Memuat ringkasan...';

        els.dashboardCards.forEach((card) => {
            card.querySelector('.dashboard-card-metrics').innerHTML = Array.from({ length: 3 }).map(() => `
                <div class="dashboard-metric">
                    <div class="dashboard-metric-value is-loading">0</div>
                    <div class="dashboard-metric-label">&nbsp;</div>
                </div>
            `).join('');
            card.querySelector('.dashboard-card-trend').innerHTML = '';
        });
    }

    function renderDashboard(payload) {
        if (payload.ts !== null && payload.ts !== undefined) {
            state.ts = payload.ts;
        }

        els.dashboardStatus.textContent = '';

        const cardsById = new Map((payload.cards || []).map((card) => [card.id, card]));

        els.dashboardCards.forEach((el) => {
            const card = cardsById.get(el.dataset.cardId);

            if (card) {
                renderDashboardCard(el, card);
            }
        });
    }

    function renderDashboardCard(el, card) {
        el.querySelector('.dashboard-card-title').textContent = card.title;
        const metricsEl = el.querySelector('.dashboard-card-metrics');
        const trendEl = el.querySelector('.dashboard-card-trend');

        if (card.error) {
            metricsEl.innerHTML = '<p class="dashboard-card-notice dashboard-card-notice-error">Data belum bisa ditampilkan.</p>';
            trendEl.innerHTML = '';
            return;
        }

        if (card.notice) {
            metricsEl.innerHTML = `<p class="dashboard-card-notice">${escapeHtml(card.notice)}</p>`;
            trendEl.innerHTML = '';
            return;
        }

        metricsEl.innerHTML = card.metrics.map((metric) => `
            <div class="dashboard-metric">
                <div class="dashboard-metric-value">${formatNumber(metric.value)}</div>
                <div class="dashboard-metric-label">${escapeHtml(metric.label)}</div>
            </div>
        `).join('');

        trendEl.innerHTML = card.trend ? dashboardSparkline(card.trend) : '';
    }

    function dashboardSparkline(trend) {
        const values = trend.values.map(Number);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const range = max - min || 1;
        const width = 120;
        const height = 32;
        const step = width / (values.length - 1 || 1);

        const points = values
            .map((value, index) => `${(index * step).toFixed(1)},${(height - ((value - min) / range) * height).toFixed(1)}`)
            .join(' ');

        return `
            <svg class="dashboard-sparkline" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" aria-hidden="true">
                <polyline points="${points}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <div class="dashboard-sparkline-labels">
                ${trend.labels.map((label) => `<span>${escapeHtml(label)}</span>`).join('')}
            </div>
        `;
    }

    function renderDashboardError(message) {
        els.dashboardStatus.innerHTML = `<span class="font-medium text-warn">${escapeHtml(message)}</span>`;
    }

    els.dashboardCards.forEach((card) => {
        card.addEventListener('click', () => activateTable(card.dataset.tableId));
    });

```

- [ ] **Step 7: Fix the bootstrap sequence to load whichever page is actually active**

Change the last lines of the script:

```js
    initTsOptions();
    loadFakultas()
        .then(loadProgramStudi)
        .then(loadSummary)
        .catch((error) => renderError(error.message));
```

to:

```js
    initTsOptions();
    loadFakultas()
        .then(loadProgramStudi)
        .then(() => activateTable(currentTableId))
        .catch((error) => renderError(error.message));
```

(Previously this always called `loadSummary()` — the dosen-pembimbing-ta-specific loader — regardless of which table was actually active. `activateTable(currentTableId)` dispatches to whichever handler actually matches the current page, which is what makes `dashboard` load correctly as the new default and is more correct generally.)

- [ ] **Step 8: Change the sidebar's default `activeTableId` in the layout**

Modify `resources/views/layouts/sapto.blade.php`. Change:

```blade
        @include('components.sidebar', ['tables' => $tables, 'activeTableId' => $activeTableId ?? 'dosen-pembimbing-ta'])
```

to:

```blade
        @include('components.sidebar', ['tables' => $tables, 'activeTableId' => $activeTableId ?? 'dashboard'])
```

- [ ] **Step 9: Manual verification**

There's no JS test runner in this project, so verify by hand against the running dev container:

1. Rebuild frontend assets (Node 18 on this machine is too old for Vite 8 — same workaround as the earlier mobile-layout fix session; skip if a working Node 20+ is already on PATH):
   ```bash
   npm run build
   ```
2. Confirm the app container is up: `docker ps` should show `sapto-prod-app-1` (or start it with `docker compose up -d` from the repo root if not).
3. Temporarily add a local-only preview route to bypass SSO for manual testing (mirrors the pattern used earlier in this project's redesign work) — insert into `routes/web.php` right before `Route::middleware('sso.auth')->group(function () {`:
   ```php
   if (app()->environment('local')) {
       Route::get('/__design_preview', function (\Illuminate\Http\Request $request) {
           $request->session()->put('sso_user', ['name' => 'Preview User', 'email' => 'preview@example.test']);
           return redirect('/');
       });
   }
   ```
   Then: `docker exec sapto-prod-app-1 php artisan route:clear`
4. Visit `http://localhost:8020/__design_preview` (adjust port if different) in a browser.
5. Confirm:
   - Dashboard is the page shown immediately (not Tabel 3.a.2).
   - Sidebar shows a "Dashboard" link above "Tabel 1", highlighted as active.
   - All 5 cards show real numbers (or the masa-studi card shows the "pilih program studi" notice, since no prodi is selected by default at role=universitas).
   - Clicking a card navigates to that table's own page (sidebar highlight moves too).
   - Clicking "Dashboard" in the sidebar again returns to it.
   - Changing the Role/TS/Fakultas dropdowns updates the dashboard's numbers.
   - Toggle dark mode: cards and sparklines still readable (no unstyled flash — note CSS lands in Task 4; if checking before Task 4 is done, cards will show unstyled metric text, which is expected at this point).
6. Remove the temporary `/__design_preview` route from `routes/web.php` and run `docker exec sapto-prod-app-1 php artisan route:clear` again before committing.

- [ ] **Step 10: Commit**

```bash
git add resources/views/components/dashboard-page.blade.php resources/views/components/sidebar.blade.php resources/views/sapto.blade.php resources/views/layouts/sapto.blade.php
git commit -m "feat(sapto): add dashboard as the default landing page

New #dashboard-page section (same show/hide pattern as the other
pages), a pinned Dashboard sidebar link, and the app now boots into
the dashboard instead of Tabel 3.a.2. Bootstrap now calls
activateTable(currentTableId) instead of unconditionally loading the
dosen-pembimbing-ta summary, so whichever page is actually active
loads its own data.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 4: Dashboard card + sparkline CSS

Styles the new `.dashboard-card`/`.dashboard-metric`/`.dashboard-sparkline` classes referenced by Task 3's markup, using existing design tokens only (no new colors).

**Files:**
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: existing tokens `--color-ink`, `--color-muted`, `--color-accent`, `--color-accent-line`, `--color-focus`, `--color-warn`, `--skeleton` (all already defined for both light/dark themes in `app.css`).
- Produces: `.dashboard-card`, `.dashboard-metric-value`, `.dashboard-metric-value.is-loading`, `.dashboard-metric-label`, `.dashboard-card-notice`, `.dashboard-card-notice-error`, `.dashboard-sparkline`, `.dashboard-sparkline-labels` — consumed only by the markup/JS added in Task 3.

- [ ] **Step 1: Add the CSS**

Modify `resources/css/app.css`. Add this block right after the `.swipe-hint { ... }` rule (the last rule inside `@layer components { ... }`, before the closing `}` of that layer):

```css
    /* ---- Dashboard cards ---- */
    .dashboard-card {
        display: block;
        cursor: pointer;
        transition: transform 180ms ease, border-color 180ms ease;
    }

    .dashboard-card:hover {
        border-color: var(--color-accent-line);
    }

    .dashboard-card:focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-focus) 45%, transparent);
    }

    .dashboard-metric-value {
        color: var(--color-ink);
        @apply text-[18px] font-extrabold leading-tight tabular-nums;
    }

    .dashboard-metric-value.is-loading {
        background: var(--skeleton);
        color: transparent;
        @apply inline-block w-12 animate-pulse rounded-md;
    }

    .dashboard-metric-label {
        color: var(--color-muted);
        @apply mt-0.5 text-[11px] leading-snug;
    }

    .dashboard-card-notice {
        color: var(--color-muted);
        grid-column: 1 / -1;
        @apply text-[12.5px] leading-relaxed;
    }

    .dashboard-card-notice-error {
        color: var(--color-warn);
    }

    .dashboard-sparkline {
        color: var(--color-accent);
        width: 100%;
        height: 32px;
    }

    .dashboard-sparkline-labels {
        color: var(--color-muted);
        display: flex;
        justify-content: space-between;
        @apply mt-1 text-[10px];
    }
```

- [ ] **Step 2: Rebuild assets**

Run: `npm run build`
Expected: build succeeds, `public/build/assets/app-*.css` regenerated with a new hash.

If Node on `PATH` is older than 20.19 (Vite 8 requirement — this was the case earlier in this project), download a portable Node 20 into a scratch directory and prefix `PATH` for this one command, e.g.:
```bash
curl -fsSL -o /tmp/node20.tar.xz https://nodejs.org/dist/v20.19.0/node-v20.19.0-linux-x64.tar.xz
mkdir -p /tmp/node20 && tar -xf /tmp/node20.tar.xz -C /tmp/node20 --strip-components=1
PATH="/tmp/node20/bin:$PATH" npm run build
```

- [ ] **Step 3: Manual verification**

Repeat the manual verification steps from Task 3 Step 9 (temporary `/__design_preview` route), this time confirming:
- Card metric numbers use the bold tabular-numeral style, muted labels underneath.
- Loading state shows a pulsing skeleton block instead of "0" while `loadDashboard()` is in flight (throttle the network in devtools, or just watch the first paint).
- Sparklines render as a small teal/accent-colored line with year labels underneath, in both light and dark mode (accent color already adapts via the `--color-accent` token — no dark-mode-specific CSS needed here).
- The masa-studi notice message and any error message (`.dashboard-card-notice-error`, amber-colored) are legible in both themes.
- Remove the temporary `/__design_preview` route again afterward (`docker exec sapto-prod-app-1 php artisan route:clear`).

- [ ] **Step 4: Commit**

```bash
git add resources/css/app.css
git commit -m "style(sapto): add dashboard card and sparkline styles

Uses only existing design tokens (no new colors) so cards match the
liquid-glass system automatically in both themes.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```
