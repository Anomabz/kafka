<?php

use App\Http\Controllers\Api\Sapto\DashboardController;
use App\Http\Controllers\Api\Sapto\DosenPembimbingTaController;
use App\Http\Controllers\Api\Sapto\DosenTetapController;
use App\Http\Controllers\Api\Sapto\DosenTidakTetapController;
use App\Http\Controllers\Api\Sapto\FakultasController;
use App\Http\Controllers\Api\Sapto\IpkLulusanController;
use App\Http\Controllers\Api\Sapto\KerjasamaPendidikanController;
use App\Http\Controllers\Api\Sapto\KerjasamaPenelitianController;
use App\Http\Controllers\Api\Sapto\KerjasamaPkmController;
use App\Http\Controllers\Api\Sapto\MasaStudiController;
use App\Http\Controllers\Api\Sapto\PenelitianDtpsController;
use App\Http\Controllers\Api\Sapto\ProgramStudiController;
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
    Route::get('/tables/dosen-tidak-tetap/summary', [DosenTidakTetapController::class, 'summary']);
    Route::get('/tables/ipk-lulusan/summary', [IpkLulusanController::class, 'summary']);
    Route::get('/tables/masa-studi/summary', [MasaStudiController::class, 'summary']);
    Route::get('/tables/kerjasama-pendidikan/summary', [KerjasamaPendidikanController::class, 'summary']);
    Route::get('/tables/kerjasama-penelitian/summary', [KerjasamaPenelitianController::class, 'summary']);
    Route::get('/tables/kerjasama-pkm/summary', [KerjasamaPkmController::class, 'summary']);
    Route::get('/tables/penelitian-dtps/summary', [PenelitianDtpsController::class, 'summary']);
});
