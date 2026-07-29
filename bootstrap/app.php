<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'sso.auth' => \App\Http\Middleware\EnsureSsoAuthenticated::class,
        ]);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Server ini di belakang reverse proxy eksternal (TLS di-terminate
        // di luar container) -- tanpa ini, Request::isSecure() salah baca
        // skema jadi http meski publiknya https, bikin cookie Secure/HSTS/
        // redirect URL semua salah. IP proxy asli tidak diketahui dari sini
        // (jaringan universitas), jadi percaya X-Forwarded-* dari mana saja;
        // ganti ke IP proxy spesifik kalau sudah diketahui.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // /sapto/mahasiswa-asing* juga endpoint JSON walau di-routing lewat
        // web.php, bukan api.php (lihat EnsureSsoAuthenticated yang sudah
        // pakai expectsJson() untuk alasan yang sama) — jangan cuma is('api/*').
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson() || $request->is('api/*'),
        );

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage() ?: 'Permintaan tidak dapat diproses.',
            ], $exception->getStatusCode());
        });

        $exceptions->render(function (QueryException|PDOException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Database SAPTO tidak dapat diakses. Periksa host, port, kredensial, whitelist, atau VPN database.',
            ], 503);
        });
    })->create();
