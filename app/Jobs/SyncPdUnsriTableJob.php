<?php

namespace App\Jobs;

use App\Models\PdUnsriSyncResult;
use App\Services\KafkaProducerService;
use App\Services\PangkalanDataApiClient;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Sinkronisasi satu tabel dari api-pangkalan-data.unsri.ac.id (lewat Gravitee,
 * lihat PangkalanDataApiClient::baseUrl()) ke tabel lokal yang dipetakan di
 * config/pd_unsri_sync_tables.php. Beberapa endpoint memakai nama lokal
 * berbeda dan dapat memerlukan normalisasi payload sebelum disimpan.
 *
 * Pola staging + atomic swap: data baru ditarik penuh ke tabel sementara
 * "<table>_sync_staging" dulu. Tabel asli ("live", yang dibaca dashboard dan
 * /api/v1/{table}) baru diganti lewat RENAME TABLE (atomic di MySQL) kalau
 * tarikannya lolos semua pengaman di bawah. Gagal di mana pun -> staging
 * dibuang, tabel live tetap berisi data sebelumnya, tidak pernah kosong atau
 * setengah terisi.
 *
 * Pengaman sebelum menukar:
 *  - sumber mengembalikan 0 baris padahal tabel lokal berisi -> tidak ditukar
 *    (lebih mungkin gangguan di sumber daripada datanya benar-benar habis);
 *  - baris yang diterima < 99% dari totalData yang dilaporkan sumber -> tidak
 *    ditukar (paginasi putus/terlewat);
 *  - kolom yang tidak dikirim API tapi berisi data di lokal -> nilainya
 *    disalin dari tabel lama (carry_over_keys), atau tidak ditukar sama sekali
 *    kalau tabelnya belum punya kunci.
 *
 * 404/403 dari API sumber = "tabel ini tidak tersedia/tidak diizinkan di sana"
 * -> dilewati tanpa menggagalkan batch; tabel lokal dibiarkan apa adanya.
 *
 * Hasil tiap tabel dicatat di pd_unsri_sync_results (dibaca halaman
 * /admin/sync-pd-unsri); baris "running" diperbarui tiap halaman supaya
 * halaman itu bisa menampilkan progres per tabel dan tahu worker masih hidup.
 */
class SyncPdUnsriTableJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const PAGE_SIZE = 1000; // batas maksimum limit di API sumber

    private const MIN_COMPLETENESS = 0.99;

    public int $tries = 1;

    public int $timeout = 3600;

    private ?PdUnsriSyncResult $record = null;

    public function __construct(
        public readonly string $slug,
        public readonly string $table,
    ) {}

    public function handle(PangkalanDataApiClient $client): void
    {
        if ($this->batch()?->cancelled()) {
            $this->recordFinished(PdUnsriSyncResult::CANCELLED, 'Sinkronisasi dibatalkan sebelum tabel ini mulai ditarik. Data lama tetap dipakai.');

            return;
        }

        if (! Schema::hasTable($this->table)) {
            Log::warning("PD UNSRI sync: tabel lokal '{$this->table}' tidak ada, {$this->slug} dilewati.");
            $this->recordFinished(PdUnsriSyncResult::SKIPPED, "Tabel lokal '{$this->table}' tidak ada di database SAPTO.");

            return;
        }

        $this->record = PdUnsriSyncResult::start($this->batchKey(), $this->slug, $this->table);

        $this->guardNoForeignKeys();

        $staging = $this->table.'_sync_staging';

        DB::statement("DROP TABLE IF EXISTS `{$staging}`");
        DB::statement("CREATE TABLE `{$staging}` LIKE `{$this->table}`");

        try {
            $result = $this->pull($client, $staging);

            if ($result['outcome'] !== 'ok') {
                DB::statement("DROP TABLE IF EXISTS `{$staging}`");
                $this->record->finish(
                    $result['outcome'] === 'cancelled' ? PdUnsriSyncResult::CANCELLED : PdUnsriSyncResult::SKIPPED,
                    $result['message'], $result['fetched'], $result['total'],
                );

                return;
            }

            $this->guardCompleteness($result);
            $this->carryOverUnsentColumns($staging, $result['unsent']);
        } catch (\Throwable $e) {
            DB::statement("DROP TABLE IF EXISTS `{$staging}`");

            throw $e;
        }

        $this->swap($staging);
        $this->record->finish(PdUnsriSyncResult::DONE, null, $result['fetched'], $result['total']);

        // Kabar untuk consumer Kafka (arsip event & halaman monitor). Tidak
        // pernah menggagalkan sinkronisasi: kegagalan kirim masuk outbox.
        app(KafkaProducerService::class)->publish('sapto.sync.table_updated', 'sync.table_updated', [
            'slug' => $this->slug,
            'table' => $this->table,
            'rows_fetched' => $result['fetched'],
            'rows_source' => $result['total'] ?? null,
            'batch_id' => $this->batchId,
        ], key: $this->table);

        Log::info(sprintf(
            'PD UNSRI sync: %s selesai, %d baris diterima (sumber melaporkan %s).',
            $this->slug, $result['fetched'], $result['total'] ?? '?',
        ));
    }

    /**
     * Dipanggil worker saat job gagal (exception, timeout, atau percobaan
     * habis) -- di instance job baru, jadi baris "running"-nya dicari ulang.
     */
    public function failed(\Throwable $e): void
    {
        $record = PdUnsriSyncResult::runningFor($this->batchKey(), $this->slug)
            ?? PdUnsriSyncResult::start($this->batchKey(), $this->slug, $this->table);

        $record->finish(PdUnsriSyncResult::FAILED, $e->getMessage(), $record->rows_fetched, $record->rows_source);
    }

    /**
     * @return array{outcome: 'ok'|'skipped'|'cancelled', message: string|null, fetched: int, total: int|null, unsent: list<string>}
     */
    private function pull(PangkalanDataApiClient $client, string $staging): array
    {
        $offset = 0;
        $total = null;
        $fetched = 0;
        $columns = Schema::getColumnListing($staging);
        $sent = [];

        while (true) {
            // Dicek tiap halaman supaya tombol "Batalkan" menghentikan tabel
            // besar dalam hitungan detik; staging dibuang oleh handle().
            if ($this->batch()?->cancelled()) {
                return [
                    'outcome' => 'cancelled',
                    'message' => "Dibatalkan setelah {$fetched} baris diterima. Tabel sementara dibuang; data lama tetap dipakai.",
                    'fetched' => $fetched, 'total' => $total, 'unsent' => [],
                ];
            }

            $response = $client->getRaw("api/v1/{$this->slug}", [
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
            ]);

            if (in_array($response->status(), [403, 404], true)) {
                Log::warning("PD UNSRI sync: {$this->slug} dilewati, HTTP {$response->status()} dari sumber.");

                return [
                    'outcome' => 'skipped',
                    'message' => $response->status() === 404
                        ? 'Tabel tidak tersedia di API PD UNSRI (HTTP 404). Data lokal tetap dipakai.'
                        : 'Akun API SAPTO belum diberi akses ke tabel ini oleh PD UNSRI (HTTP 403). Data lokal tetap dipakai.',
                    'fetched' => 0, 'total' => null, 'unsent' => [],
                ];
            }

            if ($response->failed()) {
                throw new RuntimeException("PD UNSRI sync gagal ambil {$this->slug} (offset {$offset}): HTTP {$response->status()}.");
            }

            $body = $response->json() ?? [];
            $data = array_values(array_filter((array) ($body['data'] ?? []), 'is_array'));
            $total = isset($body['totalData']) ? (int) $body['totalData'] : $total;

            if ($data === []) {
                break;
            }

            $chunk = [];

            foreach ($data as $row) {
                // Endpoint LPPM mengembalikan anggota_non_dosen sebagai array,
                // sedangkan tabel lokal menyimpannya sebagai JSON. Jalur
                // sinkronisasi batch memakai konversi yang sama dengan
                // command LPPM lama.
                if (in_array($this->slug, ['penelitian-dosen', 'pengabdian-dosen'], true)) {
                    $anggota = $row['anggota_non_dosen'] ?? [];
                    if (is_string($anggota)) {
                        $decoded = json_decode($anggota, true);
                        $anggota = is_array($decoded) ? $decoded : [];
                    }
                    $row['anggota_non_dosen'] = json_encode(
                        is_array($anggota) ? $anggota : [],
                        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                    );
                }

                // Timestamp lokal tidak dikirim oleh endpoint ini; beri nilai
                // saat sinkronisasi supaya kolom tersebut tidak memerlukan
                // carry-over dari tabel lama.
                if (in_array($this->slug, ['penelitian-dosen', 'pengabdian-dosen', 'sister-sdm'], true)) {
                    $syncedAt = now()->toDateTimeString();
                    foreach (['created_at', 'updated_at'] as $timestampColumn) {
                        if (in_array($timestampColumn, $columns, true) && empty($row[$timestampColumn])) {
                            $row[$timestampColumn] = $syncedAt;
                        }
                    }
                }

                $sent += array_fill_keys(array_keys($row), true);

                // Semua baris dipaksa punya set kolom yang sama supaya INSERT
                // multi-baris valid; kolom yang tidak dikirim diisi null dulu
                // (lalu diurus carryOverUnsentColumns()).
                $chunk[] = array_combine($columns, array_map(fn (string $col) => $row[$col] ?? null, $columns));
            }

            DB::table($staging)->insertOrIgnore($chunk);
            $fetched += count($data);
            $offset += self::PAGE_SIZE;

            // Progres + tanda worker masih hidup (updated_at) untuk halaman admin.
            $this->record?->update(['rows_fetched' => $fetched, 'rows_source' => $total]);

            if (count($data) < self::PAGE_SIZE || ($total !== null && $offset >= $total)) {
                break;
            }
        }

        return [
            'outcome' => 'ok',
            'message' => null,
            'fetched' => $fetched,
            'total' => $total,
            'unsent' => array_values(array_diff($columns, array_keys($sent))),
        ];
    }

    private function batchKey(): string
    {
        return $this->batchId ?? 'tanpa-batch';
    }

    private function recordFinished(string $status, string $message): void
    {
        PdUnsriSyncResult::create([
            'batch_id' => $this->batchKey(),
            'slug' => $this->slug,
            'table_name' => $this->table,
            'status' => $status,
            'message' => $message,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    /**
     * RENAME TABLE membawa foreign key yang MENUNJUK ke tabel ini ikut ke
     * tabel lama (lalu DROP-nya gagal dan FK-nya menunjuk tabel yang salah),
     * dan CREATE TABLE ... LIKE tidak menyalin FK MILIK tabel ini. Staging+swap
     * tidak aman untuk tabel yang terlibat FK -- tolak di awal sebelum
     * menyentuh apa pun.
     */
    private function guardNoForeignKeys(): void
    {
        $fk = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->where(fn ($q) => $q->where('TABLE_NAME', $this->table)->orWhere('REFERENCED_TABLE_NAME', $this->table))
            ->value('CONSTRAINT_NAME');

        if ($fk !== null) {
            throw new RuntimeException(
                "PD UNSRI sync: {$this->table} terlibat foreign key ({$fk}); staging+swap tidak aman untuk tabel ini -- tidak disentuh."
            );
        }
    }

    /** @param array{fetched: int, total: int|null} $result */
    private function guardCompleteness(array $result): void
    {
        if ($result['fetched'] === 0 && DB::table($this->table)->exists()) {
            throw new RuntimeException(
                "PD UNSRI sync: sumber mengembalikan 0 baris untuk {$this->slug}, padahal tabel lokal berisi -- tidak ditukar."
            );
        }

        if ($result['total'] !== null && $result['fetched'] < $result['total'] * self::MIN_COMPLETENESS) {
            throw new RuntimeException(sprintf(
                'PD UNSRI sync: %s hanya menerima %d dari %d baris yang dilaporkan sumber -- tidak ditukar.',
                $this->slug, $result['fetched'], $result['total'],
            ));
        }
    }

    /**
     * Kolom yang tidak pernah dikirim API (mis. data pribadi yang dirahasiakan
     * sumber) tapi berisi data di tabel lokal: salin nilainya dari tabel lama
     * ke staging lewat kunci di config pd_unsri_sync_tables.carry_over_keys.
     *
     * @param  list<string>  $unsent
     */
    private function carryOverUnsentColumns(string $staging, array $unsent): void
    {
        // Kolom auto_increment (mis. `id` bawaan Eloquent di tabel yang kunci
        // bisnis aslinya kolom lain seperti id_kurikulum) selalu "berisi data"
        // secara lokal -- 1, 2, 3, ... -- meski tidak pernah dikirim API sama
        // sekali. Itu bukan data yang perlu dijaga: nilainya terisi ulang
        // otomatis begitu baris masuk ke tabel staging, dan tidak berarti apa
        // pun di luar tabel ini (tabel yang jadi rujukan FK sudah ditolak
        // lebih dulu oleh guardNoForeignKeys()).
        $unsent = array_values(array_diff($unsent, array_filter([$this->autoIncrementColumn()])));

        $withData = array_values(array_filter(
            $unsent,
            fn (string $col): bool => DB::table($this->table)->whereNotNull($col)->exists(),
        ));

        if ($withData === []) {
            return;
        }

        $key = config("pd_unsri_sync_tables.carry_over_keys.{$this->table}");

        if (! $key) {
            throw new RuntimeException(sprintf(
                'PD UNSRI sync: API tidak mengirim kolom %s untuk %s, padahal kolom itu berisi data di tabel lokal. '
                .'Tidak ditukar supaya data itu tidak hilang -- tambahkan kunci tabel ini di '
                .'config/pd_unsri_sync_tables.php (carry_over_keys).',
                implode(', ', $withData), $this->slug,
            ));
        }

        $set = implode(', ', array_map(fn (string $col): string => "s.`{$col}` = l.`{$col}`", $withData));

        DB::statement("UPDATE `{$staging}` s JOIN `{$this->table}` l ON s.`{$key}` = l.`{$key}` SET {$set}");

        Log::info(sprintf(
            'PD UNSRI sync: %s -- %d kolom yang tidak dikirim API dipertahankan dari data lama: %s.',
            $this->slug, count($withData), implode(', ', $withData),
        ));
    }

    private function autoIncrementColumn(): ?string
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $this->table)
            ->where('EXTRA', 'like', '%auto_increment%')
            ->value('COLUMN_NAME');
    }

    private function swap(string $staging): void
    {
        $old = $this->table.'_sync_old';

        DB::statement("DROP TABLE IF EXISTS `{$old}`");
        DB::statement("RENAME TABLE `{$this->table}` TO `{$old}`, `{$staging}` TO `{$this->table}`");
        DB::statement("DROP TABLE IF EXISTS `{$old}`");
    }
}
