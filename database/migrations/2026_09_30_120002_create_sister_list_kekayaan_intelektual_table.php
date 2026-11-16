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
        // (mis. sqlite untuk testing). Dipakai Tabel 3.b.7 (HKI DTPS).
        if (Schema::hasTable('sister_list_kekayaan_intelektual')) {
            return;
        }

        Schema::create('sister_list_kekayaan_intelektual', function (Blueprint $table) {
            $table->string('id_sdm', 255)->nullable()->index('idx_id_sdm');
            $table->string('id_kategori_kegiatan', 255)->nullable();
            $table->string('a_klaim_bkd', 255)->nullable();
            $table->string('wkt_klaim_bkd', 255)->nullable();
            $table->string('id', 255)->nullable()->index('idx_id');
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
        // Sengaja kosong: tabel produksi milik proses sinkronisasi.
    }
};
