<?php

namespace App\Services;

/**
 * Tabel 3.b.4) Pagelaran/Pameran/Presentasi/Publikasi Ilmiah DTPS — varian
 * program VOKASI (Diploma Tiga/Sarjana Terapan/Magister Terapan/Doktor
 * Terapan). Seluruh perhitungan ada di {@see SaptoPublikasiDtpsRepository}.
 *
 * Baris 1-7 (jurnal + seminar) sama persis dengan varian akademik
 * {@see SaptoPublikasiIlmiahDtpsRepository}. Yang berbeda hanya baris 8-10:
 * di sini pagelaran/pameran/presentasi dalam forum, bukan tulisan di media
 * massa. Karena itu "Koran/majalah populer/majalah umum" TIDAK dipetakan di
 * varian ini — tulisan populer bukan pagelaran/pameran.
 *
 * Keterbatasan sumber data (perlu diverifikasi manual):
 * - "Seminar wilayah/lokal/perguruan tinggi" selalu 0: taksonomi SISTER cuma
 *   mengenal prosiding/poster tingkat nasional & internasional.
 * - Ketiga baris pagelaran/pameran/presentasi selalu 0: tidak ada sumbernya.
 *   Tabel SISTER yang seharusnya memuatnya (sister_list_pembicara,
 *   sister_list_orasi_ilmiah, sister_list_penghargaan) semuanya masih kosong,
 *   dan di sister_list_publikasi tidak ada jenis_publikasi bertema
 *   pagelaran/pameran/karya seni — yang menyebut "dipresentasikan" di sana
 *   semuanya prosiding seminar, yang sudah dihitung di baris 6-7. Isi manual
 *   sampai sumbernya tersedia.
 */
class SaptoPagelaranDtpsRepository extends SaptoPublikasiDtpsRepository
{
    protected function tableName(): string
    {
        return 'Pagelaran/Pameran/Presentasi/Publikasi Ilmiah DTPS';
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

    protected function jenjangFilter(): array
    {
        return ['D3', 'D4', 'Sarjana Terapan', 'Magister Terapan', 'Doktor Terapan'];
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
