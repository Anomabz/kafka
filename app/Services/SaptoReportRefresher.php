<?php

namespace App\Services;

use App\Console\Commands\WarmSaptoCache;
use App\Support\RecomputingCacheRepository;
use App\Support\SaptoUserContext;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * "Segarkan laporan" di popover badge API: hitung ulang cache ringkasan
 * laporan untuk LINGKUP PENGGUNA SENDIRI (prodi korprodi, fakultas dekan,
 * universitas untuk rektor/admin) dari data lokal. Tidak menarik apa pun dari
 * API PD UNSRI -- API itu tidak bisa difilter per prodi, jadi tarikan
 * berlingkup memang tidak mungkin; data sumbernya diperbarui sinkronisasi
 * mingguan.
 *
 * Perhitungan jalan di proses terpisah (`sapto:refresh-scope`, dilepas dari
 * request): `php artisan serve` di sini single-threaded, jadi 5-20 detik
 * hitung di dalam request akan membekukan seluruh situs, dan queue worker
 * sedang dipakai sinkronisasi yang lama.
 *
 * Penjaga: satu penyegaran per lingkup (lock), jeda 2 menit setelah selesai,
 * dan paling banyak 2 penyegaran bersamaan di seluruh aplikasi (DB).
 */
class SaptoReportRefresher
{
    private const LOCK_TTL = 600;

    private const COOLDOWN = 120;

    private const SLOTS = 2;

    private const STATUS_TTL = 86400;

    /** @return array{key: string, type: string, label: string, fakultas: ?string, prodi: ?string} */
    public function scopeFor(SaptoUserContext $context): array
    {
        return match ($context->role) {
            // Sama dengan argumen yang dipakai controller untuk korprodi:
            // resolveFakultasId() = null, resolveProgramStudiId() = prodinya.
            'prodi' => $this->scope(null, $context->programStudiId),
            'fakultas' => $this->scope($context->fakultasId, null),
            default => $this->scope(null, null),
        };
    }

    /** @return array{key: string, type: string, label: string, fakultas: ?string, prodi: ?string} */
    public function scope(?string $fakultasId, ?string $programStudiId): array
    {
        $repo = app(SaptoProgramStudiRepository::class);

        if ($programStudiId !== null) {
            $prodi = $repo->programStudis()->firstWhere('id', $programStudiId)
                ?? throw new HttpException(422, 'Program studi tidak dikenal.');

            return ['key' => 'prodi:'.$programStudiId, 'type' => 'prodi', 'label' => (string) $prodi['label'], 'fakultas' => null, 'prodi' => $programStudiId];
        }

        if ($fakultasId !== null) {
            $fakultas = $repo->faculties()->firstWhere('id', $fakultasId)
                ?? throw new HttpException(422, 'Fakultas tidak dikenal.');

            return ['key' => 'fakultas:'.$fakultasId, 'type' => 'fakultas', 'label' => (string) $fakultas['nama'], 'fakultas' => $fakultasId, 'prodi' => null];
        }

        return ['key' => 'universitas', 'type' => 'universitas', 'label' => 'Universitas Sriwijaya', 'fakultas' => null, 'prodi' => null];
    }

    /**
     * Blok `refresh` per pengguna di GET /api/sapto/pd-unsri/status.
     *
     * @return array<string, mixed>
     */
    public function payloadFor(Request $request): array
    {
        try {
            $scope = $this->scopeFor(SaptoUserContext::fromRequest($request));
        } catch (HttpException) {
            return ['allowed' => false, 'scope_label' => null, 'state' => 'idle', 'started_at' => null,
                'finished_at' => null, 'duration_ms' => null, 'cooldown_until' => null, 'message' => null];
        }

        return ['allowed' => true, 'scope_label' => $scope['label'], 'scope_type' => $scope['type']] + $this->status($scope['key']);
    }

    /** @return array{state: string, started_at: ?string, finished_at: ?string, duration_ms: ?int, cooldown_until: ?string, message: ?string} */
    public function status(string $scopeKey): array
    {
        $status = Cache::get($this->statusKey($scopeKey));

        if (! is_array($status)) {
            return ['state' => 'idle', 'started_at' => null, 'finished_at' => null, 'duration_ms' => null, 'cooldown_until' => null, 'message' => null];
        }

        // Proses yang mati di tengah jalan tidak sempat menulis status akhir;
        // jangan biarkan tombolnya macet "berjalan" selamanya.
        if ($status['state'] === 'running' && Carbon::parse($status['started_at'])->addSeconds(self::LOCK_TTL)->isPast()) {
            $status['state'] = 'failed';
            $status['message'] = 'Penyegaran terhenti tanpa hasil. Silakan coba lagi.';
        }

        if ($status['cooldown_until'] !== null && Carbon::parse($status['cooldown_until'])->isPast()) {
            $status['cooldown_until'] = null;
        }

        return array_intersect_key($status, array_flip(['state', 'started_at', 'finished_at', 'duration_ms', 'cooldown_until', 'message']));
    }

