<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_pendidikan_dosen', function (Blueprint $table) {
            $table->id();
            $table->string('id_dosen', 255)->nullable();
            $table->string('nidn', 255)->nullable();
            $table->string('nuptk', 255)->nullable();
            $table->string('nama_dosen', 255)->nullable();
            $table->string('id_bidang_studi', 255)->nullable();
            $table->string('nama_bidang_studi', 255)->nullable();
            $table->string('id_jenjang_pendidikan', 255)->nullable();
            $table->string('nama_jenjang_pendidikan', 255)->nullable();
            $table->string('id_gelar_akademik', 255)->nullable();
            $table->string('nama_gelar_akademik', 255)->nullable();
            $table->string('id_perguruan_tinggi', 255)->nullable();
            $table->string('nama_perguruan_tinggi', 255)->nullable();
            $table->string('fakultas', 255)->nullable();
            $table->string('tahun_lulus', 255)->nullable();
            $table->string('sks_lulus', 255)->nullable();
            $table->string('ipk', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_pendidikan_dosen');
    }
};
