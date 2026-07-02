<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_kelas_kuliah', function (Blueprint $table) {
            $table->id();
            $table->string('id_kelas_kuliah', 255)->nullable();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nama_program_studi', 255)->nullable();
            $table->string('id_semester', 255)->nullable();
            $table->string('nama_semester', 255)->nullable();
            $table->string('id_matkul', 255)->nullable();
            $table->string('kode_mata_kuliah', 255)->nullable();
            $table->string('nama_mata_kuliah', 255)->nullable();
            $table->string('nama_kelas_kuliah', 255)->nullable();
            $table->string('sks', 255)->nullable();
            $table->text('id_dosen')->nullable();
            $table->text('nama_dosen')->nullable();
            $table->string('jumlah_mahasiswa', 255)->nullable();
            $table->string('apa_untuk_pditt', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_kelas_kuliah');
    }
};
