<?php

namespace App\Http\Middleware;

use App\Services\LocalAccounts;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestContext
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('request_id', (string) Str::uuid());
        $response = $next($request);
        $response->headers->set('X-Request-ID', $request->attributes->get('request_id'));
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $credentials = $request->routeIs('local.*', 'login', 'login.store')
            || (app(LocalAccounts::class)->enabled() && $request->routeIs('platform.*', 'administration.*'));
        $response->headers->set('Referrer-Policy', $credentials ? 'no-referrer' : 'same-origin');
        $response->headers->set('X-Frame-Options', 'DENY');

        return $response;
    }
}
