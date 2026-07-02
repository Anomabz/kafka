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
        Schema::create('mahasiswa_lulus_do', function (Blueprint $table) {
            $table->string('id_registrasi_mahasiswa')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('id_mahasiswa')->nullable();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('angkatan')->nullable();
            $table->string('id_jenis_keluar')->nullable();
            $table->string('nama_jenis_keluar')->nullable();
            $table->date('tanggal_keluar')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('nomor_sk_yudisium')->nullable();
            $table->dateTime('tanggal_sk_yudisium')->nullable();
            $table->double('ipk')->nullable();
            $table->string('nomor_ijazah')->nullable();
            $table->integer('jalur_skripsi')->nullable();
            $table->text('judul_skripsi')->nullable();
            $table->date('bulan_awal_bimbingan')->nullable();
            $table->date('bulan_akhir_bimbingan')->nullable();
            $table->string('id_dosen')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('pembimbing_ke')->nullable();
            $table->string('asal_ijazah')->nullable();
            $table->string('id_periode_keluar')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mahasiswa_lulus_do');
    }
};
