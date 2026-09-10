<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sama seperti sister_detail_publikasi (skema hampir identik, satu
        // keluarga luaran SISTER): tabel ini sudah ada di database
        // produksi/staging (berisi data teknologi tepat guna, karya seni,
        // HKI, dst). Guard supaya migrate tidak gagal di sana; di database
        // baru (sqlite testing) migration ini yang membuat skemanya.
        //
        // sister_list_kekayaan_intelektual (tabel per-penulis, pasangan
        // tabel ini seperti sister_list_publikasi) TIDAK dimigrasikan --
        // tidak dipakai, sister_detail_kekayaan_intelektual sudah mandiri
        // (kolom `penulis` berisi JSON daftar penulis sendiri).
        if (Schema::hasTable('sister_detail_kekayaan_intelektual')) {
            return;
        }

        Schema::create('sister_detail_kekayaan_intelektual', function (Blueprint $table) {
            $table->string('id_list', 255)->nullable()->index();
            $table->string('id', 255)->nullable()->index();
            $table->string('id_jenis_publikasi', 255)->nullable()->index();
            $table->longText('judul')->nullable();
            $table->longText('judul_artikel')->nullable();
            $table->longText('judul_asli')->nullable();
            $table->string('nama_jurnal', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('edisi', 255)->nullable();
            $table->string('volume', 255)->nullable();
            $table->string('nomor', 255)->nullable();
            $table->string('halaman', 255)->nullable();
            $table->string('jumlah_halaman', 255)->nullable();
            $table->string('penerbit', 255)->nullable();
            $table->string('seminar', 255)->nullable();
            $table->string('prosiding', 255)->nullable();
            $table->string('nomor_paten', 255)->nullable();
            $table->string('pemberi_paten', 255)->nullable();
            $table->string('doi', 255)->nullable();
            $table->string('isbn', 255)->nullable();
            $table->string('issn', 255)->nullable();
            $table->string('e_issn', 255)->nullable();
            $table->longText('tautan')->nullable();
            $table->longText('keterangan')->nullable();
            $table->string('id_litabmas', 255)->nullable();
            $table->string('id_kategori_capaian_luaran', 255)->nullable()->index();
            $table->string('quartile', 255)->nullable();
            $table->longText('kategori_kegiatan')->nullable();
            $table->string('jenis_publikasi', 255)->nullable();
            $table->longText('judul_litabmas')->nullable();
            $table->string('kategori_capaian_luaran', 255)->nullable();
            $table->string('id_kategori_kegiatan', 255)->nullable();
            $table->longText('bidang_keilmuan')->nullable();
            $table->string('asal_data', 255)->nullable();
            $table->longText('penulis')->nullable();
            $table->longText('dokumen')->nullable();
            $table->longText('message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sister_detail_kekayaan_intelektual');
    }
};
