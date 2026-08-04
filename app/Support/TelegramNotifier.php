<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Alert error 5xx ke Telegram (@saptostatusbot) dan terima pendaftaran chat id
 * baru lewat webhook. Chat id disimpan di storage/app (bukan tabel db) karena
 * tidak butuh query, cuma daftar kecil yang dibaca-tulis jarang.
 */
class TelegramNotifier
{
    private const CHAT_IDS_PATH = 'telegram_chat_ids.json';

    public static function report(\Throwable $e): void
    {
        if (! app()->environment('production') || ! config('services.telegram.bot_token')) {
            return;
        }

        // HTTP exception 4xx (404, 419, 403, ...) itu perilaku normal, bukan
        // insiden -- cuma alert untuk yang benar-benar 500-an atau exception
        // tak terduga di luar HTTP.
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
            && $e->getStatusCode() < 500) {
            return;
        }

        // Throttle per lokasi error 5 menit -- exception yang sama berulang
        // (mis. db down) tidak boleh membanjiri chat.
        $key = 'telegram-alert:'.md5($e::class.$e->getFile().$e->getLine());
        if (Cache::has($key)) {
            return;
        }
        Cache::put($key, true, 300);

        $message = sprintf(
            "🔴 <b>SAPTO error</b>\n<b>%s</b>: %s\n<code>%s:%d</code>",
            $e::class,
            e($e->getMessage()) ?: '(tanpa pesan)',
            $e->getFile(),
            $e->getLine(),
        );

        static::broadcast($message);
    }

    public static function broadcast(string $message): void
    {
        foreach (static::chatIds() as $chatId) {
            static::sendTo($chatId, $message);
        }
    }

    public static function sendTo(int|string $chatId, string $message): void
    {
        $token = config('services.telegram.bot_token');
        if (! $token) {
            return;
        }

        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // Jangan sampai kegagalan kirim notifikasi ikut melempar exception baru.
            Log::warning('Gagal kirim alert Telegram', ['chat_id' => $chatId, 'error' => $e->getMessage()]);
        }
    }

    public static function registerChatId(int|string $chatId): bool
    {
        $ids = static::chatIds();
        if (in_array((string) $chatId, $ids, true)) {
            return false;
        }

        $ids[] = (string) $chatId;
        Storage::put(self::CHAT_IDS_PATH, json_encode(array_values($ids)));

        return true;
    }

    /** @return string[] */
    public static function chatIds(): array
    {
        if (! Storage::exists(self::CHAT_IDS_PATH)) {
            return [];
        }

        $ids = json_decode(Storage::get(self::CHAT_IDS_PATH), true);

        return is_array($ids) ? $ids : [];
    }
}
