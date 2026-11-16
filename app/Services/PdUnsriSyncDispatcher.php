<?php

namespace App\Services;

use App\Jobs\SyncPdUnsriTableJob;
use App\Models\PdUnsriSyncResult;
use App\Services\Kafka\KafkaMonitor;
use Cron\CronExpression;
use Illuminate\Bus\Batch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Titik tunggal untuk memulai sinkronisasi PD UNSRI, dipakai baik oleh
 * command terjadwal (mingguan) maupun tombol admin (manual). Satu batch =
 * satu job per tabel di config/pd_unsri_sync_tables.php -- lihat
 * SyncPdUnsriTableJob untuk mekanisme staging+swap per tabel. Setelah semua
 * job selesai (berhasil atau gagal), cache laporan dihitung ulang.
 *
 * statusPayload() adalah satu-satunya sumber data halaman
 * /admin/sync-pd-unsri (render awal maupun polling JSON).
 */
class PdUnsriSyncDispatcher
{
    public const BATCH_NAME = 'pd-unsri-sync';

    // Samakan dengan Schedule::command('sapto:sync-pd-unsri') di routes/console.php
    // -- file itu tidak dimuat saat request HTTP, jadi jadwalnya tidak bisa dibaca dari sana.
    public const SCHEDULE_CRON = '0 2 * * 0';

    public const SCHEDULE_TIMEZONE = 'Asia/Jakarta';

    /**
     * @param  list<string>  $hanyaTabel  Nama tabel tertentu saja (kosong = semua).
     *                                    Dipakai saat menambah tabel baru atau
     *                                    mengulang satu tabel yang gagal, supaya
     *                                    tidak menarik ulang jutaan baris tabel lain.
     */
    public function dispatch(string $triggeredBy = 'tidak diketahui', array $hanyaTabel = []): Batch
    {
        $jobs = [];

        foreach (config('pd_unsri_sync_tables.tables', []) as $slug => $table) {
            if ($hanyaTabel !== [] && ! in_array($table, $hanyaTabel, true)) {
                continue;
            }

            $jobs[] = new SyncPdUnsriTableJob($slug, $table);
        }

        if ($jobs === []) {
            throw new InvalidArgumentException('Tidak ada tabel yang cocok di config/pd_unsri_sync_tables.php.');
        }

        return Bus::batch($jobs)
            ->name(self::BATCH_NAME)
            ->allowFailures()
            ->withOption('triggered_by', $triggeredBy)
            // Objek invokable, bukan closure: callback ini diserialisasi ke
            // tabel job_batches (lihat PdUnsriSyncFinished).
            ->finally(new PdUnsriSyncFinished)
            ->dispatch();
    }

