<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mahasiswa_lulus_do', function (Blueprint $table) {
            $table->string('id_registrasi_mahasiswa', 255)->primary();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nama_program_studi', 255)->nullable();
            $table->string('id_mahasiswa', 255)->nullable();
            $table->string('nim', 255)->nullable();
            $table->string('nama_mahasiswa', 255)->nullable();
            $table->string('angkatan', 255)->nullable();
            $table->string('id_jenis_keluar', 255)->nullable();
            $table->string('nama_jenis_keluar', 255)->nullable();
            $table->date('tanggal_keluar')->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->string('nomor_sk_yudisium', 255)->nullable();
            $table->datetime('tanggal_sk_yudisium')->nullable();
            $table->double('ipk', 5, 2)->nullable();
            $table->string('nomor_ijazah', 255)->nullable();
            $table->integer('jalur_skripsi')->nullable();
            $table->text('judul_skripsi')->nullable();
            $table->date('bulan_awal_bimbingan')->nullable();
            $table->date('bulan_akhir_bimbingan')->nullable();
            $table->string('id_dosen', 255)->nullable();
            $table->string('nidn', 255)->nullable();
            $table->string('nama_dosen', 255)->nullable();
            $table->string('pembimbing_ke', 255)->nullable();
            $table->string('asal_ijazah', 255)->nullable();
            $table->string('id_periode_keluar', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahasiswa_lulus_do');
    }
};
