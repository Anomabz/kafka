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
        Schema::create('simak_dosens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nm_fakultas')->nullable();
            $table->string('simak_fakultas')->nullable();
            $table->string('FCOD')->nullable();
            $table->string('FNIP')->nullable();
            $table->string('FNAM')->nullable();
            $table->string('MPK')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simak_dosens');
    }
};
