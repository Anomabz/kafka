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
        Schema::create('riwayat_penelitian_dosen', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_dosen')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nuptk')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('id_penelitian')->nullable();
            $table->text('judul_penelitian')->nullable();
            $table->string('id_kelompok_bidang')->nullable();
            $table->string('kode_kelompok_bidang')->nullable();
            $table->string('nama_kelompok_bidang')->nullable();
            $table->string('id_lembaga_iptek')->nullable();
            $table->string('nama_lembaga_iptek')->nullable();
            $table->string('tahun_kegiatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_penelitian_dosen');
    }
};
