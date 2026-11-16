<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Setiap event yang BENAR-BENAR sudah lewat Kafka (ditulis consumer
        // `sapto:kafka-consume`, bukan producer) -- jadi baris di sini adalah
        // bukti alurnya berjalan, sekaligus arsip audit permanen: retensi di
        // broker cuma 30-90 hari. event_id unik membuat consumer idempoten:
        // pesan yang terbaca dua kali (at-least-once) tidak tercatat dua kali.
        Schema::create('kafka_event_log', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('topic', 191)->index();
            $table->unsignedInteger('partition')->default(0);
            $table->unsignedBigInteger('offset')->default(0);
            $table->string('type', 100)->index();
            $table->string('event_key', 191)->nullable();
            $table->string('actor', 191)->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('consumed_at')->useCurrent();
        });

        // Outbox: event yang gagal dikirim ke REST Proxy (Kafka/proxy mati,
        // restart, jaringan). Tanpa ini event audit login yang terjadi saat
        // Kafka sedang restart hilang tanpa jejak. Dikirim ulang tiap menit
        // oleh `sapto:kafka-flush-outbox` (scheduler).
        Schema::create('kafka_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('topic', 191);
            $table->string('event_key', 191)->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('pending')->index(); // pending|sent|dead
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // Notifikasi hasil sinkronisasi (dari consumer Kafka) hanya relevan
        // untuk admin; null = siaran ke semua orang seperti /notif Telegram.
        Schema::table('web_notifications', function (Blueprint $table) {
            $table->string('audience', 20)->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('web_notifications', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
        Schema::dropIfExists('kafka_outbox');
        Schema::dropIfExists('kafka_event_log');
    }
};
