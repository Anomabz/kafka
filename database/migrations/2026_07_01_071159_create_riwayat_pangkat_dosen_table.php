<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_pangkat_dosen', function (Blueprint $table) {
            $table->id();
            $table->string('id_dosen', 255)->nullable();
            $table->string('nidn', 255)->nullable();
            $table->string('nuptk', 255)->nullable();
            $table->string('nama_dosen', 255)->nullable();
            $table->string('id_pangkat_golongan', 255)->nullable();
            $table->string('nama_pangkat_golongan', 255)->nullable();
            $table->string('sk_pangkat', 255)->nullable();
            $table->date('tanggal_sk_pangkat')->nullable();
            $table->date('mulai_sk_pangkat')->nullable();
            $table->string('masa_kerja_dalam_tahun', 255)->nullable();
            $table->string('masa_kerja_dalam_bulan', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_pangkat_dosen');
    }
};
