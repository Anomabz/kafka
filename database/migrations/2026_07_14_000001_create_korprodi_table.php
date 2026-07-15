<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('korprodi', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 32);
            $table->string('nama');
            $table->string('kode_program_studi', 16);
            // 'medium' = matched from an informal/shortened program name in the
            // source list rather than an exact match against program_studi.csv —
            // worth a spot-check against BAAK/akademik records.
            $table->string('confidence', 8)->default('high');
            $table->timestamps();

            $table->unique(['nip', 'kode_program_studi']);
            $table->index('nip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('korprodi');
    }
};
