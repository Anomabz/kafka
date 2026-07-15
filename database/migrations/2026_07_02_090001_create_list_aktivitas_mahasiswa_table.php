<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sama seperti dosen_pembimbing: tabel ini juga dibuat oleh proses
        // sinkronisasi eksternal di database produksi/staging. Guard supaya
        // migrate tidak gagal di sana; di database baru (sqlite testing)
        // migration ini yang membuat skemanya.
        if (Schema::hasTable('list_aktivitas_mahasiswa')) {
            return;
        }

        Schema::create('list_aktivitas_mahasiswa', function (Blueprint $table) {
            $table->string('id_aktivitas', 255)->nullable()->index('idx_aktivitas');
            $table->string('jenis_anggota', 255)->nullable();
            $table->string('nama_jenis_anggota', 255)->nullable();
            $table->string('id_jenis_aktivitas', 255)->nullable();
            $table->string('nama_jenis_aktivitas', 255)->nullable();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nama_prodi', 255)->nullable();
            $table->string('id_semester', 255)->nullable();
            $table->string('nama_semester', 255)->nullable();
            $table->text('judul')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('lokasi', 255)->nullable();
            $table->string('sk_tugas', 255)->nullable();
            $table->string('sumber_data', 255)->nullable();
            $table->string('tanggal_sk_tugas', 255)->nullable();
            $table->string('untuk_kampus_merdeka', 255)->nullable();
            $table->string('asal_data', 255)->nullable();
            $table->string('nama_program_mbkm', 255)->nullable();
            $table->string('nm_asaldata', 255)->nullable();
            $table->string('program_mbkm', 255)->nullable();
            $table->string('tanggal_mulai', 255)->nullable();
            $table->string('tanggal_selesai', 255)->nullable();
            $table->string('status_sync', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_aktivitas_mahasiswa');
    }
};
