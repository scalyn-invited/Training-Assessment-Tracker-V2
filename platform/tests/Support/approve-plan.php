<?php

use App\Models\Enrolment;
use App\Models\User;
use App\Services\LearningPlans;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

// Child-process probe used only by the dedicated MariaDB concurrency test.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mariadb'
    || config('database.connections.mariadb.database') !== 'training_foundation_test') {
    exit(3);
}
$enrolment = Enrolment::findOrFail($argv[1]);
$actor = User::findOrFail($enrolment->coordinator_id);
try {
    app(LearningPlans::class)->review($actor, $enrolment, (int) $argv[2], true, 'Concurrent approval capacity proof.');
    exit(0);
} catch (ValidationException $error) {
    echo json_encode($error->errors(), JSON_THROW_ON_ERROR);
    exit(2);
}
