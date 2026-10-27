<?php

use App\Http\Controllers\BrandingController;
use App\Http\Controllers\ThemePreferenceController;
use App\Models\Enrolment;
use App\Models\User;
use App\Services\LearningPlans;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientAdministration;
use App\Services\Tenancy\ClientContext;
use App\Services\Tenancy\PlatformAdministration;
use App\Services\Tenancy\SensitiveAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/client-qualification-guard.php';
assertClientQualificationDatabase();
$actor = User::findOrFail($argv[2]);
echo "READY\n";
fflush(STDOUT);
// Parent releases both contenders only after both processes have booted.
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(4);
}
try {
    $service = app(ClientAdministration::class);
    match ($argv[1]) {
        'platform-revoke' => app(PlatformAdministration::class)->revoke($actor, $argv[3], 'Concurrent Global Admin removal'),
        'platform-appoint' => app(PlatformAdministration::class)->appoint($actor, $argv[3], 'Concurrent Global Admin appointment'),
        'platform-client' => app(PlatformAdministration::class)->client($actor, $argv[3], 'Concurrent client', 0, 'Concurrent client update', true),
        'preference' => savePreference($actor, $argv[3]),
        'branding' => saveBranding($actor, $argv[3]),
        'sensitive' => app(SensitiveAccess::class)->grant($actor, $argv[3], $argv[4], 'Concurrent sensitive grant proof'),
        'approve' => app(LearningPlans::class)->review($actor, Enrolment::findOrFail($argv[4]), (int) $argv[5], true, 'Concurrent programme approval proof'),
        'primary' => $service->allocate($actor, $argv[3], $argv[4], $argv[5], 'primary', 'Concurrent primary allocation proof'),
        'grant' => $service->grantRole($actor, $argv[3], $argv[4], 'member', null, 'Concurrent identical role grant proof'),
        'team' => $service->createGroup($actor, $argv[3], 'Revocation race group', 'team', 'Revocation race mutation proof'),
        'read' => print (app(ClientAccess::class)->administer($actor, $argv[3]) ? 'ALLOWED' : 'DENIED'),
        default => throw new RuntimeException('Unknown qualification operation'),
    };
    exit(0);
} catch (HttpException $exception) {
    echo 'DENIED:'.$exception->getStatusCode();
    exit($exception->getStatusCode() === 409 ? 2 : 3);
}

function saveBranding(User $actor, string $client): void
{
    $context = app(ClientContext::class);
    $context->id = $client;
    $request = Request::create('/settings/appearance', 'POST', [
        'client_id' => $client, 'version' => 0, 'preview_mode' => 'white-label',
        'preview_client' => 'Concurrent brand', 'preview_name' => 'Concurrent academy', 'preview_accent' => '#123456',
    ]);
    $request->setUserResolver(fn () => $actor);
    try {
        app(BrandingController::class)->save($request, $context, app(ClientAccess::class));
    } catch (ValidationException $error) {
        if (isset($error->errors()['version'])) {
            exit(2);
        }
        throw $error;
    }
}

function savePreference(User $actor, string $client): void
{
    $context = app(ClientContext::class);
    $context->id = $client;
    $request = Request::create('/settings/appearance/preference', 'POST', ['client_id' => $client, 'version' => 0, 'theme' => 'dark']);
    $request->setUserResolver(fn () => $actor);
    app(ThemePreferenceController::class)->save($request, $context);
}
