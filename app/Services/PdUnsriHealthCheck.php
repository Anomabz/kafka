<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Status jalur data PD UNSRI (lewat Gravitee) untuk badge di topbar, yang
 * dilihat SEMUA peran. Dicek tiap menit oleh scheduler (routes/console.php)
 * dan disimpan di cache, jadi request pengguna tidak pernah menunggu probe --
 * kecuali cache kosong (mis. habis cache:clear setelah sinkronisasi), di mana
 * satu request menjalankan probe sekali.
 *
 * Payload sengaja tidak memuat hostname, IP, atau teks exception: detail
 * teknis hanya masuk log server.
 */
class PdUnsriHealthCheck
{
    private const CACHE_KEY = 'pd-unsri:health';

    private const CACHE_TTL = 180;

    private const SLOW_MS = 2000;

    // Satu baris tabel kecil yang pasti ada di sumber (137 prodi) -- murah.
    private const PROBE_PATH = 'api/v1/program-studi';

    public function __construct(
        private readonly PangkalanDataApiClient $client,
        private readonly PdUnsriSyncDispatcher $sync,
    ) {}

    /** @return array<string, mixed> */
    public function current(): array
    {
        $health = Cache::get(self::CACHE_KEY);

        if (! is_array($health)) {
            $health = $this->probeAndStore();
        }

        return $health + ['last_sync' => $this->lastSync()];
    }

    /** @return array<string, mixed> */
    public function refresh(): array
    {
        return $this->probeAndStore() + ['last_sync' => $this->lastSync()];
    }

    /** @return array<string, mixed> */
    private function probeAndStore(): array
    {
        $health = $this->probe();

        Cache::put(self::CACHE_KEY, $health, self::CACHE_TTL);

        return $health;
    }

    /** @return array<string, mixed> */
    private function probe(): array
    {
        $started = hrtime(true);

        try {
            $response = $this->client->probe(self::PROBE_PATH, ['limit' => 1]);
        } catch (ConnectionException $e) {
            Log::warning('PD UNSRI health: request ke gateway gagal.', ['error' => $e->getMessage()]);

            // cURL 28 = gateway terjangkau tapi sumber tidak membalas dalam batas waktu.
            return str_contains($e->getMessage(), 'cURL error 28')
                ? $this->result('offline', 'Offline', 'API PD UNSRI tidak merespons (waktu habis).', null)
                : $this->result('offline', 'Offline', 'Gateway Gravitee tidak terjangkau.', null);
        } catch (HttpException $e) {
            Log::warning('PD UNSRI health: token API tidak bisa diambil.', ['error' => $e->getMessage()]);

            return $this->result('gangguan', 'Gangguan', 'Login API PD UNSRI gagal.', null);
        } catch (Throwable $e) {
            Log::warning('PD UNSRI health: pengecekan gagal.', ['error' => $e->getMessage()]);

            return $this->result('offline', 'Offline', 'API PD UNSRI tidak dapat dihubungi.', null);
        }

        $ms = (int) round((hrtime(true) - $started) / 1_000_000);
        $status = $response->status();

        if (! $response->successful()) {
            Log::warning('PD UNSRI health: sumber membalas tidak normal.', ['http_status' => $status, 'latency_ms' => $ms]);
        }

        return match (true) {
            $response->successful() && $ms < self::SLOW_MS => $this->result('online', 'Online', 'API PD UNSRI merespons normal.', $ms),
            $response->successful() => $this->result('lambat', 'Lambat', 'API PD UNSRI merespons, tapi lambat.', $ms),
            in_array($status, [401, 403], true) => $this->result('gangguan', 'Gangguan', 'Akses ke API PD UNSRI ditolak (akun/token).', $ms),
            $response->serverError() => $this->result('offline', 'Offline', 'API PD UNSRI tidak merespons.', $ms),
            default => $this->result('gangguan', 'Gangguan', 'API PD UNSRI membalas tidak normal.', $ms),
        };
    }

    /** @return array<string, mixed> */
    private function result(string $status, string $label, string $detail, ?int $latencyMs): array
    {
        return [
            'status' => $status,
            'label' => $label,
            'detail' => $detail,
            'latency_ms' => $latencyMs,
            'checked_at' => now()->toIso8601String(),
            'via' => 'Gravitee',
        ];
    }

    /**
     * Kesegaran data: finished_at = batch terakhir yang selesai dan tidak
     * dibatalkan (bukan batch yang sedang berjalan), supaya selama sinkronisasi
     * berjalan pengguna tetap tahu umur data yang sedang mereka lihat.
     *
     * @return array{finished_at: string|null, running: bool, processed: int, total: int}
     */
    private function lastSync(): array
    {
        try {
            $finishedAt = DB::table('job_batches')
                ->where('name', PdUnsriSyncDispatcher::BATCH_NAME)
                ->whereNull('cancelled_at')
                ->whereNotNull('finished_at')
                ->max('finished_at');

            $batch = $this->sync->latest();
        } catch (Throwable $e) {
            Log::warning('PD UNSRI health: status sinkronisasi tidak terbaca.', ['error' => $e->getMessage()]);

            return ['finished_at' => null, 'running' => false, 'processed' => 0, 'total' => 0];
        }

        $running = $batch !== null && ! $batch->finished() && ! $batch->cancelled();

        return [
            'finished_at' => $finishedAt ? now()->setTimestamp((int) $finishedAt)->toIso8601String() : null,
            'running' => $running,
            'processed' => $running ? $batch->totalJobs - $batch->pendingJobs : 0,
            'total' => $running ? $batch->totalJobs : 0,
        ];
    }
}
