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
        Schema::create('riwayat_nilai_mahasiswa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_registrasi_mahasiswa')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('id_periode')->nullable();
            $table->string('id_matkul')->nullable();
            $table->string('nama_mata_kuliah')->nullable();
            $table->string('id_kelas')->nullable();
            $table->string('nama_kelas_kuliah')->nullable();
            $table->string('sks_mata_kuliah')->nullable();
            $table->string('nilai_angka')->nullable();
            $table->string('nilai_huruf')->nullable();
            $table->string('nilai_indeks')->nullable();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('angkatan')->nullable();
            $table->string('status_sync')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_nilai_mahasiswa');
    }
};
