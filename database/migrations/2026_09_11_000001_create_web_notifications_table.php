<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notifikasi siaran dari perintah /notif Telegram (lihat
        // routes/web.php dan App\Models\WebNotification). Cuma id+pesan yang
        // dipakai frontend untuk polling "ada yang baru sejak id X?".
        Schema::create('web_notifications', function (Blueprint $table) {
            $table->id();
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_notifications');
    }
};
