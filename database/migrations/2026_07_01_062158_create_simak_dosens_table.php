<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simak_dosens', function (Blueprint $table) {
            $table->id();
            $table->string('nm_fakultas', 255)->nullable();
            $table->string('simak_fakultas', 255)->nullable();
            $table->string('FCOD', 255)->nullable();
            $table->string('FNIP', 255)->nullable();
            $table->string('FNAM', 255)->nullable();
            $table->string('MPK', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simak_dosens');
    }
};
