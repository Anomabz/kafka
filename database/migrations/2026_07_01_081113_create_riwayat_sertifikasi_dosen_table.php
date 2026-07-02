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
        Schema::create('riwayat_sertifikasi_dosen', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_dosen')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nuptk')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('nomor_peserta')->nullable();
            $table->string('id_bidang_studi')->nullable();
            $table->string('nama_bidang_studi')->nullable();
            $table->string('id_jenis_sertifikasi')->nullable();
            $table->string('nama_jenis_sertifikasi')->nullable();
            $table->string('tahun_sertifikasi')->nullable();
            $table->string('sk_sertifikasi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_sertifikasi_dosen');
    }
};
