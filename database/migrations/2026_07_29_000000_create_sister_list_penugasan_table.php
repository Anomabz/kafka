<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sister_list_penugasan')) {
            return;
        }

        Schema::create('sister_list_penugasan', function (Blueprint $table) {
            $table->string('id', 255)->nullable()->index('slp_id');
            $table->string('id_sdm', 255)->nullable()->index('slp_id_sdm');
            $table->string('jenjang_pendidikan', 255)->nullable();
            $table->string('unit_kerja', 255)->nullable()->index('slp_unit_kerja');
            $table->string('tanggal_keluar', 255)->nullable();
            $table->string('tanggal_mulai', 255)->nullable();
            $table->string('apakah_penugasan_homebase', 255)->nullable();
            $table->string('perguruan_tinggi', 255)->nullable()->index('slp_pt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sister_list_penugasan');
    }
};
