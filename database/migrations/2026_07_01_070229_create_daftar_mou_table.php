<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daftar_mou', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('perguruan_tinggi_lembaga', 255)->nullable();
            $table->string('no_kerjasama', 255)->nullable();
            $table->date('mulai')->nullable();
            $table->date('berakhir')->nullable();
            $table->text('kegiatan_kerjasama')->nullable();
            $table->string('jenis_mitra', 255)->nullable();
            $table->year('tahun')->nullable();
            $table->string('link_repo', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daftar_mou');
    }
};
