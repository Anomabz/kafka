<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_kurikulum', function (Blueprint $table) {
            $table->id();
            $table->string('id_jenj_didik', 255)->nullable();
            $table->string('jml_sem_normal', 255)->nullable();
            $table->string('id_kurikulum', 255)->nullable();
            $table->string('nama_kurikulum', 255)->nullable();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nama_program_studi', 255)->nullable();
            $table->string('id_semester', 255)->nullable();
            $table->string('semester_mulai_berlaku', 255)->nullable();
            $table->string('jumlah_sks_lulus', 255)->nullable();
            $table->string('jumlah_sks_wajib', 255)->nullable();
            $table->string('jumlah_sks_pilihan', 255)->nullable();
            $table->string('jumlah_sks_mata_kuliah_wajib', 255)->nullable();
            $table->string('jumlah_sks_mata_kuliah_pilihan', 255)->nullable();
            $table->string('status_sync', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_kurikulum');
    }
};
