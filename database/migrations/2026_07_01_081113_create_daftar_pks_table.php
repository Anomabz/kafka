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
        Schema::create('daftar_pks', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('unit_pelaksana')->nullable();
            $table->string('nama_perguruan_tinggi_lembaga')->nullable();
            $table->string('no_kerjasama')->nullable();
            $table->date('periode_mulai')->nullable();
            $table->date('periode_berakhir')->nullable();
            $table->text('kegiatan_kerjasama')->nullable();
            $table->bigInteger('nominal')->nullable();
            $table->string('jangka_waktu')->nullable();
            $table->string('jenis_mitra')->nullable();
            $table->year('tahun')->nullable();
            $table->string('status_sikerma')->nullable();
            $table->string('link_repo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daftar_pks');
    }
};
