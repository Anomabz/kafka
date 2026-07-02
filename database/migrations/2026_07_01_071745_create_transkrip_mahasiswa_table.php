<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transkrip_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->string('id_registrasi_mahasiswa', 255)->nullable();
            $table->string('id_matkul', 255)->nullable();
            $table->string('id_kelas_kuliah', 255)->nullable();
            $table->string('id_nilai_transfer', 255)->nullable();
            $table->string('id_konversi_aktivitas', 255)->nullable();
            $table->string('smt_diambil', 255)->nullable();
            $table->string('kode_mata_kuliah', 255)->nullable();
            $table->string('nama_mata_kuliah', 255)->nullable();
            $table->string('sks_mata_kuliah', 255)->nullable();
            $table->string('nilai_angka', 255)->nullable();
            $table->string('nilai_huruf', 255)->nullable();
            $table->string('nilai_indeks', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transkrip_mahasiswa');
    }
};
