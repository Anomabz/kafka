<?php

/*
 * Tabel LKPS yang WAJIB diisi per jenis program, diambil dari sheet
 * "Daftar Tabel" pada "SAPTO & PD_UNSRI.xlsx" (templat LKPS BAN-PT, tanda √
 * di kolom D3/S/STr/M/MTr/D/DTr). Tabel yang tidak tercantum di 'wajib' wajib
 * untuk semua jenis program.
 *
 * Dipakai SaptoKesiapanService: tabel yang tidak wajib untuk jenjang prodi
 * terpilih berstatus `tidak_wajib` dan tidak dihitung "kosong" -- tanpa ini
 * mis. prodi S1 dianggap belum mengisi 6.b (khusus Magister/Doktor).
 */

return [
    // nama_jenjang_pendidikan di PD UNSRI => kode jenis program di templat.
    // Hanya jenjang yang benar-benar ada di data UNSRI (2026-09-30: D3, S1,
    // S2, S3, Profesi, Sp-1, Sp-2). Profesi/Sp tidak ada di templat LKPS ini,
    // dan program terapan belum ada di UNSRI: untuk jenjang yang tidak
    // terdaftar semua tabel dihitung, tidak ada yang disaring.
    'jenjang' => [
        'D3' => 'D3',
        'S1' => 'S1',
        'S2' => 'S2',
        'S3' => 'S3',
    ],

    'label' => [
        'D3' => 'Diploma Tiga',
        'S1' => 'Sarjana',
        'STr' => 'Sarjana Terapan',
        'S2' => 'Magister',
        'MTr' => 'Magister Terapan',
        'S3' => 'Doktor',
        'DTr' => 'Doktor Terapan',
    ],

    'wajib' => [
        'mahasiswa-asing' => ['S1', 'STr', 'S2', 'MTr', 'S3', 'DTr'],
        'dosen-industri' => ['D3', 'STr'],
        'publikasi-ilmiah-dtps' => ['S1', 'S2', 'S3'],
        'pagelaran-dtps' => ['D3', 'STr', 'MTr', 'DTr'],
        'sitasi-dtps' => ['S1', 'STr', 'S2', 'MTr', 'S3', 'DTr'],
        'produk-jasa-dtps' => ['D3', 'STr', 'MTr', 'DTr'],
        'penelitian-melibatkan-mahasiswa' => ['S1', 'STr', 'S2', 'MTr', 'S3', 'DTr'],
        'penelitian-rujukan-tema' => ['S2', 'MTr', 'S3', 'DTr'],
        'pkm-melibatkan-mahasiswa' => ['D3', 'S1', 'STr'],
        'prestasi-non-akademik' => ['D3', 'S1', 'STr'],
        'waktu-tunggu' => ['D3', 'S1', 'STr'],
        'kesesuaian-bidang-kerja' => ['D3', 'S1', 'STr', 'S2', 'MTr'],
        'tempat-kerja' => ['D3', 'S1', 'STr'],
        'referensi-kepuasan-pengguna' => ['D3', 'S1', 'STr', 'S2', 'MTr'],
        'kepuasan-pengguna' => ['D3', 'S1', 'STr', 'S2', 'MTr'],
        'publikasi-mahasiswa' => ['S1', 'S2', 'S3'],
        'pagelaran-mahasiswa' => ['STr', 'MTr', 'DTr'],
        'sitasi-mahasiswa' => ['S2', 'MTr', 'S3', 'DTr'],
        'produk-jasa-mahasiswa' => ['D3', 'STr', 'MTr', 'DTr'],
        'hki-paten-mahasiswa' => ['S1', 'STr', 'S2', 'MTr', 'S3', 'DTr'],
        'hki-hak-cipta-mahasiswa' => ['S1', 'STr', 'S2', 'MTr', 'S3', 'DTr'],
        'teknologi-mahasiswa' => ['S1', 'STr', 'S2', 'MTr', 'S3', 'DTr'],
        'buku-mahasiswa' => ['S1', 'STr', 'S2', 'MTr', 'S3', 'DTr'],
    ],
];
