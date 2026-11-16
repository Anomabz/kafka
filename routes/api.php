<?php

use App\Http\Controllers\Api\Sapto\BukuDtpsController;
use App\Http\Controllers\Api\Sapto\DashboardController;
use App\Http\Controllers\Api\Sapto\DosenPembimbingTaController;
use App\Http\Controllers\Api\Sapto\DosenTetapController;
use App\Http\Controllers\Api\Sapto\DosenIndustriController;
use App\Http\Controllers\Api\Sapto\DosenTidakTetapController;
use App\Http\Controllers\Api\Sapto\EwmpDosenController;
use App\Http\Controllers\Api\Sapto\KurikulumController;
use App\Http\Controllers\Api\Sapto\FakultasController;
use App\Http\Controllers\Api\Sapto\HkiHakCiptaDtpsController;
use App\Http\Controllers\Api\Sapto\HkiPatenDtpsController;
use App\Http\Controllers\Api\Sapto\IpkLulusanController;
use App\Http\Controllers\Api\Sapto\KerjasamaPendidikanController;
use App\Http\Controllers\Api\Sapto\KerjasamaPenelitianController;
use App\Http\Controllers\Api\Sapto\KerjasamaPkmController;
use App\Http\Controllers\Api\Sapto\KesiapanController;
use App\Http\Controllers\Api\Sapto\KesesuaianBidangKerjaController;
use App\Http\Controllers\Api\Sapto\IntegrasiPembelajaranController;
use App\Http\Controllers\Api\Sapto\KepuasanMahasiswaController;
use App\Http\Controllers\Api\Sapto\KepuasanPenggunaController;
use App\Http\Controllers\Api\Sapto\MasaStudiController;
use App\Http\Controllers\Api\Sapto\PagelaranDtpsController;
use App\Http\Controllers\Api\Sapto\PagelaranMahasiswaController;
use App\Http\Controllers\Api\Sapto\PdUnsriRefreshController;
use App\Http\Controllers\Api\Sapto\PdUnsriStatusController;
use App\Http\Controllers\Api\Sapto\PenelitianDtpsController;
use App\Http\Controllers\Api\Sapto\PenelitianMelibatkanMhsController;
use App\Http\Controllers\Api\Sapto\PkmDtpsController;
use App\Http\Controllers\Api\Sapto\PkmMelibatkanMhsController;
use App\Http\Controllers\Api\Sapto\ProdukJasaMahasiswaController;
use App\Http\Controllers\Api\Sapto\PrestasiAkademikController;
use App\Http\Controllers\Api\Sapto\PrestasiNonAkademikController;
use App\Http\Controllers\Api\Sapto\ProgramStudiController;
use App\Http\Controllers\Api\Sapto\PublikasiMahasiswaController;
use App\Http\Controllers\Api\Sapto\PublikasiIlmiahDtpsController;
use App\Http\Controllers\Api\Sapto\RekognisiDtpsController;
use App\Http\Controllers\Api\Sapto\SeleksiMahasiswaController;
use App\Http\Controllers\Api\Sapto\ReferensiKepuasanPenggunaController;
use App\Http\Controllers\Api\Sapto\TeknologiDtpsController;
use App\Http\Controllers\Api\Sapto\TeknologiMahasiswaController;
use App\Http\Controllers\Api\Sapto\TempatKerjaController;
use App\Http\Controllers\Api\Sapto\WaktuTungguController;
use App\Http\Controllers\Api\Sapto\SitasiDtpsController;
use App\Http\Controllers\Api\Sapto\SitasiMahasiswaController;
use App\Http\Controllers\Api\Sapto\BukuMahasiswaController;
use App\Http\Controllers\Api\Sapto\HkiHakCiptaMahasiswaController;
use App\Http\Controllers\Api\Sapto\HkiPatenMahasiswaController;
use App\Http\Controllers\Api\Sapto\ProdukJasaDtpsController;
use App\Http\Controllers\Api\V1\PdUnsriTableController;

