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
       Schema::create('lppm_penelitian_dosen', function (Blueprint $table) {
        $table->id();

        $table->string('id_ketua', 20);
        $table->string('nama_skema');
        $table->text('judul');
        $table->year('tahun');
        $table->longText('anggota_non_dosen')->nullable();
        $table->timestamps();

        $table->unique(['id_ketua', 'tahun']);
    });
    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lppm_penelitian_dosen');
    }
};
