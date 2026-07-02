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
        Schema::create('riwayat_fungsional_dosen', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_dosen')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nuptk')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('id_jabatan_fungsional')->nullable();
            $table->string('nama_jabatan_fungsional')->nullable();
            $table->string('sk_jabatan_fungsional')->nullable();
            $table->date('mulai_sk_jabatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_fungsional_dosen');
    }
};
