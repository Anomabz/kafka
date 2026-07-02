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
        Schema::create('wilayah', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_level_wilayah')->nullable();
            $table->string('id_induk_wilayah')->nullable();
            $table->string('id_wilayah')->nullable();
            $table->string('id_negara')->nullable();
            $table->string('nama_wilayah')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wilayah');
    }
};
