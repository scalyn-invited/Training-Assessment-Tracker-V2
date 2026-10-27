<?php

use App\Models\Enrolment;
use App\Models\User;
use App\Services\LearningPlans;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientAdministration;
use App\Services\Tenancy\SensitiveAccess;
use Illuminate\Contracts\Console\Kernel;
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
