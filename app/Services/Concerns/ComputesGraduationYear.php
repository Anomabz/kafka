<?php

namespace App\Services\Concerns;

/**
 * Ekspresi SQL tahun lulus dari list_mahasiswa_lulus_do. Utamakan
 * id_periode_keluar (format YYYYS); bila kosong -- yang terjadi pada ~18%
 * baris "Lulus" (kebanyakan lulusan lama) -- ambil tahun dari tanggal
 * keluar/yudisium (format DD-MM-YYYY, tahun di 4 karakter terakhir; juga
 * menangani YYYY-MM-DD). Tanpa fallback ini ribuan lulusan tidak masuk
 * hitungan per tahun. Portabel MySQL & SQLite.
 *
 * Dipakai bersama oleh tabel 8.a (IPK Lulusan) dan 8.d.1 (Waktu Tunggu
 * Lulusan) supaya keduanya menghitung lulusan yang sama per tahun.
 */
trait ComputesGraduationYear
{
    private function graduationYearSql(): string
    {
        $fromDate = static fn (string $col): string => "case when substr({$col},3,1) = '-' then substr({$col},7,4) else substr({$col},1,4) end";

        return "case
            when id_periode_keluar is not null and id_periode_keluar <> '' then substr(id_periode_keluar,1,4)
            when tanggal_keluar is not null and length(tanggal_keluar) = 10 then {$fromDate('tanggal_keluar')}
            when tgl_keluar is not null and length(tgl_keluar) = 10 then {$fromDate('tgl_keluar')}
            when tgl_sk_yudisium is not null and length(tgl_sk_yudisium) = 10 then {$fromDate('tgl_sk_yudisium')}
            else null
        end";
    }
}
