<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_studi', function (Blueprint $table) {
            $table->id();
            $table->string('id_prodi', 255)->nullable();
            $table->string('kode_program_studi', 255)->nullable();
            $table->string('nama_program_studi', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->integer('id_jenjang_pendidikan')->nullable();
            $table->string('nama_jenjang_pendidikan', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_studi');
    }
};
