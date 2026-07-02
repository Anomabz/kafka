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
        Schema::create('riwayat_pangkat_dosen', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_dosen')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nuptk')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('id_pangkat_golongan')->nullable();
            $table->string('nama_pangkat_golongan')->nullable();
            $table->string('sk_pangkat')->nullable();
            $table->date('tanggal_sk_pangkat')->nullable();
            $table->date('mulai_sk_pangkat')->nullable();
            $table->string('masa_kerja_dalam_tahun')->nullable();
            $table->string('masa_kerja_dalam_bulan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_pangkat_dosen');
    }
};
