<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mahasiswa_bimbingan_tugas_akhir', function (Blueprint $table): void {
            $table->id();
            $table->string('mahasiswa_id')->nullable();
            $table->string('nim', 64)->nullable()->index();
            $table->string('nama_mahasiswa');
            $table->foreignId('program_studi_id')->constrained('program_studi');
            $table->foreignId('dosen_id')->constrained('dosen');
            $table->string('judul_tugas_akhir')->nullable();
            $table->string('jenis_tugas_akhir', 128)->nullable();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('status', 128)->nullable();
            $table->string('sumber_data')->nullable();
            $table->timestamps();

            $table->index(['program_studi_id', 'tahun']);
            $table->index(['dosen_id', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahasiswa_bimbingan_tugas_akhir');
    }
};
