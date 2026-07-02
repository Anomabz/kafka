<?php

use App\Http\Controllers\Api\Sapto\DosenPembimbingTaController;
use App\Http\Controllers\Api\Sapto\FakultasController;
use App\Http\Controllers\Api\Sapto\ProgramStudiController;
use App\Http\Controllers\Api\Sapto\MahasiswaController;
use Illuminate\Support\Facades\Route;

Route::prefix('sapto')->group(function (): void {
    Route::get('/fakultas', FakultasController::class);
    Route::get('/program-studi', ProgramStudiController::class);

    Route::get('/mahasiswa-asing', [MahasiswaController::class, 'data_mahasiswa_asing']);

    Route::prefix('tables/dosen-pembimbing-ta')->group(function (): void {
        Route::get('/summary', [DosenPembimbingTaController::class, 'summary']);
        Route::get('/detail', [DosenPembimbingTaController::class, 'detail']);
    });
});
