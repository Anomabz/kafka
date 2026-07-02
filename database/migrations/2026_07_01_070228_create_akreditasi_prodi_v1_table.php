<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akreditasi_prodi_v1', function (Blueprint $table) {
            $table->id(); // bigint(20) auto_increment
            $table->string('kode_prodi', 20);
            $table->string('nama_prodi', 255);
            $table->string('jenjang', 10)->nullable();
            $table->string('no_sk', 100)->nullable();
            $table->year('tahun')->nullable();
            $table->string('peringkat', 255)->nullable();
            $table->date('tanggal_kadaluarsa')->nullable();
            $table->string('jenis_akreditasi', 255)->nullable();
            $table->string('penyelenggara_akreditasi_internasional', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akreditasi_prodi_v1');
    }
};
