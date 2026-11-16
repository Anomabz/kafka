<?php

namespace App\Services;

/**
 * Tabel 8.f.1) Publikasi Ilmiah Mahasiswa -- program akademik
 * (Sarjana/Magister/Doktor). Baris & pemetaan = Tabel 3.b.4 varian akademik
 * ({@see SaptoPublikasiIlmiahDtpsRepository}), termasuk keterbatasannya:
 * seminar wilayah dan media massa wilayah/internasional selalu 0 karena
 * taksonomi SISTER tidak membedakannya.
 */
class SaptoPublikasiIlmiahMahasiswaRepository extends SaptoPublikasiMahasiswaRepository
{
    protected function tableName(): string
    {
        return 'Publikasi Ilmiah Mahasiswa';
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
