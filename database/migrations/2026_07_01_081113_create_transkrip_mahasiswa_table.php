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
        Schema::create('transkrip_mahasiswa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_registrasi_mahasiswa')->nullable();
            $table->string('id_matkul')->nullable();
            $table->string('id_kelas_kuliah')->nullable();
            $table->string('id_nilai_transfer')->nullable();
            $table->string('id_konversi_aktivitas')->nullable();
            $table->string('smt_diambil')->nullable();
            $table->string('kode_mata_kuliah')->nullable();
            $table->string('nama_mata_kuliah')->nullable();
            $table->string('sks_mata_kuliah')->nullable();
            $table->string('nilai_angka')->nullable();
            $table->string('nilai_huruf')->nullable();
            $table->string('nilai_indeks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transkrip_mahasiswa');
    }
};
