<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lppm_pengabdian_dosen', function (Blueprint $table): void {
            $table->id();
            $table->string('id_ketua', 30);
            $table->string('nama_skema');
            $table->text('judul');
            $table->year('tahun');
            $table->json('anggota_non_dosen');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lppm_pengabdian_dosen');
    }
};
