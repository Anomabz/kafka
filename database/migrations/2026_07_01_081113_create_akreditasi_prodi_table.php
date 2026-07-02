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
        Schema::create('akreditasi_prodi', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('kode_prodi')->nullable();
            $table->string('nama_prodi')->nullable();
            $table->string('jenjang')->nullable();
            $table->string('no_sk')->nullable();
            $table->year('tahun')->nullable();
            $table->string('peringkat')->nullable();
            $table->date('tanggal_kadaluarsa')->nullable();
            $table->string('jenis_akreditasi')->nullable();
            $table->string('penyelenggara_akreditasi_internasional')->nullable();
            $table->text('link_repo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('akreditasi_prodi');
    }
};
