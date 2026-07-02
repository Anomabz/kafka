<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simak_kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nm_fakultas', 255)->nullable();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nm_prodi_simak', 255)->nullable();
            $table->string('KLS_KODE', 255)->nullable();
            $table->string('KLS_NAMA', 255)->nullable();
            $table->string('KLS_KODEMK', 255)->nullable();
            $table->string('KLS_TAK', 255)->nullable();
            $table->string('KLS_PS', 255)->nullable();
            $table->string('KLS_DSN', 255)->nullable();
            $table->string('KLS_DSN2', 255)->nullable();
            $table->string('KLS_DSN3', 255)->nullable();
            $table->string('KLS_DSN4', 255)->nullable();
            $table->string('KLS_DSN5', 255)->nullable();
            $table->string('KLS_DSN6', 255)->nullable();
            $table->string('KLS_HARI', 255)->nullable();
            $table->string('KLS_JAM1', 255)->nullable();
            $table->string('KLS_JAM2', 255)->nullable();
            $table->string('KLS_RUANG', 255)->nullable();
            $table->string('KLS_KPS', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simak_kelas');
    }
};
