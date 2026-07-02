<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah', function (Blueprint $table) {
            $table->id();
            $table->string('id_level_wilayah', 255)->nullable();
            $table->string('id_induk_wilayah', 255)->nullable();
            $table->string('id_wilayah', 255)->nullable();
            $table->string('id_negara', 255)->nullable();
            $table->string('nama_wilayah', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah');
    }
};
