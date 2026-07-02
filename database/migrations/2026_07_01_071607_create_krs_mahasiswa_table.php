<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('krs_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->string('id_registrasi_mahasiswa', 255)->nullable();
            $table->string('id_periode', 255)->nullable();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nama_program_studi', 255)->nullable();
            $table->string('id_matkul', 255)->nullable();
            $table->string('kode_mata_kuliah', 255)->nullable();
            $table->string('nama_mata_kuliah', 255)->nullable();
            $table->string('id_kelas', 255)->nullable();
            $table->string('nama_kelas_kuliah', 255)->nullable();
            $table->string('sks_mata_kuliah', 255)->nullable();
            $table->string('nim', 255)->nullable();
            $table->string('nama_mahasiswa', 255)->nullable();
            $table->string('angkatan', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_mahasiswa');
    }
};
