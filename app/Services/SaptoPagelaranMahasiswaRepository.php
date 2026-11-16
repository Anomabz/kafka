<?php

namespace App\Services;

/**
 * Tabel 8.f.1) Pagelaran/Pameran/Presentasi/Publikasi Ilmiah Mahasiswa --
 * program terapan (Sarjana Terapan/Magister Terapan/Doktor Terapan). Baris &
 * pemetaan = Tabel 3.b.4 varian vokasi ({@see SaptoPagelaranDtpsRepository}):
 * baris pagelaran/pameran selalu 0 karena SISTER tidak punya jenis itu.
 */
class SaptoPagelaranMahasiswaRepository extends SaptoPublikasiMahasiswaRepository
{
    protected function tableName(): string
    {
        return 'Pagelaran/Pameran/Presentasi/Publikasi Ilmiah Mahasiswa';
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
            'pagelaran_wilayah' => 'Pagelaran/pameran/presentasi dalam forum di tingkat wilayah',
            'pagelaran_nasional' => 'Pagelaran/pameran/presentasi dalam forum di tingkat nasional',
            'pagelaran_internasional' => 'Pagelaran/pameran/presentasi dalam forum di tingkat internasional',
        ];
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
        ];
    }
}
