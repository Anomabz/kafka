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
        Schema::create('sister_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sister_url');
            $table->string('sister_username');
            $table->string('sister_password');
            $table->string('sister_id_pengguna');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sister_configs');
    }
};
