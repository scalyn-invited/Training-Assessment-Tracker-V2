<?php

use App\Models\User;
use App\Services\LocalAccounts;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/client-qualification-guard.php';
assertClientQualificationDatabase();
config(['training.authentication_mode' => 'local_password']);
echo "READY\n";
fflush(STDOUT);
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    $service = app(LocalAccounts::class);
    match ($input['action']) {
        'create' => $service->create(User::findOrFail($input['actor']), $input['client'], 'Concurrent synthetic member', 'race@example.invalid', 'Fifteen plus characters!', 'Concurrent creation proof'),
        'redeem' => $service->setPassword($input['token'], 'setup', 'Fifteen plus characters!', 'Fifteen plus characters!'),
        'accept' => $service->accept(User::findOrFail($input['user']), $input['token']),
    };
    echo 'SUCCESS';
    exit(0);
} catch (HttpException $e) {
    echo 'DENIED:'.$e->getStatusCode();
    exit(in_array($e->getStatusCode(), [409, 410]) ? 2 : 3);
}
