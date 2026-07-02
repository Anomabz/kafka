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
        Schema::table('mahasiswa_bimbingan_tugas_akhir', function (Blueprint $table) {
            $table->foreign(['dosen_id'])->references(['id'])->on('dosen')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['program_studi_id'])->references(['id'])->on('program_studi')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mahasiswa_bimbingan_tugas_akhir', function (Blueprint $table) {
            $table->dropForeign('mahasiswa_bimbingan_tugas_akhir_dosen_id_foreign');
            $table->dropForeign('mahasiswa_bimbingan_tugas_akhir_program_studi_id_foreign');
        });
    }
};
