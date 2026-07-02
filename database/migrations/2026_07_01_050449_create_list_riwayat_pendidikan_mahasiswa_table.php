<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('list_riwayat_pendidikan_mahasiswa', function (Blueprint $table) {
            $table->id(); // ID Auto Increment bawaan Laravel

            $table->string('id_registrasi_mahasiswa', 255)->nullable();
            $table->string('id_mahasiswa', 255)->nullable();
            $table->string('nim', 255)->nullable();
            $table->string('nama_mahasiswa', 255)->nullable();
            $table->string('id_jenis_daftar', 255)->nullable();
            $table->string('nama_jenis_daftar', 255)->nullable();
            $table->string('id_jalur_daftar', 255)->nullable();
            $table->string('id_periode_masuk', 255)->nullable();
            $table->string('nama_periode_masuk', 255)->nullable();
            $table->string('id_jenis_keluar', 255)->nullable();
            $table->string('keterangan_keluar', 255)->nullable();
            $table->string('id_perguruan_tinggi', 255)->nullable();
            $table->string('nama_perguruan_tinggi', 255)->nullable();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nama_program_studi', 255)->nullable();
            $table->string('sks_diakui', 255)->nullable();
            $table->string('id_perguruan_tinggi_asal', 255)->nullable();
            $table->string('nama_perguruan_tinggi_asal', 255)->nullable();
            $table->string('id_prodi_asal', 255)->nullable();
            $table->string('nama_program_studi_asal', 255)->nullable();
            $table->string('jenis_kelamin', 255)->nullable();
            $table->string('tanggal_daftar', 255)->nullable(); // Sesuai gambar: varchar(255)
            $table->string('nama_ibu_kandung', 255)->nullable();
            $table->string('id_pembiayaan', 255)->nullable();
            $table->string('nama_pembiayaan_awal', 255)->nullable();
            $table->string('biaya_masuk', 255)->nullable();
            $table->string('id_bidang_minat', 255)->nullable();
            $table->string('nm_bidang_minat', 255)->nullable();
            $table->string('id_periode_keluar', 255)->nullable();
            $table->string('tanggal_keluar', 255)->nullable(); // Sesuai gambar: varchar(255)
            $table->string('last_update', 255)->nullable();
            $table->string('tgl_create', 255)->nullable();
            $table->string('status_sync', 255)->nullable();

            $table->timestamps(); // Menghasilkan kolom created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_riwayat_pendidikan_mahasiswa');
    }
};
