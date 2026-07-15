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
}
