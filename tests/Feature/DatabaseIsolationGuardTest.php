<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression guard for the 2026-07-02 incident, when the test suite ran against
 * the live MySQL database and RefreshDatabase (migrate:fresh) dropped every
 * table. This test fails loudly if the suite is ever pointed at anything other
 * than an isolated in-memory SQLite database. It intentionally does NOT use
 * RefreshDatabase, so it can never itself mutate a database.
 */
class DatabaseIsolationGuardTest extends TestCase
{
    public function test_test_suite_runs_on_isolated_sqlite_memory(): void
    {
        $this->assertSame('sqlite', config('database.default'), 'Tests must default to sqlite, never mysql');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'), 'Test sqlite DB must be :memory:');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'Active connection driver must be sqlite, NOT mysql');
        $this->assertNotSame('mysql', config('database.default'));
    }

    /**
     * Regresi 2026-09-30: config/queue.php membaca env('DB_CONNECTION') langsung
     * (bukan database.default), jadi batch dari tes menulis ke job_batches
     * PRODUKSI. Tidak satu pun konfigurasi yang bisa menyimpan data boleh
     * menunjuk ke koneksi selain sqlite in-memory.
     */
    public function test_no_persistence_config_points_at_a_real_database(): void
    {
        foreach ([
            'queue.batching.database',
            'queue.failed.database',
            'queue.connections.database.connection',
            'session.connection',
            'cache.stores.database.connection',
        ] as $key) {
            $this->assertSame('sqlite', config($key), "{$key} harus sqlite di tes, bukan koneksi produksi");
        }

        $batchDb = DB::connection(config('queue.batching.database'))->getDatabaseName();
        $this->assertSame(':memory:', $batchDb, 'Batch dari tes harus masuk sqlite in-memory');
    }
}
