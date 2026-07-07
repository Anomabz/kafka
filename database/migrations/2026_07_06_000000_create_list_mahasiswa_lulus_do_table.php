<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sama seperti dosen_pembimbing: tabel ini dibuat oleh proses
        // sinkronisasi eksternal di database produksi/staging (berisi ~168rb
        // baris lulusan/DO). Guard supaya migrate tidak gagal di sana; di
        // database baru (sqlite testing) migration ini yang membuat skemanya.
        if (Schema::hasTable('list_mahasiswa_lulus_do')) {
            return;
        }

        Schema::create('list_mahasiswa_lulus_do', function (Blueprint $table) {
            $table->string('id_registrasi_mahasiswa', 255)->nullable()->index('idx_lulus_reg');
            $table->string('id_mahasiswa', 255)->nullable();
            $table->string('id_perguruan_tinggi', 255)->nullable();
            $table->string('id_prodi', 255)->nullable()->index('idx_lulus_prodi');
            $table->string('tgl_masuk_sp', 255)->nullable();
            $table->string('tgl_keluar', 255)->nullable();
            $table->string('tgl_sk_yudisium', 255)->nullable();
            $table->string('ipk', 255)->nullable();
            $table->string('nim', 255)->nullable();
            $table->string('nama_mahasiswa', 255)->nullable();
            $table->string('nama_program_studi', 255)->nullable();
            $table->string('angkatan', 255)->nullable();
            $table->string('id_jenis_keluar', 255)->nullable();
            $table->string('nama_jenis_keluar', 255)->nullable()->index('idx_lulus_jenis');
            $table->string('tanggal_keluar', 255)->nullable();
            $table->string('id_periode_keluar', 255)->nullable()->index('idx_lulus_periode');
            $table->text('judul_skripsi')->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->string('status_sync', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_mahasiswa_lulus_do');
    }
};
