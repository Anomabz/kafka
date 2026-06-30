<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_studi', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('fakultas');
            $table->string('nama');
            $table->string('jenjang', 32);
            $table->timestamps();

            $table->index(['fakultas', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_studi');
    }
};
