<?php

namespace App\Http\Controllers;

use App\Models\WebNotification;
use Illuminate\Http\Request;

/**
 * Polling endpoint untuk alert siaran (lihat App\Models\WebNotification).
 * Tidak pakai broadcasting/websocket -- server ini sudah menjalankan belasan
 * project compose sekaligus dan buka port baru cuma untuk ini tidak sepadan;
 * polling tiap beberapa detik jauh lebih murah untuk skala SAPTO.
 */
class WebNotificationController extends Controller
{
    public function latest(Request $request)
    {
        // Kunjungan pertama (belum ada id tersimpan di localStorage
        // frontend): jangan tumpahkan semua histori sebagai alert, cukup
        // kasih tahu id terbaru sebagai titik awal.
        if ($request->boolean('bootstrap')) {
            return response()->json([
                'latest_id' => (int) (WebNotification::max('id') ?? 0),
                'notifications' => [],
            ]);
        }

        $afterId = (int) $request->integer('after_id');

        // Notifikasi khusus admin (audience='admin') disaring di server, bukan
        // di frontend -- isinya info operasional yang bukan untuk korprodi.
        // Saat admin menyamar, sso_user adalah akun tiruan tanpa is_admin,
        // jadi yang terlihat tetap sama dengan orang yang disamarkan.
        $isAdmin = (bool) ($request->session()->get('sso_user')['is_admin'] ?? false);

        $notifications = WebNotification::query()
            ->where('id', '>', $afterId)
            ->when(! $isAdmin, fn ($q) => $q->whereNull('audience'))
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'message', 'created_at']);

        return response()->json([
            'latest_id' => (int) ($notifications->last()->id ?? $afterId),
            'notifications' => $notifications,
        ]);
    }
}
