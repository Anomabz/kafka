<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biodata_mahasiswa', function (Blueprint $table) {
            $table->string('id_mahasiswa', 255)->primary();
            $table->string('nama_mahasiswa', 255)->nullable();
            $table->string('jenis_kelamin', 255)->nullable();
            $table->string('tempat_lahir', 255)->nullable();
            $table->string('tanggal_lahir', 255)->nullable();
            $table->string('id_agama', 255)->nullable();
            $table->string('nama_agama', 255)->nullable();
            $table->string('nik', 255)->nullable();
            $table->string('nisn', 255)->nullable();
            $table->string('npwp', 255)->nullable();
            $table->string('id_negara', 255)->nullable();
            $table->string('kewarganegaraan', 255)->nullable();
            $table->string('jalan', 255)->nullable();
            $table->string('dusun', 255)->nullable();
            $table->string('rt', 255)->nullable();
            $table->string('rw', 255)->nullable();
            $table->string('kelurahan', 255)->nullable();
            $table->string('kode_pos', 255)->nullable();
            $table->string('id_wilayah', 255)->nullable();
            $table->string('nama_wilayah', 255)->nullable();
            $table->string('id_jenis_tinggal', 255)->nullable();
            $table->string('nama_jenis_tinggal', 255)->nullable();
            $table->string('id_alat_transportasi', 255)->nullable();
            $table->string('nama_alat_transportasi', 255)->nullable();
            $table->string('telepon', 255)->nullable();
            $table->string('handphone', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('penerima_kps', 255)->nullable();
            $table->string('nomor_kps', 255)->nullable();
            $table->string('nik_ayah', 255)->nullable();
            $table->string('nama_ayah', 255)->nullable();
            $table->string('tanggal_lahir_ayah', 255)->nullable();
            $table->string('id_pendidikan_ayah', 255)->nullable();
            $table->string('nama_pendidikan_ayah', 255)->nullable();
            $table->string('id_pekerjaan_ayah', 255)->nullable();
            $table->string('nama_pekerjaan_ayah', 255)->nullable();
            $table->string('id_penghasilan_ayah', 255)->nullable();
            $table->string('nama_penghasilan_ayah', 255)->nullable();
            $table->string('nik_ibu', 255)->nullable();
            $table->string('nama_ibu_kandung', 255)->nullable();
            $table->string('tanggal_lahir_ibu', 255)->nullable();
            $table->string('id_pendidikan_ibu', 255)->nullable();
            $table->string('nama_pendidikan_ibu', 255)->nullable();
            $table->string('id_pekerjaan_ibu', 255)->nullable();
            $table->string('nama_pekerjaan_ibu', 255)->nullable();
            $table->string('id_penghasilan_ibu', 255)->nullable();
            $table->string('nama_penghasilan_ibu', 255)->nullable();
            $table->string('nama_wali', 255)->nullable();
            $table->string('tanggal_lahir_wali', 255)->nullable();
            $table->string('id_pendidikan_wali', 255)->nullable();
            $table->string('nama_pendidikan_wali', 255)->nullable();
            $table->string('id_pekerjaan_wali', 255)->nullable();
            $table->string('nama_pekerjaan_wali', 255)->nullable();
            $table->string('id_penghasilan_wali', 255)->nullable();
            $table->string('nama_penghasilan_wali', 255)->nullable();
            $table->string('id_kebutuhan_khusus_mahasiswa', 255)->nullable();
            $table->string('nama_kebutuhan_khusus_mahasiswa', 255)->nullable();
            $table->string('id_kebutuhan_khusus_ayah', 255)->nullable();
            $table->string('nama_kebutuhan_khusus_ayah', 255)->nullable();
            $table->string('id_kebutuhan_khusus_ibu', 255)->nullable();
            $table->string('nama_kebutuhan_khusus_ibu', 255)->nullable();
            $table->string('status_sync', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biodata_mahasiswa');
    }
};