use Illuminate\Support\Facades\Route;

// 'web' adds the session/cookie stack so the SSO login (session-based) is
// visible here; 'sso.auth' rejects unauthenticated calls with 401.
Route::middleware(['web', 'sso.auth'])->prefix('sapto')->group(function (): void {
    Route::get('/fakultas', FakultasController::class);
    Route::get('/program-studi', ProgramStudiController::class);

    // Badge status API PD UNSRI di topbar -- semua peran, lihat PdUnsriHealthCheck.
    Route::get('/pd-unsri/status', PdUnsriStatusController::class);
    // "Segarkan laporan" lingkup pengguna sendiri, dari data lokal -- lihat SaptoReportRefresher.
    Route::post('/pd-unsri/refresh', PdUnsriRefreshController::class);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/kesiapan', KesiapanController::class);

    Route::prefix('tables/dosen-pembimbing-ta')->group(function (): void {
        Route::get('/summary', [DosenPembimbingTaController::class, 'summary']);
        Route::get('/detail', [DosenPembimbingTaController::class, 'detail']);
    });

    Route::get('/tables/dosen-tetap-pt/summary', [DosenTetapController::class, 'summary']);
    Route::get('/tables/dosen-tidak-tetap/summary', [DosenTidakTetapController::class, 'summary']);
    Route::get('/tables/ipk-lulusan/summary', [IpkLulusanController::class, 'summary']);
    Route::get('/tables/ipk-lulusan/detail', [IpkLulusanController::class, 'detail']);
    Route::get('/tables/masa-studi/summary', [MasaStudiController::class, 'summary']);
    Route::get('/tables/masa-studi/detail', [MasaStudiController::class, 'detail']);
    // Detail lulusan dipakai oleh kolom "Jumlah Lulusan" di tiap tabel;
    // endpoint memakai filter tahun dan scope akses yang sama.
    Route::get('/tables/waktu-tunggu/detail', [IpkLulusanController::class, 'detail']);
    Route::get('/tables/kesesuaian-bidang-kerja/detail', [IpkLulusanController::class, 'detail']);
    Route::get('/tables/tempat-kerja/detail', [IpkLulusanController::class, 'detail']);
    Route::get('/tables/referensi-kepuasan-pengguna/detail', [IpkLulusanController::class, 'detail']);
    Route::get('/tables/kerjasama-pendidikan/summary', [KerjasamaPendidikanController::class, 'summary']);
    Route::get('/tables/kerjasama-penelitian/summary', [KerjasamaPenelitianController::class, 'summary']);
    Route::get('/tables/kerjasama-pkm/summary', [KerjasamaPkmController::class, 'summary']);
    Route::get('/tables/penelitian-dtps/summary', [PenelitianDtpsController::class, 'summary']);
    Route::get('/tables/penelitian-dtps/detail', [PenelitianDtpsController::class, 'detail']);
    Route::get('/tables/penelitian-melibatkan-mahasiswa/summary', [PenelitianMelibatkanMhsController::class, 'summary']);
    Route::get('/tables/pkm-dtps/summary', [PkmDtpsController::class, 'summary']);
    Route::get('/tables/pkm-dtps/detail', [PkmDtpsController::class, 'detail']);
    Route::get('/tables/pkm-melibatkan-mahasiswa/summary', [PkmMelibatkanMhsController::class, 'summary']);
    Route::get('/tables/publikasi-ilmiah-dtps/summary', [PublikasiIlmiahDtpsController::class, 'summary']);
    Route::get('/tables/publikasi-ilmiah-dtps/detail', [PublikasiIlmiahDtpsController::class, 'detail']);
    Route::get('/tables/pagelaran-dtps/summary', [PagelaranDtpsController::class, 'summary']);
    Route::get('/tables/pagelaran-dtps/detail', [PagelaranDtpsController::class, 'detail']);
    Route::get('/tables/buku-dtps/summary', [BukuDtpsController::class, 'summary']);
    Route::get('/tables/teknologi-dtps/summary', [TeknologiDtpsController::class, 'summary']);
    Route::get('/tables/hki-paten-dtps/summary', [HkiPatenDtpsController::class, 'summary']);
    Route::get('/tables/hki-hak-cipta-dtps/summary', [HkiHakCiptaDtpsController::class, 'summary']);
    Route::get('/tables/waktu-tunggu/summary', [WaktuTungguController::class, 'summary']);
    Route::get('/tables/kesesuaian-bidang-kerja/summary', [KesesuaianBidangKerjaController::class, 'summary']);
    Route::get('/tables/tempat-kerja/summary', [TempatKerjaController::class, 'summary']);
    Route::get('/tables/referensi-kepuasan-pengguna/summary', [ReferensiKepuasanPenggunaController::class, 'summary']);
    Route::get('/tables/prestasi-akademik/summary', [PrestasiAkademikController::class, 'summary']);
    Route::get('/tables/prestasi-non-akademik/summary', [PrestasiNonAkademikController::class, 'summary']);

    // Tabel LKPS yang dibangun 2026-09-12 setelah tabel sumbernya ikut
    // disinkronkan (lihat config/pd_unsri_sync_tables.php).
    Route::get('/tables/rekognisi-dtps/summary', [RekognisiDtpsController::class, 'summary']);
    Route::get('/tables/dosen-industri/summary', [DosenIndustriController::class, 'summary']);
    Route::get('/tables/kurikulum/summary', [KurikulumController::class, 'summary']);

    // Tabel LKPS tambahan yang dipakai halaman SAPTO.
    Route::get('/tables/ewmp-dosen/summary', [EwmpDosenController::class, 'summary']);
    Route::get('/tables/sitasi-dtps/summary', [SitasiDtpsController::class, 'summary']);
    Route::get('/tables/integrasi-pembelajaran/summary', [IntegrasiPembelajaranController::class, 'summary']);
    Route::get('/tables/kepuasan-mahasiswa/summary', [KepuasanMahasiswaController::class, 'summary']);
    Route::get('/tables/kepuasan-pengguna/summary', [KepuasanPenggunaController::class, 'summary']);
    Route::get('/tables/publikasi-mahasiswa/summary', [PublikasiMahasiswaController::class, 'summary']);
    Route::get('/tables/pagelaran-mahasiswa/summary', [PagelaranMahasiswaController::class, 'summary']);
    Route::get('/tables/sitasi-mahasiswa/summary', [SitasiMahasiswaController::class, 'summary']);
    Route::get('/tables/produk-jasa-mahasiswa/summary', [ProdukJasaMahasiswaController::class, 'summary']);
    Route::get('/tables/hki-paten-mahasiswa/summary', [HkiPatenMahasiswaController::class, 'summary']);
    Route::get('/tables/hki-hak-cipta-mahasiswa/summary', [HkiHakCiptaMahasiswaController::class, 'summary']);
    Route::get('/tables/teknologi-mahasiswa/summary', [TeknologiMahasiswaController::class, 'summary']);
    Route::get('/tables/buku-mahasiswa/summary', [BukuMahasiswaController::class, 'summary']);
    Route::get('/tables/seleksi-mahasiswa/summary', [SeleksiMahasiswaController::class, 'summary']);
    Route::get('/tables/seleksi-mahasiswa/detail', [SeleksiMahasiswaController::class, 'detail']);
    Route::get('/tables/produk-jasa-dtps/summary', [ProdukJasaDtpsController::class, 'summary']);

});

// API baca-saja "Data Akademik UNSRI per Tabel" -- lihat "URL API PD UNSRI.xlsx"
// dan config/pd_unsri_tables.php untuk daftar tabel yang diekspos. {table} cuma
// boleh salah satu slug di whitelist itu; di luar itu 404 (lihat
// PdUnsriTableController).
Route::middleware(['web', 'sso.auth'])->prefix('v1')->group(function (): void {
    Route::get('/{table}', [PdUnsriTableController::class, 'index']);
});
