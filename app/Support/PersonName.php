<?php

namespace App\Support;

/**
 * Merapikan nama untuk sapaan di layar. Nama dari SSO UNSRI sering HURUF
 * BESAR SEMUA ("DR. H.M. IRSAN SALEH"), yang terbaca seperti berteriak di
 * sapaan "Selamat pagi, ...". Nama yang sudah bercampur huruf besar/kecil
 * dibiarkan apa adanya: itu ejaan pemiliknya.
 */
class PersonName
{
    public static function tidy(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '' || $raw !== mb_strtoupper($raw)) {
            return $raw;
        }

        $title = mb_convert_case(mb_strtolower($raw), MB_CASE_TITLE);

        // Inisial bertitik ("H.M.") tetap kapital: "H.m." terlihat salah ketik.
        return (string) preg_replace_callback('/(?<=\p{L}\.)\p{Ll}/u', fn (array $m): string => mb_strtoupper($m[0]), $title);
    }
}
