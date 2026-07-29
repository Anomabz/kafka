<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_prestasi_mahasiswa', function (Blueprint $table) {
            $table->string('id_prestasi')->nullable()->index();
            $table->string('id_mahasiswa')->nullable()->index();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('id_jenis_prestasi')->nullable();
            $table->string('nama_jenis_prestasi')->nullable();
            $table->string('id_tingkat_prestasi')->nullable();
            $table->string('nama_tingkat_prestasi')->nullable();
            $table->string('nama_prestasi')->nullable();
            $table->string('tahun_prestasi')->nullable()->index();
            $table->string('penyelenggara')->nullable();
            $table->string('peringkat')->nullable();
            $table->string('id_aktivitas')->nullable();
            $table->string('status_sync')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_prestasi_mahasiswa');
    }
};
