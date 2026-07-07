<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan_dosen', function (Blueprint $table) {
            $table->string('id_registrasi_dosen', 255)->primary();
            $table->string('id_dosen', 255)->nullable();
            $table->integer('id_tahun_ajaran')->nullable();
            $table->string('id_perguruan_tinggi', 255)->nullable();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nama_program_studi', 255)->nullable();
            $table->string('nomor_surat_tugas', 255)->nullable();
            $table->date('tanggal_surat_tugas')->nullable();
            $table->date('mulai_surat_tugas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_dosen');
    }
};
