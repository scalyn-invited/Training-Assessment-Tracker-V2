<?php

use App\Http\Middleware\LocalhostProduction;
use App\Http\Middleware\RequestContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(LocalhostProduction::class);
        $middleware->append(RequestContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'token']);
        $exceptions->render(function (HttpException $exception, Request $request) {
            $status = $exception->getStatusCode();
            if ($request->routeIs('local.*') && ! $request->expectsJson() && in_array($status, [403, 409, 410], true)) {
                return response()->view('local-accounts.error', compact('status'), $status);
            }
        });
    })->create();

if (getenv('TRAINING_RUNTIME_STORAGE')) {
    $app->useStoragePath(getenv('TRAINING_RUNTIME_STORAGE'));
}

return $app;
