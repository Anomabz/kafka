<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester', function (Blueprint $table) {
            $table->id();
            $table->string('id_semester', 255)->nullable();
            $table->integer('id_tahun_ajaran')->nullable();
            $table->string('nama_semester', 255)->nullable();
            $table->integer('semester')->nullable();
            $table->integer('a_periode_aktif')->nullable();
            $table->string('tanggal_mulai', 255)->nullable();
            $table->string('tanggal_selesai', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester');
    }
};
