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
        Schema::create('semester', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_semester')->nullable();
            $table->integer('id_tahun_ajaran')->nullable();
            $table->string('nama_semester')->nullable();
            $table->integer('semester')->nullable();
            $table->integer('a_periode_aktif')->nullable();
            $table->string('tanggal_mulai')->nullable();
            $table->string('tanggal_selesai')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semester');
    }
};
