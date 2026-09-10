<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sama seperti sister_detail_publikasi: tabel referensi ini sudah
        // ada di database produksi/staging (32 baris, kode id_jenis_publikasi
        // -> nama, mis. 13 => "Buku lainnya"). Guard supaya migrate tidak
        // gagal di sana; di database baru (sqlite testing) migration ini
        // yang membuat skemanya.
        if (Schema::hasTable('jenis_publikasi')) {
            return;
        }

        Schema::create('jenis_publikasi', function (Blueprint $table) {
            $table->integer('id')->nullable()->index();
            $table->string('nama', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_publikasi');
    }
};
