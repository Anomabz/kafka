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
            $table->string('id_mahasiswa')->nullable()->index('idx_mhs');
            $table->string('nama_mahasiswa')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('nipd')->nullable();
            $table->string('ipk')->nullable();
            $table->string('total_sks')->nullable();
            $table->string('id_sms')->nullable();
            $table->string('id_agama')->nullable();
            $table->string('nama_agama')->nullable();
            $table->string('id_prodi')->nullable()->index('idx_prodi');
            $table->string('nama_program_studi')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
            $table->string('nama_status_mahasiswa')->nullable()->index('idx_nm_status');
            $table->string('nim')->nullable()->index('idx_nim');
            $table->string('id_periode')->nullable()->index('idx_periode');
            $table->string('nama_periode_masuk')->nullable();
            $table->string('id_registrasi_mahasiswa')->nullable()->index('idx_reg');
            $table->string('id_periode_keluar')->nullable();
            $table->string('tanggal_keluar')->nullable();
            $table->string('last_update')->nullable();
            $table->string('tgl_create')->nullable();
            $table->string('status_sync')->nullable();

            $table->index(['id_prodi', 'id_mahasiswa', 'nim'], 'idx_list_mahasiswa');
            $table->index(['id_prodi', 'id_status_mahasiswa'], 'idx_prod_status');
            $table->index(['id_prodi', 'id_periode'], 'idx_prodi_periode');
            $table->index(['id_periode', 'nama_status_mahasiswa'], 'idx_stat_periode');
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
