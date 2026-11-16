<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Kafka\KafkaMonitor;
use App\Services\KafkaProducerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * /admin/kafka -- halaman monitor Event Stream (Kafka): status, topic, event
 * terbaru, dan tombol event uji. Lihat KafkaMonitor dan docs/kafka.md.
 */
class KafkaController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        return view('admin.kafka', ['topics' => config('kafka.topics')]);
    }

    /** Dipolling halaman: 10 detik saat diam, 1 detik sesudah event uji dikirim. */
    public function status(Request $request, KafkaMonitor $monitor): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($monitor->snapshot($request->query('topic')))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Kirim satu event uji ke `sapto.audit` supaya tim bisa MELIHAT seluruh
     * alurnya: producer -> broker (offset) -> consumer -> arsip. Halaman
     * lalu memantau event_id ini sampai muncul di arsip.
     */
    public function test(Request $request, KafkaProducerService $producer): JsonResponse
    {
        $this->ensureAdmin($request);

        // Cegah klik beruntun membanjiri topic audit.
        if (! Cache::add('kafka:test:lock', 1, 3)) {
            return response()->json(['ok' => false, 'message' => 'Tunggu beberapa detik sebelum mengirim event uji lagi.'], 429);
        }

        $actor = KafkaProducerService::actorFromRequest($request);
        $event = KafkaProducerService::envelope('test', ['pesan' => 'Event uji dari halaman Admin → Event Stream (Kafka).'], $actor);
        $ok = $producer->send('sapto.audit', $actor['email'] ?? $actor['nip'] ?? null, $event);

        $last = Cache::get(KafkaProducerService::LAST_RESULT_KEY);

        return response()->json([
            'ok' => $ok,
            'event_id' => $event['event_id'],
            'partition' => $ok ? ($last['partition'] ?? null) : null,
            'offset' => $ok ? ($last['offset'] ?? null) : null,
            // Kalau gagal, event tidak hilang: masuk outbox dan dikirim ulang
            // tiap menit -- pesan ini yang menjelaskannya ke admin.
            'message' => $ok
                ? 'Diterima broker Kafka.'
                : 'Belum bisa dikirim ke Kafka. Event disimpan di antrean kirim ulang dan dicoba lagi tiap menit.',
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        $sso = (array) $request->session()->get('sso_user', []);

        abort_unless($sso['is_admin'] ?? false, 403, 'Halaman ini khusus akun admin SAPTO.');
    }
}
