<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_penelitian_dosen', function (Blueprint $table) {
            $table->id();
            $table->string('id_dosen', 255)->nullable();
            $table->string('nidn', 255)->nullable();
            $table->string('nuptk', 255)->nullable();
            $table->string('nama_dosen', 255)->nullable();
            $table->string('id_penelitian', 255)->nullable();
            $table->text('judul_penelitian')->nullable();
            $table->string('id_kelompok_bidang', 255)->nullable();
            $table->string('kode_kelompok_bidang', 255)->nullable();
            $table->string('nama_kelompok_bidang', 255)->nullable();
            $table->string('id_lembaga_iptek', 255)->nullable();
            $table->string('nama_lembaga_iptek', 255)->nullable();
            $table->string('tahun_kegiatan', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_penelitian_dosen');
    }
};
