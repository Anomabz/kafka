<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sister_negara', function (Blueprint $table) {
            $table->string('id', 255)->primary(); // varchar(255) primary
            $table->string('nama', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sister_negara');
    }
};
