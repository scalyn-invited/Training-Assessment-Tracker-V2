<?php

use App\Exceptions\AccountActionDenied;
use App\Http\Middleware\HostedPasswordTransport;
use App\Http\Middleware\InstallationGate;
use App\Http\Middleware\LocalhostProduction;
use App\Http\Middleware\RequestContext;
use App\Services\Tenancy\ClientAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Capture only server-level overrides, before Laravel loads .env.
$installerProcessEnvironment = [];
foreach (['APP_ENV', 'APP_DEBUG', 'APP_URL', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'TRAINING_DB_PASSWORD_B64', 'DB_URL', 'DB_SOCKET', 'TRAINING_AUTH_MODE', 'TRAINING_ENVIRONMENT', 'MOCK_IDENTITY_ENABLED', 'SESSION_DRIVER', 'SESSION_SECURE_COOKIE', 'TRAINING_LOCALHOST_PRODUCTION', 'TRAINING_HOSTED_PASSWORD_LOGIN', 'APP_KEY', 'TRAINING_INSTALL_STATE', 'TRAINING_INSTALL_MODE', 'TRAINING_LOCAL_DEVELOPMENT', 'SESSION_HTTP_ONLY', 'SESSION_SAME_SITE', 'SESSION_DOMAIN', 'SESSION_COOKIE', 'QUEUE_CONNECTION', 'CACHE_STORE', 'MAIL_MAILER', 'TRAINING_SMTP_ENABLED', 'LOCAL_ACCOUNT_MAIL_ENABLED', 'AI_LIVE_ENABLED', 'TRAINING_DEMO_ENABLED', 'TRAINING_PRIVATE_STORAGE'] as $key) {
    if (getenv($key) !== false) {
        $installerProcessEnvironment[$key] = getenv($key);
    }
}

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(LocalhostProduction::class);
        $middleware->prepend(InstallationGate::class);
        $middleware->append(HostedPasswordTransport::class);
        $middleware->append(RequestContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'token', 'setup_token', 'db_password', 'demo_password', 'demo_password_confirmation']);
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

$app->instance('installation.process_environment', $installerProcessEnvironment);

if (getenv('TRAINING_RUNTIME_STORAGE')) {
    $app->useStoragePath(getenv('TRAINING_RUNTIME_STORAGE'));
}

return $app;
