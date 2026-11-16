<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_tracer_iku', function (Blueprint $table) {
            $table->id();

            $table->string('kode_kategori', 100)->unique();
            $table->string('kelompok', 100);
            $table->text('kriteria');
            $table->decimal('bobot', 4, 2)->default(0.00);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_tracer_iku');
    }
};