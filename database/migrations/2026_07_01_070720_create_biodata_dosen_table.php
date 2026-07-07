<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biodata_dosen', function (Blueprint $table) {
            $table->string('id_dosen', 255)->primary();
            $table->string('nama_dosen', 255);
            $table->string('tempat_lahir', 255)->nullable();
            $table->string('tanggal_lahir', 255)->nullable();
            $table->string('jenis_kelamin', 255)->nullable();
            $table->integer('id_agama')->nullable();
            $table->string('nama_agama', 255)->nullable();
            $table->integer('id_status_aktif')->nullable();
            $table->string('nama_status_aktif', 255)->nullable();
            $table->string('nidn', 20)->nullable();
            $table->string('nama_ibu_kandung', 255)->nullable();
            $table->string('nik', 40)->nullable();
            $table->string('nip', 40)->nullable();
            $table->string('nuptk', 255)->nullable();
            $table->string('npwp', 40)->nullable();
            $table->integer('id_jenis_sdm')->nullable();
            $table->string('nama_jenis_sdm', 255)->nullable();
            $table->string('no_sk_cpns', 255)->nullable();
            $table->string('tanggal_sk_cpns', 255)->nullable();
            $table->string('no_sk_pengangkatan', 255)->nullable();
            $table->string('mulai_sk_pengangkatan', 255)->nullable();
            $table->integer('id_lembaga_pengangkatan')->nullable();
            $table->string('nama_lembaga_pengangkatan', 255)->nullable();
            $table->integer('id_pangkat_golongan')->nullable();
            $table->string('nama_pangkat_golongan', 255)->nullable();
            $table->integer('id_sumber_gaji')->nullable();
            $table->string('nama_sumber_gaji', 255)->nullable();
            $table->string('jalan', 255)->nullable();
            $table->string('dusun', 255)->nullable();
            $table->integer('rt')->nullable();
            $table->integer('rw')->nullable();
            $table->string('ds_kel', 255)->nullable();
            $table->string('kode_pos', 255)->nullable();
            $table->string('id_wilayah', 255)->nullable();
            $table->string('nama_wilayah', 255)->nullable();
            $table->string('telepon', 255)->nullable();
            $table->string('handphone', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('status_pernikahan')->nullable();
            $table->string('nama_suami_istri', 255)->nullable();
            $table->string('nip_suami_istri', 255)->nullable();
            $table->string('tanggal_mulai_pns', 255)->nullable();
            $table->unsignedBigInteger('id_pekerjaan_suami_istri')->nullable();
            $table->string('nama_pekerjaan_suami_istri', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biodata_dosen');
    }
};
