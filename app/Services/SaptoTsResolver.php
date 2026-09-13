<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya sumber nilai TS untuk seluruh tabel LKPS.
 *
 * Di LKPS, TS adalah satu tahun acuan untuk SELURUH dokumen ("tahun akademik
 * penuh terakhir saat pengajuan usulan akreditasi", sheet Menu). Sebelum kelas
 * ini ada, tiap kelompok tabel menghitung TS sendiri dari sumbernya masing
 * masing: 3.b.x dari tahun ajaran penugasan dosen (2025) dan 8.x dari tahun
 * kelulusan (2026), sehingga satu dokumen memuat dua jendela pelaporan yang
 * berbeda -- salah satunya pasti tidak sesuai TS yang diisi di dokumen.
 *
 * Nilainya bisa dikunci lewat config sapto.ts (env SAPTO_TS) saat prodi
 * mengajukan akreditasi untuk tahun tertentu.
 */
class SaptoTsResolver
{
    /** Tahun akademik penuh terakhir; dipakai semua Sapto*Repository. */
    public function ts(): int
    {
        $dikunci = (int) config('sapto.ts');

        if ($dikunci > 0) {
            return $dikunci;
        }

        return Cache::remember('sapto:ts:v1', 3600, function (): int {
            // Diturunkan dari SATU tabel acuan, bukan dari tabel masing-masing
            // laporan: itulah yang dulu membuat TS berbeda antar tabel. Tahun
            // ajaran penugasan dosen dipakai karena menjadi kalender akademik
            // yang paling lengkap dan sudah jadi acuan kelompok 3.b.x.
            $tahun = (int) DB::table('list_penugasan_dosen')
                ->whereNotNull('nama_tahun_ajaran')
                ->where('nama_tahun_ajaran', '!=', '')
                ->max(DB::raw('SUBSTRING(nama_tahun_ajaran, 1, 4)'));

            if ($tahun >= 2000 && $tahun <= 2100) {
                return $tahun;
            }

            // Tabel acuan kosong (mis. instalasi baru): jatuh ke kalender.
            // Tahun akademik dilabeli tahun mulainya dan berganti tiap
            // Agustus, jadi yang "penuh terakhir" adalah satu tahun sebelum
            // yang sedang berjalan.
            $now = now();

            return (int) ($now->month >= 8 ? $now->year - 1 : $now->year - 2);
        });
    }

    /** TS, TS-1, TS-2 -- urutan lama ke baru, seperti kolom di LKPS. */
    public function tigaTahun(?int $ts = null): array
    {
        $ts ??= $this->ts();

        return [$ts - 2, $ts - 1, $ts];
    }

    public function dikunci(): bool
    {
        return (int) config('sapto.ts') > 0;
    }
}
