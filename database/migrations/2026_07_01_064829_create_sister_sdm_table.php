<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sister_sdm', function (Blueprint $table) {
            $table->string('id_sdm', 255)->primary(); // varchar(255) primary
            $table->string('nama_sdm', 255)->nullable();
            $table->string('nidn', 255)->nullable();
            $table->string('nip', 255)->nullable();
            $table->string('nuptk', 255)->nullable();
            $table->datetime('waktu_data_update')->nullable();
            $table->string('nama_status_aktif', 255)->nullable();
            $table->string('nama_status_pegawai', 255)->nullable();
            $table->string('jenis_sdm', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sister_sdm');
    }
};
