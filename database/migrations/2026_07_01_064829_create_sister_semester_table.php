<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sister_semester', function (Blueprint $table) {
            $table->id(); // int(11) primary
            $table->string('nama', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sister_semester');
    }
};
