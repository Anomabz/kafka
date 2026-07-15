<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sister_detail_penelitian', function (Blueprint $table) {
            $table->string('id_list', 255)->nullable()->index();
            $table->string('id', 255)->nullable()->index();
            $table->string('id_kategori_kegiatan', 255)->nullable();
            $table->longText('judul')->nullable();
            $table->string('lama_kegiatan', 255)->nullable();
            $table->string('tahun_pelaksanaan_ke', 255)->nullable();
            $table->string('dana_dikti', 255)->nullable();
            $table->string('dana_perguruan_tinggi', 255)->nullable();
            $table->string('dana_institusi_lain', 255)->nullable();
            $table->string('in_kind', 255)->nullable();
            $table->string('sk_penugasan', 255)->nullable();
            $table->string('tanggal_sk_penugasan', 255)->nullable();
            $table->string('lokasi', 255)->nullable();
            $table->string('id_jenis_skim', 255)->nullable();
            $table->string('jenis_skim', 255)->nullable();
            $table->string('tahun_usulan', 255)->nullable();
            $table->string('tahun_kegiatan', 255)->nullable();
            $table->string('tahun_pelaksanaan', 255)->nullable();
            $table->string('id_litabmas_sebelumnya', 255)->nullable();
            $table->string('litabmas_sebelumnya', 255)->nullable();
            $table->string('id_afiliasi', 255)->nullable();
            $table->string('afiliasi', 255)->nullable();
            $table->string('id_kelompok_bidang', 255)->nullable();
            $table->string('kelompok_bidang', 255)->nullable();
            $table->longText('anggota')->nullable();
            $table->longText('dokumen')->nullable();
            $table->longText('mitra_litabmas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sister_detail_penelitian');
    }
};
