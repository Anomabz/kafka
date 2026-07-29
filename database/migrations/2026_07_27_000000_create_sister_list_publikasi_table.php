<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sister_list_publikasi', function (Blueprint $table) {
            $table->string('id_sdm', 255)->nullable()->index();
            $table->string('id_kategori_kegiatan', 255)->nullable();
            $table->string('a_klaim_bkd', 255)->nullable();
            $table->string('wkt_klaim_bkd', 255)->nullable();
            $table->string('id', 255)->nullable()->index();
            $table->longText('judul')->nullable();
            $table->string('quartile', 255)->nullable();
            $table->string('jenis_publikasi', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->longText('kategori_kegiatan')->nullable();
            $table->string('asal_data', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sister_list_publikasi');
    }
};
