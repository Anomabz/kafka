<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\PenugasanController;
use App\Http\Controllers\Api\Sapto\MahasiswaController; // <-- 1. Import Controllernya di sini
use App\Http\Controllers\Auth\SsoController;
use App\Support\TelegramNotifier;

// Welcome page bermerek SAPTO tidak punya URL sendiri lagi: tamu yang membuka
// root langsung dilayani halaman itu oleh EnsureSsoAuthenticated, jadi address
// bar tetap "sapto.unsri.ac.id". /login disisakan cuma sebagai pengalihan untuk
// bookmark lama (dan supaya route('login') tetap sah).
//
// Tidak ada form kredensial milik SAPTO sama sekali: password diketik di
// halaman login bawaan Keycloak, jadi sesi SSO-nya dipakai bersama aplikasi
// kampus lain — yang sudah login di app lain tidak perlu mengetik apa pun.
Route::redirect('/login', '/')->name('login');

// Easter egg — sengaja tidak ditautkan dari nav manapun.
Route::get('/credits', fn () => view('credits'));

// Masuk = POST dari welcome page (membawa token captcha). GET dialihkan ke
// root supaya URL ini tidak bisa dipakai untuk melompati captcha.
Route::get('/auth/login', fn () => redirect('/'));
Route::post('/auth/login', [SsoController::class, 'login'])->name('sso.login');
Route::get('/auth/callback', [SsoController::class, 'callback'])->name('sso.callback');
Route::get('/auth/logout', [SsoController::class, 'logout'])->name('sso.logout');

// Webhook @saptostatusbot: chat mana pun yang tahu {secret} bisa daftar
// sendiri untuk terima alert error 5xx. Secret di URL path itulah gerbangnya
// -- kalau salah, cocokkan dulu ke config baru proses body.
Route::post('/telegram/webhook/{secret}', function (Request $request, string $secret) {
    if (! hash_equals((string) config('services.telegram.webhook_secret'), $secret)) {
        abort(404);
    }

    $chatId = $request->input('message.chat.id');
    if ($chatId) {
        $isNew = TelegramNotifier::registerChatId($chatId);
        TelegramNotifier::sendTo($chatId, $isNew
            ? '✅ Chat ini terdaftar untuk alert error SAPTO.'
            : 'Chat ini sudah terdaftar.');
    }

    return response()->noContent();
})->middleware('throttle:30,1');

// Catatan: route /dev/login-as lama dihapus. Dulu di-gate app()->environment
// ('local'), tapi container ini berjalan sebagai APP_ENV=local sehingga
// gate-nya tak pernah aktif — artinya siapa pun bisa menyamar jadi korprodi
// tanpa password. Diganti dengan /admin/login-as yang dikunci akun admin
// (lihat ImpersonationController).

