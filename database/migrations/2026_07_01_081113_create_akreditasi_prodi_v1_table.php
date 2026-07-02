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
        Schema::create('akreditasi_prodi_v1', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_prodi', 20);
            $table->string('nama_prodi');
            $table->string('jenjang', 10)->nullable();
            $table->string('no_sk', 100)->nullable();
            $table->year('tahun')->nullable();
            $table->string('peringkat')->nullable();
            $table->date('tanggal_kadaluarsa')->nullable();
            $table->string('jenis_akreditasi')->nullable();
            $table->string('penyelenggara_akreditasi_internasional')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('akreditasi_prodi_v1');
    }
};
