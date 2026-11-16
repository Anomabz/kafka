<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Hard safety guard against re-wiping the live database.
     *
     * docker-compose injects DB_CONNECTION=mysql, DB_DATABASE=sapto_db and
     * APP_ENV=local as real OS environment variables. Those OS vars override
     * phpunit.xml's <env ... force="true"> in this setup, so the test suite
     * would boot against the live MySQL and RefreshDatabase (migrate:fresh)
     * would DROP every table — which is exactly what happened on 2026-07-02.
     *
     * We therefore force an isolated in-memory SQLite database in code, at the
     * application-creation stage (before RefreshDatabase migrates), regardless
     * of environment variables. Do NOT remove this unless phpunit.xml isolation
     * is verified to override the container OS env (run ZzIsolationGuardTest).
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $this->forceIsolatedTestDatabase($app);

        return $app;
    }

    /**
     * Second line of defence: re-assert isolation right before migrate:fresh.
     */
    protected function beforeRefreshingDatabase()
    {
        $this->forceIsolatedTestDatabase($this->app);
    }

    private function forceIsolatedTestDatabase($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'url' => null,
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // config/queue.php membaca env('DB_CONNECTION') LANGSUNG untuk batch
        // dan failed_jobs, bukan database.default -- jadi tanpa ini `mysql`
        // dari environment container tetap terpakai. 2026-09-30: tes yang
        // membuat batch menulis empat baris `running` ke job_batches PRODUKSI,
        // dan sinkronisasi mingguan hampir dilewati karena dikira masih
        // berjalan. Semua konfigurasi yang bisa menunjuk database di luar
        // config('database.default') harus dipaksa di sini.
        $app['config']->set('queue.batching.database', 'sqlite');
        $app['config']->set('queue.failed.database', 'sqlite');
        $app['config']->set('queue.connections.database.connection', 'sqlite');
        $app['config']->set('session.connection', 'sqlite');
        $app['config']->set('cache.stores.database.connection', 'sqlite');

        // Repository batch dibuat sekali lalu disimpan; buang supaya dibuat
        // ulang dengan koneksi di atas.
        $app->forgetInstance(\Illuminate\Bus\BatchRepository::class);

        // Drop any connection that may already have been resolved so the new
        // sqlite config is what actually gets used.
        $app->make('db')->purge();
    }
}
