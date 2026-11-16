<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Diisi sinkronisasi PD UNSRI dan sudah ada di produksi; migration ini
        // membuat skemanya di database baru (mis. sqlite untuk testing).
        // Sumber EWMP Pendidikan Tabel 3.a.3 (SaptoEwmpDosenRepository).
        if (Schema::hasTable('dosen_pengajar_kelas_kuliah')) {
            return;
        }

        Schema::create('dosen_pengajar_kelas_kuliah', function (Blueprint $table) {
            $table->string('id_aktivitas_mengajar', 255)->nullable()->index();
            $table->string('id_registrasi_dosen', 255)->nullable()->index();
            $table->string('id_dosen', 255)->nullable()->index();
            $table->string('nidn', 255)->nullable();
            $table->string('nuptk', 255)->nullable();
            $table->string('nama_dosen', 255)->nullable();
            $table->string('id_kelas_kuliah', 255)->nullable()->index();
            $table->string('nama_kelas_kuliah', 255)->nullable();
            $table->string('id_substansi', 255)->nullable()->index();
            $table->string('sks_substansi_total', 255)->nullable();
            $table->string('rencana_minggu_pertemuan', 255)->nullable();
            $table->string('realisasi_minggu_pertemuan', 255)->nullable();
            $table->string('id_jenis_evaluasi', 255)->nullable()->index();
            $table->string('nama_jenis_evaluasi', 255)->nullable();
            $table->string('id_prodi', 255)->nullable()->index();
            $table->string('id_semester', 255)->nullable()->index();
        });
    }

    public function down(): void
    {
        // Sengaja kosong: tabel produksi milik proses sinkronisasi, jangan
        // ikut terhapus oleh rollback.
    }
};
