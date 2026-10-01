<?php

namespace Tests\Integration;

use App\Models\AiBlock;
use App\Models\AiRun;
use App\Services\Ai\Generation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class AiConcurrencyTest extends TestCase
{
    use DatabaseMigrations, LearningFixtures;

    public function test_duplicate_workers_make_only_one_provider_attempt(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('training_foundation_test', config('database.connections.mariadb.database'));
        Queue::fake();
        $this->seed();
        $plan = $this->prepare();
        $run = app(Generation::class)->request($this->person(), $this->enrolment(), $plan->version, 'concurrent-queue-test');
        $block = AiBlock::where('ai_run_id', $run->id)->firstOrFail();
        $connection = config('database.connections.mariadb');
        $environment = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mariadb',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'TRAINING_ENVIRONMENT' => 'test', 'MOCK_IDENTITY_ENABLED' => 'true', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'database'];
        $workers = [];
        for ($i = 0; $i < 2; $i++) {
            $worker = new Process([PHP_BINARY, 'tests/Support/generate-block.php', $block->id], base_path(), $environment, null, 30);
            $worker->start();
            $workers[] = $worker;
        }
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
        }
        $this->assertDatabaseCount('ai_attempts', 1);
        $this->assertSame('complete', $block->fresh()->status);
        $this->assertSame(600, $run->fresh()->spent);
    }

    public function test_competing_programmes_cannot_over_reserve_organisation_budget(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('training_foundation_test', config('database.connections.mariadb.database'));
        Queue::fake();
        $this->seed();
        $this->prepare();
        $second = $this->enrolment()->replicate();
        $second->title = 'Second synthetic programme';
        $second->save();
        $this->prepare($second, 4, 30, false);
        $connection = config('database.connections.mariadb');
        $environment = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mariadb',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'TRAINING_ENVIRONMENT' => 'test', 'MOCK_IDENTITY_ENABLED' => 'true', 'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'database', 'AI_ORGANISATION_LIMIT_MICRO_USD' => '2000000'];
        $workers = [];
        foreach ([$this->enrolment()->id, $second->id] as $id) {
            $worker = new Process([PHP_BINARY, 'tests/Support/generate-block.php', $id, 'reserve'], base_path(), $environment, null, 30);
            $worker->start();
            $workers[] = $worker;
        }
        $codes = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $codes[] = $worker->getExitCode();
            if ($worker->getExitCode() === 2) {
                $this->assertSame('budget-denied', $worker->getOutput());
            } else {
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            }
        }
        sort($codes);
        $this->assertSame([0, 2], $codes);
        $this->assertDatabaseCount('ai_runs', 1);
        $this->assertLessThanOrEqual(2000000, (int) AiRun::sum('reserved'));
    }
}
