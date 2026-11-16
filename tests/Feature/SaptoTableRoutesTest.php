<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tiap tabel di halaman SAPTO memanggil /api/sapto/tables/{endpoint}/summary
 * (GENERIC_TABLES di sapto.blade.php). 2026-09-30 routes/api.php sempat
 * dikembalikan ke versi commit dan 15 rute hilang: tabelnya menjawab 404 di
 * situs tanpa ada tes yang gagal. Tes ini menutup celah itu.
 */
class SaptoTableRoutesTest extends TestCase
{
    public function test_every_table_endpoint_used_by_the_page_has_a_route(): void
    {
        $page = file_get_contents(resource_path('views/sapto.blade.php'));
        preg_match_all("/endpoint: '([a-z0-9-]+)'/", $page, $m);
        $endpoints = array_unique($m[1]);

        $this->assertGreaterThan(30, count($endpoints), 'Daftar endpoint tabel di halaman tidak terbaca.');

        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->all();
        $missing = array_values(array_filter(
            $endpoints,
            fn (string $e): bool => ! in_array("api/sapto/tables/{$e}/summary", $uris, true),
        ));

        $this->assertSame([], $missing, 'Endpoint tabel tanpa rute (404 di situs): '.implode(', ', $missing));
    }
}
