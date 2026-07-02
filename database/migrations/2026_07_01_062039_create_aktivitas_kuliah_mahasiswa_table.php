<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aktivitas_kuliah_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->string('id_registrasi_mahasiswa', 255)->nullable();
            $table->string('id_mahasiswa', 255)->nullable();
            $table->string('id_semester', 255)->nullable();
            $table->string('nama_semester', 255)->nullable();
            $table->string('nim', 255)->nullable();
            $table->string('nama_mahasiswa', 255)->nullable();
            $table->string('angkatan', 255)->nullable();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nama_program_studi', 255)->nullable();
            $table->string('id_status_mahasiswa', 255)->nullable();
            $table->string('nama_status_mahasiswa', 255)->nullable();
            $table->string('ips', 255)->nullable();
            $table->string('ipk', 255)->nullable();
            $table->string('sks_semester', 255)->nullable();
            $table->string('sks_total', 255)->nullable();
            $table->string('biaya_kuliah_smt', 255)->nullable();
            $table->string('status_sync', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitas_kuliah_mahasiswa');
    }
};
