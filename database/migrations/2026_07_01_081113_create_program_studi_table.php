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
        Schema::create('program_studi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_prodi')->nullable();
            $table->string('kode_program_studi')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('status')->nullable();
            $table->integer('id_jenjang_pendidikan')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_studi');
    }
};