Route::middleware('sso.auth')->group(function () {

// "Login sebagai" khusus admin (pengganti /dev/login-as) — lihat
// ImpersonationController (gated is_admin, tanpa eskalasi hak).
Route::get('/admin/login-as', [ImpersonationController::class, 'index'])->name('admin.login-as');
Route::post('/admin/login-as', [ImpersonationController::class, 'store'])->name('admin.login-as.store');
Route::post('/admin/stop-impersonating', [ImpersonationController::class, 'stop'])->name('admin.stop-impersonating');

// Kelola siapa boleh masuk SAPTO dan sebagai apa (korprodi/dekan/rektor) —
// lihat PenugasanController. Khusus admin.
Route::get('/admin/peran', [PenugasanController::class, 'index'])->name('admin.peran');
Route::post('/admin/peran', [PenugasanController::class, 'store'])->name('admin.peran.store');
Route::delete('/admin/peran/{penugasan}', [PenugasanController::class, 'destroy'])->name('admin.peran.destroy');

Route::get('/', function () {
    return view('sapto', [
        // Dashboard dihapus; tabel pertama (Kerjasama Tridharma - Pendidikan)
        // jadi tab default saat aplikasi dibuka.
        'activeTableId' => 'kerjasama-pendidikan',
        'tables' => [
            ['id' => 'kerjasama-pendidikan', 'code' => '1', 'name' => 'Tabel 1 Kerjasama Tridharma - Pendidikan'],
            ['id' => 'kerjasama-penelitian', 'code' => '1', 'name' => 'Tabel 1 Kerjasama Tridharma - Penelitian'],
            ['id' => 'kerjasama-pkm', 'code' => '1', 'name' => 'Tabel 1 Kerjasama Tridharma - Pengabdian kepada Masyarakat'],
            ['id' => 'seleksi-mahasiswa', 'code' => '2.a', 'name' => 'Tabel 2.a Seleksi Mahasiswa'],
            ['id' => 'mahasiswa-asing', 'code' => '2.b', 'name' => 'Tabel 2.b Mahasiswa Asing'],
            ['id' => 'dosen-tetap-pt', 'code' => '3.a.1', 'name' => 'Tabel 3.a.1) Dosen Tetap Perguruan Tinggi'],
            ['id' => 'dosen-pembimbing-ta', 'code' => '3.a.2', 'name' => 'Tabel 3.a.2) Dosen Pembimbing Utama Tugas Akhir'],
            ['id' => 'ewmp-dosen-tetap', 'code' => '3.a.3', 'name' => 'Tabel 3.a.3) Ekuivalen Waktu Mengajar Penuh (EWMP) Dosen Tetap Perguruan Tinggi'],
            ['id' => 'dosen-tidak-tetap', 'code' => '3.a.4', 'name' => 'Tabel 3.a.4) Dosen Tidak Tetap'],
            ['id' => 'dosen-industri', 'code' => '3.a.5', 'name' => 'Tabel 3.a.5) Dosen Industri/Praktisi'],
            ['id' => 'rekognisi-dosen', 'code' => '3.b.1', 'name' => 'Tabel 3.b.1) Pengakuan/Rekognisi Dosen'],
            ['id' => 'penelitian-dtps', 'code' => '3.b.2', 'name' => 'Tabel 3.b.2) Penelitian DTPS'],
            ['id' => 'pkm-dtps', 'code' => '3.b.3', 'name' => 'Tabel 3.b.3) PkM DTPS'],
            ['id' => 'publikasi-ilmiah-dtps', 'code' => '3.b.4', 'name' => 'Tabel 3.b.4) Publikasi Ilmiah DTPS'],
            ['id' => 'pagelaran-dtps', 'code' => '3.b.4', 'name' => 'Tabel 3.b.4) Pagelaran/Pameran/Presentasi/Publikasi Ilmiah DTPS'],
            ['id' => 'sitasi-dtps', 'code' => '3.b.5', 'name' => 'Tabel 3.b.5) Karya Ilmiah DTPS yang Disitasi'],
            ['id' => 'produk-jasa-dtps', 'code' => '3.b.6', 'name' => 'Tabel 3.b.6) Produk/Jasa DTPS yang Diadopsi oleh Industri/Masyarakat'],
            ['id' => 'hki-paten-dtps', 'code' => '3.b.7', 'name' => 'Tabel 3.b.7) Luaran Penelitian/PkM Lainnya - HKI (Paten, Paten Sederhana)'],
            ['id' => 'hki-hak-cipta-dtps', 'code' => '3.b.7', 'name' => 'Tabel 3.b.7) Luaran Penelitian/PkM Lainnya - HKI (Hak Cipta, Desain Produk Industri, dll.)'],
            ['id' => 'teknologi-dtps', 'code' => '3.b.7', 'name' => 'Tabel 3.b.7) Luaran Penelitian/PkM Lainnya - Teknologi Tepat Guna, Produk, Karya Seni, Rekayasa Sosial'],
            ['id' => 'buku-dtps', 'code' => '3.b.7', 'name' => 'Tabel 3.b.7) Luaran Penelitian/PkM Lainnya - Buku ber-ISBN, Book Chapter'],
            ['id' => 'penggunaan-dana', 'code' => '4', 'name' => 'Tabel 4 Penggunaan Dana'],
            ['id' => 'kurikulum', 'code' => '5.a', 'name' => 'Tabel 5.a Kurikulum, Capaian Pembelajaran, dan Rencana Pembelajaran'],
            ['id' => 'integrasi-pembelajaran', 'code' => '5.b', 'name' => 'Tabel 5.b Integrasi Kegiatan Penelitian/PkM dalam Pembelajaran'],
            ['id' => 'kepuasan-mahasiswa', 'code' => '5.c', 'name' => 'Tabel 5.c Kepuasan Mahasiswa'],
            ['id' => 'penelitian-melibatkan-mahasiswa', 'code' => '6.a', 'name' => 'Tabel 6.a Penelitian DTPS yang Melibatkan Mahasiswa'],
            ['id' => 'penelitian-rujukan-tema', 'code' => '6.b', 'name' => 'Tabel 6.b Penelitian DTPS yang Menjadi Rujukan Tema Tesis/Disertasi'],
            ['id' => 'pkm-melibatkan-mahasiswa', 'code' => '7', 'name' => 'Tabel 7 PkM DTPS yang Melibatkan Mahasiswa'],
            ['id' => 'ipk-lulusan', 'code' => '8.a', 'name' => 'Tabel 8.a IPK Lulusan'],
            ['id' => 'prestasi-akademik', 'code' => '8.b.1', 'name' => 'Tabel 8.b.1) Prestasi Akademik Mahasiswa'],
            ['id' => 'prestasi-non-akademik', 'code' => '8.b.2', 'name' => 'Tabel 8.b.2) Prestasi Non-akademik Mahasiswa'],
            ['id' => 'masa-studi', 'code' => '8.c', 'name' => 'Tabel 8.c Masa Studi Lulusan'],
            ['id' => 'waktu-tunggu', 'code' => '8.d.1', 'name' => 'Tabel 8.d.1) Waktu Tunggu Lulusan'],
            ['id' => 'kesesuaian-bidang-kerja', 'code' => '8.d.2', 'name' => 'Tabel 8.d.2) Kesesuaian Bidang Kerja Lulusan'],
            ['id' => 'tempat-kerja', 'code' => '8.e.1', 'name' => 'Tabel 8.e.1) Tempat Kerja Lulusan'],
            ['id' => 'referensi-kepuasan-pengguna', 'code' => '8.e.2', 'name' => 'Tabel Referensi 8.e.2)'],
            ['id' => 'kepuasan-pengguna', 'code' => '8.e.2', 'name' => 'Tabel 8.e.2) Kepuasan Pengguna Lulusan'],
            ['id' => 'publikasi-mahasiswa', 'code' => '8.f.1', 'name' => 'Tabel 8.f.1) Publikasi Ilmiah Mahasiswa'],
            ['id' => 'pagelaran-mahasiswa', 'code' => '8.f.1', 'name' => 'Tabel 8.f.1) Pagelaran/Pameran/Presentasi/Publikasi Ilmiah Mahasiswa'],
            ['id' => 'sitasi-mahasiswa', 'code' => '8.f.2', 'name' => 'Tabel 8.f.2) Karya Ilmiah Mahasiswa yang Disitasi'],
            ['id' => 'produk-jasa-mahasiswa', 'code' => '8.f.3', 'name' => 'Tabel 8.f.3) Produk/Jasa Mahasiswa yang Diadopsi oleh Industri/Masyarakat'],
            ['id' => 'hki-paten-mahasiswa', 'code' => '8.f.4', 'name' => 'Tabel 8.f.4) Luaran Penelitian yang Dihasilkan Mahasiswa - HKI (Paten, Paten Sederhana)'],
            ['id' => 'hki-hak-cipta-mahasiswa', 'code' => '8.f.4', 'name' => 'Tabel 8.f.4) Luaran Penelitian yang Dihasilkan Mahasiswa - HKI (Hak Cipta, Desain Produk Industri, dll.)'],
            ['id' => 'teknologi-mahasiswa', 'code' => '8.f.4', 'name' => 'Tabel 8.f.4) Luaran Penelitian yang Dihasilkan Mahasiswa -Teknologi Tepat Guna, Produk, Karya Seni, Rekayasa Sosial'],
            ['id' => 'buku-mahasiswa', 'code' => '8.f.4', 'name' => 'Tabel 8.f.4) Luaran Penelitian yang Dihasilkan Mahasiswa - Buku ber-ISBN, Book Chapter'],
        ],
    ]);
});

// 2. Taruh jalur web tanpa prefix API lu di sini (Sesuai arahan pembimbing)
Route::prefix('sapto')->group(function () {
    Route::get('/mahasiswa-asing', [MahasiswaController::class, 'data_mahasiswa_asing']);
    Route::get('/mahasiswa-asing/detail', [MahasiswaController::class, 'detail_mahasiswa_asing']);
});

}); // sso.auth group
