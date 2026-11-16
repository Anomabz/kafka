<?php

/**
 * Tabel sumber yang benar-benar di-query fitur SAPTO (Sapto*Repository,
 * Api\Sapto\MahasiswaController) untuk laporan LKPS yang sudah berjalan.
 * Key adalah slug endpoint sumber; value adalah nama tabel lokal. Beberapa
 * endpoint LPPM memakai slug yang berbeda dari nama tabel lokal.
 *
 * Dipakai PdUnsriSyncDispatcher (sinkronisasi mingguan + tombol admin).
 * TIDAK memengaruhi endpoint baca /api/v1/{table}, yang tetap melayani
 * seluruh 252 tabel di pd_unsri_tables.php.
 *
 * Dicek dari FROM/JOIN/DB::table() di kode, bukan sekadar kemunculan kata:
 * `fakultas`, `unit_kerja`, `wilayah`, `jenjang_pendidikan` muncul di kode
 * tapi sebagai nama kolom/key array, bukan tabel yang di-query.
 *
 * Tambah baris di sini begitu tabel LKPS baru dibangun dan butuh tabel
 * sumber lain; jangan tambah "buat jaga-jaga".
 */
return [

'tables' => [
    // 2.b Mahasiswa Asing
    'biodata-mahasiswa' => 'biodata_mahasiswa',
    'list-riwayat-pendidikan-mahasiswa' => 'list_riwayat_pendidikan_mahasiswa',
    // 404 di API sumber per 2026-09-11 -- job melewatinya, data lokal tetap dipakai.
    'negara' => 'negara',

    // Semua tabel, untuk daftar/lingkup prodi
    'program-studi' => 'program_studi',

    // 3.a.1 Dosen Tetap, 3.a.4 Dosen Tidak Tetap, dan roster DTPS 3.b.*
    'list-penugasan-dosen' => 'list_penugasan_dosen',
    'aktivitas-mengajar-dosen' => 'aktivitas_mengajar_dosen',
    'riwayat-fungsional-dosen' => 'riwayat_fungsional_dosen',
    'riwayat-pendidikan-dosen' => 'riwayat_pendidikan_dosen',
    'riwayat-sertifikasi-dosen' => 'riwayat_sertifikasi_dosen',
    'sister-list-penugasan' => 'sister_list_penugasan',

    // 3.a.2 Dosen Pembimbing Utama Tugas Akhir
    'dosen-pembimbing' => 'dosen_pembimbing',
    // 403 di API sumber per 2026-09-11 (akun API belum diberi akses) -- job
    // melewatinya sampai aksesnya dibuka, tanpa perlu ubah kode.
    'list-aktivitas-mahasiswa' => 'list_aktivitas_mahasiswa',

    // 3.b.2 Penelitian DTPS, 3.b.3 PkM DTPS
    'sister-detail-penelitian' => 'sister_detail_penelitian',
    'sister-detail-pengabdian' => 'sister_detail_pengabdian',
    'penelitian-dosen' => 'lppm_penelitian_dosen',
    'pengabdian-dosen' => 'lppm_pengabdian_dosen',

    // 3.b.4 Publikasi Ilmiah & Pagelaran DTPS
    'sister-list-publikasi' => 'sister_list_publikasi',

    // 3.b.7 HKI, Teknologi, Buku DTPS
    'sister-detail-kekayaan-intelektual' => 'sister_detail_kekayaan_intelektual',
    'sister-list-kekayaan-intelektual' => 'sister_list_kekayaan_intelektual',
    'sister-sdm' => 'sister_sdm',
    'sister-detail-publikasi' => 'sister_detail_publikasi',
    'jenis-publikasi' => 'jenis_publikasi',

    // 8.a IPK Lulusan, 8.d.1/8.d.2/8.e.1/Referensi 8.e.2 (jumlah lulusan)
    'list-mahasiswa-lulus-do' => 'list_mahasiswa_lulus_do',

    // 8.b.1/8.b.2 Prestasi, 8.c Masa Studi
    'list-mahasiswa' => 'list_mahasiswa',
    'list-prestasi-mahasiswa' => 'list_prestasi_mahasiswa',

    /*
     * Blok di bawah ini pengecualian dari aturan "hanya yang dipakai kode":
     * sheet "Sinkronisasi PD-UNSRI" di SAPTO & PD_UNSRI.xlsx mendaftar tabel
     * sumber yang sudah disiapkan tim (hendra, Mar-Apr 2026) untuk tabel LKPS
     * yang sedang/akan dibangun. Ditarik lebih dulu supaya saat fiturnya
     * dibuat datanya sudah ada dan segar. Per 2026-09-12 hampir semuanya
     * masih 0 baris di database.
     *
     * Lima tabel di sheet itu TIDAK dimasukkan karena API sumber membalas 404:
     * sister_wilayah, sister_list_pengujian_mahasiswa,
     * sister_list_ajuan_jabatan_fungsional, sister_list_ajuan_pendidikan_formal,
     * sister_detail_kepangkatan.
     */

    // 3.a.3 EWMP
    'sister-list-pengajaran' => 'sister_list_pengajaran',
    'sister-detail-pengajaran' => 'sister_detail_pengajaran',

    // 3.a.2 & 3.a.3 bimbingan mahasiswa
    'sister-list-bimbingan-mahasiswa' => 'sister_list_bimbingan_mahasiswa',
    'sister-detail-bimbingan-mahasiswa' => 'sister_detail_bimbingan_mahasiswa',

    // Data dosen pengajar kelas kuliah (dipakai tabel dosen, EWMP, penelitian,
    // PkM, publikasi, serta keterlibatan mahasiswa).
    'dosen-pengajar-kelas-kuliah' => 'dosen_pengajar_kelas_kuliah',

    // 3.a.1 & 3.a.4 profil dosen (pendidikan, sertifikasi, jabatan, penugasan)
    'sister-list-pendidikan-formal' => 'sister_list_pendidikan_formal',
    'sister-detail-pendidikan-formal' => 'sister_detail_pendidikan_formal',
    'sister-list-sertifikasi-dosen' => 'sister_list_sertifikasi_dosen',
    'sister-detail-sertifikasi-dosen' => 'sister_detail_sertifikasi_dosen',
    'sister-list-sertifikasi-profesi' => 'sister_list_sertifikasi_profesi',
    'sister-detail-sertifikasi-profesi' => 'sister_detail_sertifikasi_profesi',
    'sister-list-jabatan-fungsional' => 'sister_list_jabatan_fungsional',
    'sister-detail-jabatan-fungsional' => 'sister_detail_jabatan_fungsional',
    'sister-detail-jabatan-struktural' => 'sister_detail_jabatan_struktural',
    'sister-list-ajuan-kepangkatan' => 'sister_list_ajuan_kepangkatan',
    'sister-list-ajuan-sertifikasi-dosen' => 'sister_list_ajuan_sertifikasi_dosen',
    'sister-detail-ajuan-jabatan-fungsional' => 'sister_detail_ajuan_jabatan_fungsional',
    'sister-detail-penugasan' => 'sister_detail_penugasan',

    // 3.a.5 Dosen Industri/Praktisi
    'sister-list-riwayat-pekerjaan' => 'sister_list_riwayat_pekerjaan',
    'sister-detail-riwayat-pekerjaan' => 'sister_detail_riwayat_pekerjaan',

    // 3.b.1 Rekognisi DTPS
    'sister-detail-detasering' => 'sister_detail_detasering',
    'sister-detail-beasiswa' => 'sister_detail_beasiswa',

    // 5.a Kurikulum
    'list-kurikulum' => 'list_kurikulum',
    'detail-kurikulum' => 'detail_kurikulum',
    // mata-kuliah (sesuai daftar xlsx) menjawab 404; isinya tetap terjangkau
    // lewat detail-mata-kuliah + matkul-kurikulum yang keduanya jalan.
    'detail-mata-kuliah' => 'detail_mata_kuliah',
    'matkul-kurikulum' => 'matkul_kurikulum',
    'rencana-pembelajaran' => 'rencana_pembelajaran',
    'rencana-evaluasi' => 'rencana_evaluasi',

    // 3.b.1 Rekognisi/Pengakuan DTPS
    'sister-list-penghargaan' => 'sister_list_penghargaan',
    'sister-detail-penghargaan' => 'sister_detail_penghargaan',
    'sister-list-pembicara' => 'sister_list_pembicara',
    'sister-list-anggota-profesi' => 'sister_list_anggota_profesi',
    'sister-list-visiting-scientist' => 'sister_list_visiting_scientist',
    'sister-list-orasi-ilmiah' => 'sister_list_orasi_ilmiah',

    // 3.a.1/3.a.4 kepangkatan -- sister-detail-kepangkatan 404, versi list jalan
    'sister-list-kepangkatan' => 'sister_list_kepangkatan',

    // 3.a.2 & 6.b bimbingan/pengujian sisi feeder
    'list-bimbing-mahasiswa' => 'list_bimbing_mahasiswa',
    'list-uji-mahasiswa' => 'list_uji_mahasiswa',

     // Aktivitas akademik mahasiswa (sumber PD-DIKTI untuk kebutuhan lanjutan)
    'aktivitas-kuliah-mahasiswa' => 'aktivitas_kuliah_mahasiswa',
    'sister-list-pengelola-jurnal' => 'sister_list_pengelola_jurnal',
    'sister-list-pengabdian' => 'sister_list_pengabdian',
    'sister-list-penelitian' => 'sister_list_penelitian',
    'sister-list-bkd-penelitian' => 'sister_list_bkd_penelitian',
    'sister-list-bkd-pengabdian' => 'sister_list_bkd_pengabdian',
    'sister-list-bkd-penunjang' => 'sister_list_bkd_penunjang',
    'sister-list-penunjang-lain' => 'sister_list_penunjang_lain',
    'sister-list-detasering' => 'sister_list_detasering',


    // 8.f.1 luaran mahasiswa: penulis_publikasi SENGAJA tidak disinkronkan.
    // Tabel itu punya foreign key ke `detail_publikasi` yang TIDAK ADA di
    // database (bersama dokumen_publikasi dan bidang_ilmu_publikasi) --
    // kerusakan lama, bukan dari sinkronisasi. Selama induknya hilang, tabel
    // itu tidak bisa ditulis dengan aman. Perbaiki dulu skemanya (pulihkan
    // detail_publikasi atau buang FK yang menggantung), baru masukkan ke sini.

    // Profil PT -- menggantikan profil-pt lama yang 404
    'perguruan-tinggi' => 'perguruan_tinggi',
],

/*
 * API sumber sengaja tidak mengirim sebagian kolom (data pribadi: NIK,
 * alamat, kontak, orang tua, ...). Nilai kolom-kolom itu di tabel lokal
 * dipertahankan dengan menyalinnya dari tabel lama ke tabel staging lewat
 * kunci di bawah, sebelum ditukar -- kalau tidak, penukaran akan
 * mengosongkannya dan datanya tidak bisa ditarik ulang dari mana pun.
 *
 * Tabel yang tidak ada di sini tapi punya kolom tak-terkirim yang berisi
 * data TIDAK akan ditukar (job gagal dengan pesan jelas) -- lihat
 * SyncPdUnsriTableJob::carryOverUnsentColumns().
 */
'carry_over_keys' => [
    'biodata_mahasiswa' => 'id_mahasiswa',
    'list_mahasiswa' => 'id_registrasi_mahasiswa',
    'list_riwayat_pendidikan_mahasiswa' => 'id_registrasi_mahasiswa',
],

/*
 * Kode tabel laporan LKPS yang membaca tiap tabel sumber -- ditampilkan di
 * /admin/sync-pd-unsri supaya admin tahu laporan mana yang terdampak kalau
 * satu tabel gagal/dilewati.
 */
'used_by' => [
    'biodata_mahasiswa' => ['2.b'],
    'list_riwayat_pendidikan_mahasiswa' => ['2.b'],
    'negara' => ['2.b'],
    'program_studi' => ['Semua tabel'],
    'list_penugasan_dosen' => ['3.a.1', '3.a.4', '3.b.x'],
    'aktivitas_mengajar_dosen' => ['3.a.1'],
    'dosen_pengajar_kelas_kuliah' => ['3.a.1', '3.a.3', '3.a.4', '3.b.1', '3.b.2', '3.b.3', '3.b.4', '3.b.6', '3.b.7', '6.b', '7.b'],
    'lppm_penelitian_dosen' => ['6.b'],
    'lppm_pengabdian_dosen' => ['7.b'],
    'riwayat_fungsional_dosen' => ['3.a.1'],
    'riwayat_pendidikan_dosen' => ['3.a.1'],
    'riwayat_sertifikasi_dosen' => ['3.a.1'],
    'sister_list_penugasan' => ['3.b.4', '3.b.7'],
    'sister_list_kekayaan_intelektual' => ['3.b.6', '3.b.7'],
    'sister_sdm' => ['3.a.5', '3.b.1', '3.b.2', '3.b.3', '3.b.4', '3.b.6', '3.b.7'],
    'dosen_pembimbing' => ['3.a.2'],
    'list_aktivitas_mahasiswa' => ['3.a.2'],
    'sister_detail_penelitian' => ['3.b.2'],
    'sister_detail_pengabdian' => ['3.b.3'],
    'sister_list_publikasi' => ['3.b.4'],
    'sister_list_bkd_penelitian' => ['3.a.3 EWMP'],
    'sister_list_bkd_pengabdian' => ['3.a.3 EWMP'],
    'sister_list_bkd_penunjang' => ['3.a.3 EWMP'],
    'sister_detail_kekayaan_intelektual' => ['3.b.7 HKI', '3.b.7 Teknologi'],
    'sister_detail_publikasi' => ['3.b.7 Buku'],
    'jenis_publikasi' => ['3.b.7 Buku'],
    'list_mahasiswa_lulus_do' => ['8.a', '8.d.1', '8.d.2', '8.e.1', '8.e.2'],
    'list_mahasiswa' => ['8.b', '8.c'],
    'list_prestasi_mahasiswa' => ['8.b.1', '8.b.2'],
],

];
