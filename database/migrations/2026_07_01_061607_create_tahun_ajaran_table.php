<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id();
            $table->integer('id_tahun_ajaran')->nullable();
            $table->string('nama_tahun_ajaran', 255)->nullable();
            $table->integer('a_periode_aktif')->nullable();
            $table->string('tanggal_mulai', 255)->nullable();
            $table->string('tanggal_selesai', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahun_ajaran');
    }
};
