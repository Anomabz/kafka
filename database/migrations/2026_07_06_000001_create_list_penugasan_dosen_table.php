<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dibuat oleh proses sinkronisasi eksternal di produksi/staging (~32rb
        // baris penugasan dosen). Guard supaya migrate tidak gagal di sana; di
        // database baru (sqlite testing) migration ini yang membuat skemanya.
        if (Schema::hasTable('list_penugasan_dosen')) {
            return;
        }

        Schema::create('list_penugasan_dosen', function (Blueprint $table) {
            $table->string('id_registrasi_dosen', 255)->nullable();
            $table->string('jk', 255)->nullable();
            $table->string('id_dosen', 255)->nullable()->index('idx_penugasan_dosen');
            $table->string('nama_dosen', 255)->nullable();
            $table->string('nidn', 255)->nullable();
            $table->string('nuptk', 255)->nullable();
            $table->string('id_tahun_ajaran', 255)->nullable();
            $table->string('nama_tahun_ajaran', 255)->nullable()->index('idx_penugasan_ta');
            $table->string('id_perguruan_tinggi', 255)->nullable();
            $table->string('nama_perguruan_tinggi', 255)->nullable();
            $table->string('id_prodi', 255)->nullable()->index('idx_penugasan_prodi');
            $table->string('nama_program_studi', 255)->nullable();
            $table->string('id_stat_pegawai', 255)->nullable();
            $table->string('a_sp_homebase', 255)->nullable()->index('idx_penugasan_homebase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_penugasan_dosen');
    }
};
