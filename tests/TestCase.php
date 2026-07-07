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

        // Drop any connection that may already have been resolved so the new
        // sqlite config is what actually gets used.
        $app->make('db')->purge();
    }
}
