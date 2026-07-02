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
        Schema::create('mahasiswa_bimbingan_tugas_akhir', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('mahasiswa_id')->nullable();
            $table->string('nim', 64)->nullable()->index();
            $table->string('nama_mahasiswa');
            $table->unsignedBigInteger('program_studi_id');
            $table->unsignedBigInteger('dosen_id');
            $table->string('judul_tugas_akhir')->nullable();
            $table->string('jenis_tugas_akhir', 128)->nullable();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('status', 128)->nullable();
            $table->string('sumber_data')->nullable();
            $table->timestamps();

            $table->index(['dosen_id', 'tahun']);
            $table->index(['program_studi_id', 'tahun']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mahasiswa_bimbingan_tugas_akhir');
    }
};
