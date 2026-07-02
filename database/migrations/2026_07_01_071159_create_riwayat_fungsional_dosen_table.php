<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_fungsional_dosen', function (Blueprint $table) {
            $table->id();
            $table->string('id_dosen', 255)->nullable();
            $table->string('nidn', 255)->nullable();
            $table->string('nuptk', 255)->nullable();
            $table->string('nama_dosen', 255)->nullable();
            $table->string('id_jabatan_fungsional', 255)->nullable();
            $table->string('nama_jabatan_fungsional', 255)->nullable();
            $table->string('sk_jabatan_fungsional', 255)->nullable();
            $table->date('mulai_sk_jabatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_fungsional_dosen');
    }
};
