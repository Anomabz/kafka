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
        Schema::create('biodata_dosen', function (Blueprint $table) {
            $table->string('id_dosen')->primary();
            $table->string('nama_dosen');
            $table->string('tempat_lahir')->nullable();
            $table->string('tanggal_lahir')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->integer('id_agama')->nullable();
            $table->string('nama_agama')->nullable();
            $table->integer('id_status_aktif')->nullable();
            $table->string('nama_status_aktif')->nullable();
            $table->string('nidn', 20)->nullable();
            $table->string('nama_ibu_kandung')->nullable();
            $table->string('nik', 40)->nullable();
            $table->string('nip', 40)->nullable();
            $table->string('nuptk')->nullable();
            $table->string('npwp', 40)->nullable();
            $table->integer('id_jenis_sdm')->nullable();
            $table->string('nama_jenis_sdm')->nullable();
            $table->string('no_sk_cpns')->nullable();
            $table->string('tanggal_sk_cpns')->nullable();
            $table->string('no_sk_pengangkatan')->nullable();
            $table->string('mulai_sk_pengangkatan')->nullable();
            $table->integer('id_lembaga_pengangkatan')->nullable();
            $table->string('nama_lembaga_pengangkatan')->nullable();
            $table->integer('id_pangkat_golongan')->nullable();
            $table->string('nama_pangkat_golongan')->nullable();
            $table->integer('id_sumber_gaji')->nullable();
            $table->string('nama_sumber_gaji')->nullable();
            $table->string('jalan')->nullable();
            $table->string('dusun')->nullable();
            $table->integer('rt')->nullable();
            $table->integer('rw')->nullable();
            $table->string('ds_kel')->nullable();
            $table->string('kode_pos')->nullable();
            $table->string('id_wilayah')->nullable();
            $table->string('nama_wilayah')->nullable();
            $table->string('telepon')->nullable();
            $table->string('handphone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('status_pernikahan')->nullable();
            $table->string('nama_suami_istri')->nullable();
            $table->string('nip_suami_istri')->nullable();
            $table->string('tanggal_mulai_pns')->nullable();
            $table->unsignedBigInteger('id_pekerjaan_suami_istri')->nullable();
            $table->string('nama_pekerjaan_suami_istri')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biodata_dosen');
    }
};
