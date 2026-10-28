<?php

use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\User;
use App\Services\InitialAssessments;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Queue::fake();
$actor = User::findOrFail($argv[2]);
echo "READY\n";
fflush(STDOUT);
fgets(STDIN);
try {
    $s = app(InitialAssessments::class);
    if ($argv[1] === 'submit') {
        $s->submit($actor, AssessmentAssignment::findOrFail($argv[3]), (int) $argv[4], $argv[5]);
    } else {
        $t = AssessmentAttempt::findOrFail($argv[3]);
        $s->decide($actor, $t, (int) $argv[4], 'rejected', 'Concurrent isolated reviewer decision.', null);
    }
    exit(0);
} catch (HttpException $e) {
    if ($e->getStatusCode() === 409) {
        exit(2);
    }
    throw $e;
}
