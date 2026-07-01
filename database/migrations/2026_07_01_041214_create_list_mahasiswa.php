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
        Schema::create('list_mahasiswa', function (Blueprint $table) {
            $table->id();

            $table->string('id_mahasiswa', 255)->nullable();
            $table->string('nama_mahasiswa', 255)->nullable();
            $table->string('jenis_kelamin', 255)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('id_perguruan_tinggi', 255)->nullable();
            $table->string('nipd', 255)->nullable();
            $table->string('ipk', 255)->nullable();
            $table->string('total_sks', 255)->nullable();
            $table->string('id_sms', 255)->nullable();
            $table->string('id_agama', 255)->nullable();
            $table->string('nama_agama', 255)->nullable();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nama_program_studi', 255)->nullable();
            $table->string('id_status_mahasiswa', 255)->nullable();
            $table->string('nama_status_mahasiswa', 255)->nullable();
            $table->string('nim', 255)->nullable();
            $table->string('id_periode', 255)->nullable();
            $table->string('nama_periode_masuk', 255)->nullable();
            $table->string('id_registrasi_mahasiswa', 255)->nullable();
            $table->string('id_periode_keluar', 255)->nullable();
            $table->string('tanggal_keluar', 255)->nullable();
            $table->string('last_update', 255)->nullable();
            $table->string('tgl_create', 255)->nullable();
            $table->string('status_sync', 255)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_mahasiswa');
    }
};
