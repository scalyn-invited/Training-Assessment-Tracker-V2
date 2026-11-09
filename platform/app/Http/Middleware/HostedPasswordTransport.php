<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HostedPasswordTransport
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('production') && config('training.authentication_mode') === 'local_password'
            && config('training.hosted_password_login') && ! config('training.localhost_production')) {
            // Reject HTTP before session handling; never redirect a submitted password.
            abort_unless($request->isSecure(), 403, 'Use HTTPS to access this application.');
        }

        return $next($request);
    }
}
