<?php

namespace App\Http\Middleware;

use App\Http\Controllers\InstallationController;
use App\Services\Installation\InstallationState;
use Closure;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Session\Middleware\StartSession;

class InstallationGate
{
    public function handle(Request $request, Closure $next)
    {
        $setup = $request->is('setup', 'setup/*');
        if (app()->environment('testing') && ! app()->bound('installation.testing')) {
            return $next($request);
        }
        $store = app(InstallationState::class);
        $state = $store->read();
        $localPeer = in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            && in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]'], true);
        if (config('installation.localhost_only')) {
            abort_unless($localPeer, 403);
        }
        if (! $store->fresh()) {
            abort_if($setup, 404);

            return $next($request);
        }
        $mode = $state['mode'] ?? config('installation.mode');
        abort_unless($mode === 'local' ? $localPeer : $request->isSecure(), 403);
        // Hosted setup is armed only from the server console, never by the first visitor.
        abort_if($mode === 'hosted' && ! $state, 503, 'The operator must prepare installation from the server console.');
        if (! $setup) {
            return redirect('/setup');
        }
        abort_unless($request->path() === 'setup' && in_array($request->method(), ['GET', 'POST'], true), 404);
        $state ??= $store->initialize($mode);
        config(['app.debug' => false, 'app.key' => $state['key'], 'session.driver' => 'file',
            'session.files' => $store->directory().'/sessions', 'session.cookie' => 'training_setup',
            'session.path' => '/setup', 'session.domain' => null, 'session.secure' => $mode === 'hosted',
            'session.http_only' => true, 'session.same_site' => 'strict', 'session.lifetime' => 60,
            'session.encrypt' => true, 'cache.default' => 'file']);
        if (! is_dir(config('session.files'))) {
            mkdir(config('session.files'), 0700, true);
        }
        $response = app(Pipeline::class)->send($request)->through([
            EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ValidateCsrfToken::class,
        ])->then(fn ($request) => app(InstallationController::class)->handle($request));
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
