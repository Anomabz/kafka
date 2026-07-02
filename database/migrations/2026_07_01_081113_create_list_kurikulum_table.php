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
        Schema::create('list_kurikulum', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_jenj_didik')->nullable();
            $table->string('jml_sem_normal')->nullable();
            $table->string('id_kurikulum')->nullable();
            $table->string('nama_kurikulum')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('id_semester')->nullable();
            $table->string('semester_mulai_berlaku')->nullable();
            $table->string('jumlah_sks_lulus')->nullable();
            $table->string('jumlah_sks_wajib')->nullable();
            $table->string('jumlah_sks_pilihan')->nullable();
            $table->string('jumlah_sks_mata_kuliah_wajib')->nullable();
            $table->string('jumlah_sks_mata_kuliah_pilihan')->nullable();
            $table->string('status_sync')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_kurikulum');
    }
};