    /**
     * Kabar "putaran sinkronisasi selesai" ke Kafka (topic
     * sapto.sync.batch_finished). Consumer menjadikannya notifikasi admin.
     * Kegagalan di sini tidak boleh membatalkan callback batch, jadi semuanya
     * dibungkus: producer sendiri sudah menelan error jaringan, ini menjaga
     * dari kesalahan lain (mis. tabel hasil belum ada).
     */
    public static function announceBatchFinished(Batch $batch): void
    {
        try {
            $count = PdUnsriSyncResult::query()
                ->where('batch_id', $batch->id)
                ->selectRaw('status, COUNT(*) AS n')
                ->groupBy('status')
                ->pluck('n', 'status');

            $end = $batch->finishedAt ?? $batch->cancelledAt ?? now();

            app(KafkaProducerService::class)->publish('sapto.sync.batch_finished', 'sync.batch_finished', [
                'batch_id' => $batch->id,
                'triggered_by' => $batch->options['triggered_by'] ?? null,
                'total' => $batch->totalJobs,
                'done' => (int) ($count[PdUnsriSyncResult::DONE] ?? 0),
                'skipped' => (int) ($count[PdUnsriSyncResult::SKIPPED] ?? 0),
                'failed' => (int) ($count[PdUnsriSyncResult::FAILED] ?? 0) + $batch->failedJobs,
                'cancelled' => $batch->cancelled(),
                'duration_seconds' => (int) $batch->createdAt->diffInSeconds($end, true),
            ], key: $batch->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Batch yang paling baru dimulai, kalau ada. */
    public function latest(): ?Batch
    {
        $batchId = DB::table('job_batches')
            ->where('name', self::BATCH_NAME)
            ->orderByDesc('created_at')
            ->value('id');

        return $batchId ? Bus::findBatch($batchId) : null;
    }

    public function isRunning(): bool
    {
        $batch = $this->latest();

        return $batch !== null && ! $batch->finished() && ! $batch->cancelled();
    }

    /**
     * Batalkan batch yang sedang berjalan. Job yang belum mulai langsung
     * berhenti; job yang sedang menarik berhenti di halaman berikutnya dan
     * membuang staging-nya -- tabel live tidak pernah ditukar setengah jadi.
     */
    public function cancelLatest(): ?Batch
    {
        $batch = $this->latest();

        if ($batch === null || $batch->finished() || $batch->cancelled()) {
            return null;
        }

        $batch->cancel();

        return $batch->fresh();
    }

    /** @return list<array<string, mixed>> */
    public function history(int $n = 5): array
    {
        $batches = DB::table('job_batches')
            ->where('name', self::BATCH_NAME)
            ->orderByDesc('created_at')
            ->limit($n)
            ->get();

        $counts = PdUnsriSyncResult::query()
            ->whereIn('batch_id', $batches->pluck('id'))
            ->selectRaw('batch_id, status, COUNT(*) AS n')
            ->groupBy('batch_id', 'status')
            ->get()
            ->groupBy('batch_id');

        return $batches->map(function ($row) use ($counts): array {
            $batch = Bus::findBatch($row->id);
            $byStatus = ($counts->get($row->id) ?? collect())->pluck('n', 'status');

            return $this->batchSummary($batch) + [
                'results' => $byStatus->isEmpty() ? null : [
                    'done' => (int) $byStatus->get(PdUnsriSyncResult::DONE, 0),
                    'skipped' => (int) $byStatus->get(PdUnsriSyncResult::SKIPPED, 0),
                    'failed' => (int) $byStatus->get(PdUnsriSyncResult::FAILED, 0),
                    'cancelled' => (int) $byStatus->get(PdUnsriSyncResult::CANCELLED, 0),
                ],
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    public function statusPayload(): array
    {
        $batch = $this->latest();
        $tables = config('pd_unsri_sync_tables.tables', []);
        $usedBy = config('pd_unsri_sync_tables.used_by', []);
        $tableNames = array_values($tables);

        $batchResults = $batch
            ? PdUnsriSyncResult::query()->where('batch_id', $batch->id)->orderBy('id')->get()->keyBy('table_name')
            : collect();
        $lastDone = $this->lastSuccessPerTable($tableNames);
        $meta = $this->tableMeta($tableNames);
        $queued = $batch ? $this->queuedJob($batch->id) : null;
        $workerRunning = $this->workerRunning();

        $summary = $batch ? $this->batchSummary($batch) : null;
        // Batch yang dimulai sebelum pencatatan per tabel ada tidak punya satu
        // pun baris hasil; status per tabelnya diperkirakan dari waktu tabel
        // terakhir ditukar (CREATE_TIME berubah hanya saat swap).
        $tracked = $batchResults->isNotEmpty();
        $batchStart = $summary ? Carbon::parse($summary['started_at']) : null;

        $rows = [];

        foreach ($tables as $slug => $table) {
            $result = $batchResults->get($table);
            $info = $meta->get($table);
            $done = $lastDone->get($table);
            $tableUpdatedAt = $info?->CREATE_TIME ? Carbon::parse($info->CREATE_TIME) : null;

            $status = null;
            $source = null;

            if ($result) {
                $status = $result->status;
                $source = 'tercatat';
            } elseif ($batch && ! $tracked && $tableUpdatedAt && $batchStart && $tableUpdatedAt->gte($batchStart)) {
                $status = PdUnsriSyncResult::DONE;
                $source = 'perkiraan';
            } elseif ($batch && ($queued['slug'] ?? null) === $slug && ! $batch->finished()) {
                $status = PdUnsriSyncResult::RUNNING;
                $source = $tracked ? 'tercatat' : 'perkiraan';
            } elseif ($batch && ! $batch->finished() && ! $batch->cancelled()) {
                $status = 'pending';
                $source = $tracked ? 'tercatat' : 'perkiraan';
            }

            $rows[] = [
                'slug' => $slug,
                'table' => $table,
                'used_by' => $usedBy[$table] ?? [],
                'status' => $status,
                'status_source' => $source,
                'rows_fetched' => $result?->rows_fetched,
                'rows_source' => $result?->rows_source,
                'message' => $result?->message,
                'started_at' => $result?->started_at?->toIso8601String()
                    ?? ($status === PdUnsriSyncResult::RUNNING ? ($queued['reserved_at'] ?? null) : null),
                'finished_at' => $result?->finished_at?->toIso8601String(),
                'heartbeat_at' => $result?->updated_at?->toIso8601String(),
                'last_success_at' => $done?->finished_at?->toIso8601String(),
                'last_success_rows' => $done?->rows_fetched,
                'estimate_rows' => $info ? (int) $info->TABLE_ROWS : null,
                'table_updated_at' => $tableUpdatedAt?->toIso8601String(),
                'exists' => $info !== null,
            ];
        }

        $current = collect($rows)->firstWhere('status', PdUnsriSyncResult::RUNNING);

        if ($summary) {
            $summary['current'] = $current ? [
                'slug' => $current['slug'],
                'table' => $current['table'],
                'since' => $current['started_at'],
                'rows_fetched' => $current['rows_fetched'],
                'rows_source' => $current['rows_source'],
            ] : null;
            $summary['tracked'] = $tracked;
            // Batch belum selesai tapi tidak ada proses queue:work -- jangan
            // tampilkan "berjalan" padahal tidak ada yang mengerjakan.
            if ($summary['state'] === 'running' && $workerRunning === false) {
                $summary['state'] = 'stalled';
            }
        }

        return [
            'server_time' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'batch' => $summary,
            'worker_running' => $workerRunning,
            'tables' => $rows,
            'counts' => collect($rows)->countBy(fn (array $r) => $r['status'] ?? 'unknown')->all(),
            'total_tables' => count($rows),
            'next_run_at' => $this->nextScheduledRun()->toIso8601String(),
            'history' => $this->history(5),
            // Status nyata dari producer & consumer (cache + database, tanpa
            // jaringan) -- dulu kartu ini selalu menulis "Aktif & Terhubung"
            // walau Kafka tidak pernah menerima satu pesan pun.
            'kafka' => app(KafkaMonitor::class)->brief() + [
                'topic' => 'sapto.sync.table_updated',
                'url' => route('admin.kafka'),
            ],
        ];
    }

    public function nextScheduledRun(): Carbon
    {
        $tz = self::SCHEDULE_TIMEZONE;

        return Carbon::instance(
            (new CronExpression(self::SCHEDULE_CRON))->getNextRunDate(now($tz), 0, false, $tz)
        )->setTimezone($tz);
    }

    /** @return array<string, mixed> */
    private function batchSummary(?Batch $batch): array
    {
        if ($batch === null) {
            return [];
        }

        $processed = $batch->totalJobs - $batch->pendingJobs;
        $end = $batch->finishedAt ?? $batch->cancelledAt;

        return [
            'id' => $batch->id,
            'short_id' => substr($batch->id, 0, 8),
            'state' => $batch->cancelled() ? 'cancelled' : ($batch->finished() ? 'finished' : 'running'),
            'total' => $batch->totalJobs,
            'processed' => $processed,
            'pending' => $batch->pendingJobs,
            'failed' => $batch->failedJobs,
            'progress' => $batch->totalJobs > 0 ? (int) floor($processed / $batch->totalJobs * 100) : 0,
            'started_at' => $batch->createdAt->toIso8601String(),
            'finished_at' => $batch->finishedAt?->toIso8601String(),
            'cancelled_at' => $batch->cancelledAt?->toIso8601String(),
            'duration_seconds' => (int) $batch->createdAt->diffInSeconds($end ?? now(), true),
            'triggered_by' => $batch->options['triggered_by'] ?? null,
        ];
    }

    /** Hasil sukses terakhir per tabel (lintas batch) -- "terakhir diperbarui". */
    private function lastSuccessPerTable(array $tableNames): Collection
    {
        $ids = PdUnsriSyncResult::query()
            ->whereIn('table_name', $tableNames)
            ->where('status', PdUnsriSyncResult::DONE)
            ->selectRaw('MAX(id) AS id')
            ->groupBy('table_name')
            ->pluck('id');

        return PdUnsriSyncResult::query()->whereIn('id', $ids)->get()->keyBy('table_name');
    }

    /**
     * Perkiraan jumlah baris (TABLE_ROWS, statistik InnoDB -- bukan COUNT(*),
     * yang terlalu mahal untuk tabel ratusan ribu baris tiap 5 detik) dan
     * CREATE_TIME, yang di tabel hasil swap sama dengan saat tarikan terakhir dimulai.
     */
    private function tableMeta(array $tableNames): Collection
    {
        return DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->whereIn('TABLE_NAME', $tableNames)
            ->get(['TABLE_NAME', 'TABLE_ROWS', 'CREATE_TIME'])
            ->keyBy('TABLE_NAME');
    }

    /**
     * Job batch ini yang sedang dipegang worker (reserved) -- satu-satunya cara
     * tahu tabel yang sedang ditarik untuk batch tanpa baris hasil.
     *
     * @return array{slug: string, reserved_at: string}|null
     */
    private function queuedJob(string $batchId): ?array
    {
        $table = config('queue.connections.database.table', 'jobs');

        if (config('queue.default') !== 'database' || ! Schema::hasTable($table)) {
            return null;
        }

        foreach (DB::table($table)->whereNotNull('reserved_at')->orderByDesc('reserved_at')->limit(5)->get() as $job) {
            $command = json_decode($job->payload, true)['data']['command'] ?? '';

            if (! str_contains($command, $batchId)) {
                continue;
            }

            if (preg_match('/"slug";s:\d+:"([^"]+)"/', $command, $m)) {
                return [
                    'slug' => $m[1],
                    'reserved_at' => Carbon::createFromTimestamp($job->reserved_at)->toIso8601String(),
                ];
            }
        }

        return null;
    }

    /**
     * Ada proses `php artisan queue:work` di container ini? Worker jalan di
     * container yang sama dengan web server (docker/entrypoint.sh), jadi cukup
     * dibaca dari /proc. null kalau /proc tidak bisa dibaca.
     */
    private function workerRunning(): ?bool
    {
        $cmdlines = @glob('/proc/[0-9]*/cmdline');

        if (! $cmdlines) {
            return null;
        }

        $self = getmypid();

        foreach ($cmdlines as $file) {
            if ((int) basename(dirname($file)) === $self) {
                continue;
            }

            // argv dipisah \0 -- mencocokkan "artisan\0queue:work" hanya kena
            // proses PHP worker, bukan shell pembungkusnya atau perintah lain
            // yang kebetulan menyebut teks itu.
            if (str_contains((string) @file_get_contents($file), "artisan\0queue:work")) {
                return true;
            }
        }

        return false;
    }
}
