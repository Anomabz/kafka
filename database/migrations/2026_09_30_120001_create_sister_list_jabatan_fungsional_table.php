<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Diisi sinkronisasi PD UNSRI dan sudah ada di produksi (skema disalin
        // dari sana 2026-09-30); migration ini membuatnya di database baru
        // (mis. sqlite untuk testing). Dipakai Tabel 3.a.1/3.a.4 (jabatan akademik).
        if (Schema::hasTable('sister_list_jabatan_fungsional')) {
            return;
        }

        Schema::create('sister_list_jabatan_fungsional', function (Blueprint $table) {
            $table->string('id_sdm', 255)->nullable()->index('id_sdm_index');
            $table->string('id_stat_pegawai', 255)->nullable();
            $table->string('id', 255)->nullable()->index('id_index');
            $table->string('jabatan_fungsional', 255)->nullable();
            $table->string('nm_stat_pegawai', 255)->nullable();
            $table->string('sk', 255)->nullable();
            $table->date('tanggal_mulai')->nullable();
        });
    }

    public function down(): void
    {
        // Sengaja kosong: tabel produksi milik proses sinkronisasi.
    }
};
