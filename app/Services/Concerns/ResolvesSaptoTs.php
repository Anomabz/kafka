<?php

namespace App\Services\Concerns;

use App\Services\SaptoTsResolver;

/**
 * Sumber TS untuk repository tabel LKPS.
 *
 * Sebelum ini tiap repository memakai latestYear() = tahun terbaru yang ADA
 * DATANYA di tabelnya sendiri, sehingga satu dokumen memuat dua TS berbeda:
 * kelompok 3.b.x dapat 2025 (tahun ajaran penugasan dosen) dan kelompok 8.x
 * dapat 2026 (tahun kelulusan). Akibatnya baris TS pada 8.a hanya memuat 63
 * lulusan -- potongan tahun yang baru berjalan -- bukan tahun akademik penuh.
 *
 * TS sekarang satu nilai untuk semua tabel (SaptoTsResolver). Tahun yang
 * dipilih pengguna lewat dropdown tetap menang karena repository hanya
 * memakai nilai ini saat $ts null.
 */
trait ResolvesSaptoTs
{
    private function ts(): int
    {
        return app(SaptoTsResolver::class)->ts();
    }
}