    /**
     * Dipanggil endpoint POST: ambil lock, catat status, lepas proses latar,
     * lalu kembali seketika.
     *
     * @return array<string, mixed>
     */
    public function start(Request $request): array
    {
        $scope = $this->scopeFor(SaptoUserContext::fromRequest($request));
        // Korprodi di tabel `penugasan` banyak yang emailnya kosong (dicocokkan
        // lewat NIP), jadi jangan berhenti di email -- log audit perlu tahu siapa.
        $sso = (array) $request->session()->get('sso_user', []);
        $by = (string) ($sso['email'] ?: $sso['nomor_identitas'] ?: ($sso['name'] ?? '') ?: 'unknown');

        [$scopeLock, $slot, $slotLock] = $this->acquire($scope);

        $this->putStatus($scope['key'], ['state' => 'running', 'started_at' => now()->toIso8601String(), 'finished_at' => null,
            'duration_ms' => null, 'cooldown_until' => null, 'message' => null, 'by' => $by]);

        $args = array_filter([
            'sapto:refresh-scope',
            $scope['fakultas'] !== null ? '--fakultas='.$scope['fakultas'] : null,
            $scope['prodi'] !== null ? '--prodi='.$scope['prodi'] : null,
            '--by='.$by,
            '--scope-owner='.$scopeLock->owner(),
            '--slot='.$slot,
            '--slot-owner='.$slotLock->owner(),
        ]);

        $command = implode(' ', array_map('escapeshellarg', [PHP_BINARY, base_path('artisan'), ...$args]));

        // nohup + & + output dialihkan: shell langsung kembali, request tidak menunggu.
        exec('nohup '.$command.' >> '.escapeshellarg(storage_path('logs/refresh-scope.log')).' 2>&1 &');

        Log::info('SAPTO: segarkan laporan dimulai.', ['by' => $by, 'scope' => $scope['key'], 'slot' => $slot]);

        return $this->payloadFor($request);
    }

    /**
     * @return array{0: Lock, 1: int, 2: Lock}
     */
    public function acquire(array $scope): array
    {
        $status = $this->status($scope['key']);

        if ($status['cooldown_until'] !== null) {
            throw new HttpException(429, 'Laporan ini baru saja disegarkan. Coba lagi setelah '.Carbon::parse($status['cooldown_until'])->timezone(config('app.timezone'))->format('H:i:s').'.');
        }

        $scopeLock = Cache::lock($this->scopeLockName($scope['key']), self::LOCK_TTL);

        if (! $scopeLock->get()) {
            throw new HttpException(409, 'Laporan lingkup ini sedang disegarkan.');
        }

        for ($slot = 1; $slot <= self::SLOTS; $slot++) {
            $slotLock = Cache::lock($this->slotLockName($slot), self::LOCK_TTL);

            if ($slotLock->get()) {
                return [$scopeLock, $slot, $slotLock];
            }
        }

        $scopeLock->release();

        throw new HttpException(429, 'Sedang ada '.self::SLOTS.' penyegaran laporan lain berjalan. Coba lagi sebentar lagi.');
    }

    /**
     * Isi proses `sapto:refresh-scope`. Selalu melepas lock yang diwariskan.
     *
     * @return array{rewritten: list<string>, failed: list<string>, duration_ms: int}
     */
    public function run(array $scope, string $scopeOwner, int $slot, string $slotOwner, string $by): array
    {
        $started = hrtime(true);
        $failed = [];
        $timings = [];
        $manager = Cache::getFacadeRoot();
        $recomputing = new RecomputingCacheRepository(Cache::store()->getStore());

        try {
            Cache::swap($recomputing);

            foreach (WarmSaptoCache::REPOSITORIES as $name => $class) {
                $tableStarted = hrtime(true);

                try {
                    app($class)->summary(null, $scope['fakultas'], $scope['prodi']);
                    $timings[$name] = (int) round((hrtime(true) - $tableStarted) / 1_000_000);
                } catch (Throwable $e) {
                    $failed[] = $name;
                    Log::warning('SAPTO: segarkan laporan -- satu tabel gagal.', ['scope' => $scope['key'], 'tabel' => $name, 'error' => $e->getMessage()]);
                }
            }
        } finally {
            Cache::swap($manager);

            $ms = (int) round((hrtime(true) - $started) / 1_000_000);
            $total = count(WarmSaptoCache::REPOSITORIES);
            $previous = Cache::get($this->statusKey($scope['key']));

            $this->putStatus($scope['key'], [
                'state' => $failed === [] ? 'done' : 'failed',
                'started_at' => is_array($previous) ? $previous['started_at'] : now()->subMilliseconds($ms)->toIso8601String(),
                'finished_at' => now()->toIso8601String(),
                'duration_ms' => $ms,
                // Jeda hanya setelah berhasil; yang gagal boleh langsung dicoba lagi.
                'cooldown_until' => $failed === [] ? now()->addSeconds(self::COOLDOWN)->toIso8601String() : null,
                'message' => $failed === [] ? null : count($failed).' dari '.$total.' laporan gagal dihitung ulang; sisanya sudah diperbarui.',
                'by' => $by,
            ]);

            Cache::restoreLock($this->slotLockName($slot), $slotOwner)->release();
            Cache::restoreLock($this->scopeLockName($scope['key']), $scopeOwner)->release();
        }

        Log::info('SAPTO: segarkan laporan selesai.', ['by' => $by, 'scope' => $scope['key'], 'duration_ms' => $ms,
            'gagal' => $failed, 'key_ditulis' => count($recomputing->rewritten()), 'ms_per_tabel' => $timings]);

        return ['rewritten' => $recomputing->rewritten(), 'failed' => $failed, 'duration_ms' => $ms, 'timings' => $timings];
    }

    private function putStatus(string $scopeKey, array $status): void
    {
        Cache::put($this->statusKey($scopeKey), $status, self::STATUS_TTL);
    }

    private function statusKey(string $scopeKey): string
    {
        return 'sapto:refresh:status:'.$scopeKey;
    }

    private function scopeLockName(string $scopeKey): string
    {
        return 'sapto:refresh:scope:'.$scopeKey;
    }

    private function slotLockName(int $slot): string
    {
        return 'sapto:refresh:slot:'.$slot;
    }
}
