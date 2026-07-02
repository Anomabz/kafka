<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_sertifikasi_dosen', function (Blueprint $table) {
            $table->id();
            $table->string('id_dosen', 255)->nullable();
            $table->string('nidn', 255)->nullable();
            $table->string('nuptk', 255)->nullable();
            $table->string('nama_dosen', 255)->nullable();
            $table->string('nomor_peserta', 255)->nullable();
            $table->string('id_bidang_studi', 255)->nullable();
            $table->string('nama_bidang_studi', 255)->nullable();
            $table->string('id_jenis_sertifikasi', 255)->nullable();
            $table->string('nama_jenis_sertifikasi', 255)->nullable();
            $table->string('tahun_sertifikasi', 255)->nullable();
            $table->string('sk_sertifikasi', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_sertifikasi_dosen');
    }
};
