<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LocalhostProduction
{
    public function handle(Request $request, Closure $next)
    {
        if (config('training.localhost_production')) {
            // Check the socket peer, not forwarded headers or trusted-proxy inference.
            abort_unless(in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
                && in_array($request->getHost(), ['127.0.0.1', 'localhost', '[::1]'], true), 403);
        }

        return $next($request);
    }
}
