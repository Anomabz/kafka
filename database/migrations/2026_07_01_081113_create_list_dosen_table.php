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
        Schema::create('list_dosen', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_dosen')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nip')->nullable();
            $table->string('nuptk')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('id_agama')->nullable();
            $table->string('nama_agama')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('id_status_aktif')->nullable();
            $table->string('nama_status_aktif')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_dosen');
    }
};
