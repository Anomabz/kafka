<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = hasil sinkronisasi satu tabel dalam satu batch PD UNSRI.
 * Ditulis SyncPdUnsriTableJob, dibaca PdUnsriSyncDispatcher::statusPayload().
 */
class PdUnsriSyncResult extends Model
{
    public const RUNNING = 'running';

    public const DONE = 'done';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    protected $table = 'pd_unsri_sync_results';

    protected $fillable = [
        'batch_id', 'slug', 'table_name', 'status',
        'rows_fetched', 'rows_source', 'message', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'rows_fetched' => 'integer',
        'rows_source' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public static function start(string $batchId, string $slug, string $table): self
    {
        return self::create([
            'batch_id' => $batchId,
            'slug' => $slug,
            'table_name' => $table,
            'status' => self::RUNNING,
            'started_at' => now(),
        ]);
    }

    public function finish(string $status, ?string $message = null, ?int $fetched = null, ?int $source = null): void
    {
        $this->update([
            'status' => $status,
            'message' => $message !== null ? mb_substr($message, 0, 2000) : null,
            'rows_fetched' => $fetched,
            'rows_source' => $source,
            'finished_at' => now(),
        ]);
    }

    /** Baris "running" terakhir untuk job ini -- dipakai failed() yang tidak punya referensi ke instance-nya. */
    public static function runningFor(string $batchId, string $slug): ?self
    {
        return self::query()
            ->where('batch_id', $batchId)
            ->where('slug', $slug)
            ->where('status', self::RUNNING)
            ->latest('id')
            ->first();
    }
}
