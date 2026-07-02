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
        Schema::create('sister_sdm', function (Blueprint $table) {
            $table->string('id_sdm')->primary();
            $table->string('nama_sdm')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nip')->nullable();
            $table->string('nuptk')->nullable();
            $table->dateTime('waktu_data_update')->nullable();
            $table->string('nama_status_aktif')->nullable();
            $table->string('nama_status_pegawai')->nullable();
            $table->string('jenis_sdm')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sister_sdm');
    }
};
