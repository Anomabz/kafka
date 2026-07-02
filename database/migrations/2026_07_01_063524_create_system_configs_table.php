<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Configurations
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->string('url', 255)->nullable();
            $table->string('username', 255)->nullable();
            $table->string('password', 255)->nullable();
            $table->timestamps();
        });

        // Tabel Sync Feeder
        Schema::create('sync_feeder', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 255);
            $table->string('func_name', 255);
            $table->string('status', 255)->nullable();
            $table->timestamps();
        });

        // Tabel Sync Batches
        Schema::create('sync_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('status', 255);
            $table->string('batch_id', 255);
            $table->timestamps();
        });

        // Tabel Token Temps
        Schema::create('token_temps', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->longText('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurations');
        Schema::dropIfExists('sync_feeder');
        Schema::dropIfExists('sync_batches');
        Schema::dropIfExists('token_temps');
    }
};
