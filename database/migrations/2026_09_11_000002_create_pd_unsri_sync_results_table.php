<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil sinkronisasi PD UNSRI per tabel per batch -- dibaca halaman
 * /admin/sync-pd-unsri. job_batches cuma menyimpan hitungan agregat, jadi
 * tanpa tabel ini "tabel mana yang gagal dan kenapa" hanya ada di log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pd_unsri_sync_results', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->index();
            $table->string('slug');
            $table->string('table_name')->index();
            // running | done | skipped | failed | cancelled
            $table->string('status', 16);
            $table->unsignedInteger('rows_fetched')->nullable();
            $table->unsignedInteger('rows_source')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['table_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pd_unsri_sync_results');
    }
};
