<?php

namespace Tests\Integration;

use App\Services\LearningPlans;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class CapacityConcurrencyTest extends TestCase
{
    use DatabaseMigrations, LearningFixtures;

    public function test_two_reviewers_cannot_overbook_the_same_member_day(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('training_foundation_test', config('database.connections.mariadb.database'));
        $this->seed();
        $firstEnrolment = $this->enrolment();
        $first = $this->fill($this->prepare());
        $secondEnrolment = $firstEnrolment->replicate();
        $secondEnrolment->title = 'Competing plan';
        $secondEnrolment->save();
        $second = $this->fill($this->prepare($secondEnrolment, 4, 30, false), $secondEnrolment);
        $service = app(LearningPlans::class);
        $service->review($this->person(), $firstEnrolment, $first->version, false, '');
        $service->review($this->person(), $secondEnrolment, $second->version, false, '');
        $connection = config('database.connections.mariadb');
        $environment = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mariadb',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'TRAINING_ENVIRONMENT' => 'test', 'MOCK_IDENTITY_ENABLED' => 'true', 'CACHE_STORE' => 'array'];
        $workers = [];
        foreach ([[$firstEnrolment, $first], [$secondEnrolment, $second]] as [$enrolment,$plan]) {
            $worker = new Process([PHP_BINARY, 'tests/Support/approve-plan.php', $enrolment->id, (string) $plan->version], base_path(), $environment, null, 30);
            $worker->start();
            $workers[] = $worker;
        }
        $codes = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $codes[] = $worker->getExitCode();
            if ($worker->getExitCode() === 2) {
                $this->assertStringContainsString('Shared capacity is exceeded', $worker->getOutput());
            } else {
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            }
        }
        sort($codes);
        $this->assertSame([0, 2], $codes);
        $this->assertDatabaseCount('programme_approvals', 1);
        $this->assertSame(4, DB::table('capacity_allocations')->where('active', true)->count());
        $this->assertSame(120, (int) DB::table('capacity_allocations')->where('active',true)->sum('minutes'));
    }
}
