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
        Schema::create('list_kelas_kuliah', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_kelas_kuliah')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('id_semester')->nullable();
            $table->string('nama_semester')->nullable();
            $table->string('id_matkul')->nullable();
            $table->string('kode_mata_kuliah')->nullable();
            $table->string('nama_mata_kuliah')->nullable();
            $table->string('nama_kelas_kuliah')->nullable();
            $table->string('sks')->nullable();
            $table->text('id_dosen')->nullable();
            $table->text('nama_dosen')->nullable();
            $table->string('jumlah_mahasiswa')->nullable();
            $table->string('apa_untuk_pditt')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_kelas_kuliah');
    }
};
