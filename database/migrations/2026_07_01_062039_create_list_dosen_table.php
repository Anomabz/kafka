<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_dosen', function (Blueprint $table) {
            $table->id();
            $table->string('id_dosen', 255)->nullable();
            $table->string('nama_dosen', 255)->nullable();
            $table->string('nidn', 255)->nullable();
            $table->string('nip', 255)->nullable();
            $table->string('nuptk', 255)->nullable();
            $table->string('jenis_kelamin', 255)->nullable();
            $table->string('id_agama', 255)->nullable();
            $table->string('nama_agama', 255)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('id_status_aktif', 255)->nullable();
            $table->string('nama_status_aktif', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_dosen');
    }
};
