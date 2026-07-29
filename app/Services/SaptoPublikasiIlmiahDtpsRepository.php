<?php

namespace App\Services;

/**
 * Tabel 3.b.4) Publikasi Ilmiah DTPS — varian program AKADEMIK
 * (Sarjana/Magister/Doktor). Seluruh perhitungan ada di
 * {@see SaptoPublikasiDtpsRepository}; di sini hanya daftar baris dan
 * pemetaannya.
 *
 * Keterbatasan sumber data (perlu diverifikasi manual):
 * - "Seminar wilayah/lokal/perguruan tinggi" selalu 0: taksonomi SISTER cuma
 *   mengenal prosiding/poster tingkat nasional & internasional, tidak ada
 *   penanda tingkat wilayah/lokal/PT.
 * - "Tulisan di media massa wilayah" dan "... internasional" selalu 0 dengan
 *   alasan yang sama.
 * - "Tulisan di media massa nasional" mengambil SELURUH baris
 *   "Koran/majalah populer/majalah umum" karena sumber tidak memisahkan
 *   cakupan wilayah/nasional/internasional.
 */
class SaptoPublikasiIlmiahDtpsRepository extends SaptoPublikasiDtpsRepository
{
    protected function tableName(): string
    {
        return 'Publikasi Ilmiah DTPS';
    }

    protected function categories(): array
    {
        return [
            'jurnal_tidak_terakreditasi' => 'Jurnal penelitian tidak terakreditasi',
            'jurnal_nasional_terakreditasi' => 'Jurnal penelitian nasional terakreditasi',
            'jurnal_internasional' => 'Jurnal penelitian internasional',
            'jurnal_internasional_bereputasi' => 'Jurnal penelitian internasional bereputasi',
            'seminar_wilayah' => 'Seminar wilayah/lokal/perguruan tinggi',
            'seminar_nasional' => 'Seminar nasional',
            'seminar_internasional' => 'Seminar internasional',
            'media_wilayah' => 'Tulisan di media massa wilayah',
            'media_nasional' => 'Tulisan di media massa nasional',
            'media_internasional' => 'Tulisan di media massa internasional',
        ];
    }

    protected function jenjangFilter(): array
    {
        return ['S1', 'S2', 'S3'];
    }

    protected function jenisMap(): array
    {
        return [
            'Jurnal nasional' => 'jurnal_tidak_terakreditasi',
            'Jurnal nasional terakreditasi' => 'jurnal_nasional_terakreditasi',
            'Jurnal internasional' => 'jurnal_internasional',
            'Jurnal internasional bereputasi' => 'jurnal_internasional_bereputasi',
            'Prosiding seminar nasional' => 'seminar_nasional',
            'Poster seminar nasional' => 'seminar_nasional',
            'Prosiding seminar internasional' => 'seminar_internasional',
            'Poster seminar internasional' => 'seminar_internasional',
            'Koran/majalah populer/majalah umum' => 'media_nasional',
        ];
    }
}
