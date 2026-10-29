<?php

use App\Exceptions\AccountActionDenied;
use App\Http\Middleware\LocalhostProduction;
use App\Http\Middleware\RequestContext;
use App\Services\Tenancy\ClientAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        $exceptions->render(function (ValidationException $exception, Request $request) {
            // Credential forms suppress referrers; never fall back to a logo URL in session history.
            if ($request->routeIs('local.password.save', 'local.password.change', 'local.invitation.accept')) {
                $exception->redirectTo($request->url());
            } elseif ($request->routeIs('local.actions')) {
                $route = $request->input('action') === 'create'
                    ? (app(ClientAccess::class)->globalAdmin($request->user()) ? 'platform.overview' : 'administration.show')
                    : 'local.links';
                $exception->redirectTo(route($route));
            } elseif ($request->routeIs('platform.act', 'platform.appearance')) {
                $section = $request->routeIs('platform.appearance') ? 'appearance'
                    : (in_array($request->input('action'), ['appoint', 'remove']) ? 'admins' : 'clients');
                $exception->redirectTo(route('platform.section', $section));
            } elseif ($request->routeIs('administration.act')) {
                $exception->redirectTo(route('administration.show'));
            }
        });
        $exceptions->render(function (HttpException $exception, Request $request) {
            $status = $exception->getStatusCode();
            if ($request->routeIs('local.*') && ! $request->expectsJson() && in_array($status, [403, 409, 410], true)) {
                $actionMessage = $request->routeIs('local.actions') && $exception instanceof AccountActionDenied
                    ? $exception->getMessage() : null;

                return response()->view('local-accounts.error', compact('status', 'actionMessage'), $status);
            }
        });
    })->create();

if (getenv('TRAINING_RUNTIME_STORAGE')) {
    $app->useStoragePath(getenv('TRAINING_RUNTIME_STORAGE'));
}

return $app;
