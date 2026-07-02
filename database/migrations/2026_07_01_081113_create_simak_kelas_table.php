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
        Schema::create('simak_kelas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nm_fakultas')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('nm_prodi_simak')->nullable();
            $table->string('KLS_KODE')->nullable();
            $table->string('KLS_NAMA')->nullable();
            $table->string('KLS_KODEMK')->nullable();
            $table->string('KLS_TAK')->nullable();
            $table->string('KLS_PS')->nullable();
            $table->string('KLS_DSN')->nullable();
            $table->string('KLS_DSN2')->nullable();
            $table->string('KLS_DSN3')->nullable();
            $table->string('KLS_DSN4')->nullable();
            $table->string('KLS_DSN5')->nullable();
            $table->string('KLS_DSN6')->nullable();
            $table->string('KLS_HARI')->nullable();
            $table->string('KLS_JAM1')->nullable();
            $table->string('KLS_JAM2')->nullable();
            $table->string('KLS_RUANG')->nullable();
            $table->string('KLS_KPS')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simak_kelas');
    }
};
