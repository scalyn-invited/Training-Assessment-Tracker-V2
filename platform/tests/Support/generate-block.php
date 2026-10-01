<?php

use App\Models\Enrolment;
use App\Models\User;
use App\Services\Ai\Generation;
use App\Services\Ai\MockProvider;
use App\Services\Ai\ProviderResult;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mariadb'
    || config('database.connections.mariadb.database') !== 'training_foundation_test') {
    exit(3);
}
$app->bind(MockProvider::class, fn () => new class extends MockProvider
{
    public function generate(array $configuration, array $input): ProviderResult
    {
        usleep(600000);

        return parent::generate($configuration, $input);
    }
});
if (($argv[2] ?? '') === 'reserve') {
    $enrolment = Enrolment::findOrFail($argv[1]);
    try {
        app(Generation::class)->request(User::findOrFail($enrolment->coordinator_id), $enrolment, 1, 'budget-'.$enrolment->id);
    } catch (HttpException $exception) {
        if ($exception->getStatusCode() !== 422) {
            throw $exception;
        }
        echo 'budget-denied';
        exit(2);
    }
} else {
    app(Generation::class)->process($argv[1]);
}
