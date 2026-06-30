<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dosen', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('nidn', 64)->nullable()->index();
            $table->string('nidk', 64)->nullable()->index();
            $table->string('nip', 64)->nullable()->index();
            $table->timestamps();

            $table->index('nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dosen');
    }
};
