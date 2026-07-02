<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akreditasi_prodi', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('kode_prodi', 255)->nullable();
            $table->string('nama_prodi', 255)->nullable();
            $table->string('jenjang', 255)->nullable();
            $table->string('no_sk', 255)->nullable();
            $table->year('tahun')->nullable();
            $table->string('peringkat', 255)->nullable();
            $table->date('tanggal_kadaluarsa')->nullable();
            $table->string('jenis_akreditasi', 255)->nullable();
            $table->string('penyelenggara_akreditasi_internasional', 255)->nullable();
            $table->text('link_repo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akreditasi_prodi');
    }
};
