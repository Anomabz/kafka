
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sister_list_bkd_pengabdian', function (Blueprint $table) {
            $table->id();

            $table->string('id_sdm')->nullable();
            $table->string('nm_sdm')->nullable();
            $table->string('nidn', 20)->nullable();
            $table->string('nuptk', 30)->nullable();
            $table->string('id_smt', 10)->nullable();

            $table->string('unsur')->nullable();
            $table->text('judul_keg')->nullable();

            $table->unsignedInteger('id_katgiat')->nullable();
            $table->text('nm_kat')->nullable();

            $table->decimal('beban_sks', 10, 4)->nullable();
            $table->decimal('nilai', 20, 16)->nullable();

            $table->string('stat_kegiatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sister_list_bkd_pengabdian');
    }
};
