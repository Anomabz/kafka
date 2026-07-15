<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel ini juga dibuat oleh proses sinkronisasi eksternal (di luar
        // migration Laravel) di database produksi/staging yang sudah berisi
        // data. Guard supaya migrate tidak gagal dengan "table already
        // exists" di sana; di database baru (mis. sqlite untuk testing)
        // migration ini yang membuat skemanya.
        if (Schema::hasTable('dosen_pembimbing')) {
            return;
        }

        Schema::create('dosen_pembimbing', function (Blueprint $table) {
            $table->string('id_registrasi_mahasiswa', 255)->nullable()->index('idx_reg_mahasiswa');
            $table->string('id_kategori_kegiatan', 255)->nullable();
            $table->string('id_bimbing_mahasiswa', 255)->nullable();
            $table->string('nama_mahasiswa', 255)->nullable();
            $table->string('nim', 255)->nullable();
            $table->string('id_dosen', 255)->nullable()->index('idx_dosen');
            $table->string('nidn', 255)->nullable();
            $table->string('nuptk', 255)->nullable();
            $table->string('nama_dosen', 255)->nullable();
            $table->string('pembimbing_ke', 255)->nullable();
            $table->string('id_aktivitas', 255)->nullable();
            $table->string('jenis_aktivitas', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dosen_pembimbing');
    }
};
