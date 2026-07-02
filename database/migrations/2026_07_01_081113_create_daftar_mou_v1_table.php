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
        Schema::create('daftar_mou_v1', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('perguruan_tinggi_lembaga');
            $table->string('no_kerjasama')->nullable();
            $table->date('mulai')->nullable();
            $table->date('berakhir')->nullable();
            $table->text('kegiatan_kerjasama')->nullable();
            $table->string('jenis_mitra')->nullable();
            $table->year('tahun')->nullable();
            $table->string('link_repo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daftar_mou_v1');
    }
};
