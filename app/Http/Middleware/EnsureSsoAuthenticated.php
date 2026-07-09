<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSsoAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->has('sso_user')) {
            if ($request->expectsJson() || $request->is('api/*')) {
                abort(401, 'Sesi tidak valid. Silakan login ulang.');
            }

            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('sso.login');
        }

        return $next($request);
    }
}
