<?php

namespace Tests\Integration;

use App\Jobs\DeliverOutbox;
use App\Models\Enrolment;
use App\Models\User;
use App\Services\Outbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WorkerConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_real_workers_cannot_publish_the_same_event_twice(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('training_foundation_test', config('database.connections.mariadb.database'));
        config(['training.mock_identity' => true, 'training.environment' => 'test', 'queue.default' => 'database']);
        $this->seed();
        $actor = User::where('email', 'member-a@example.invalid')->firstOrFail();
        $enrolment = Enrolment::where('member_id', $actor->id)->firstOrFail();
        $event = app(Outbox::class)->requestCheck($actor, $enrolment, 1, 'worker-concurrency-proof');
        Queue::connection('database')->push(new DeliverOutbox($event->id), '', 'notifications');
        $this->assertDatabaseCount('jobs', 2);
        $connection = config('database.connections.mariadb');
        $environment = [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mariadb',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'TRAINING_ENVIRONMENT' => 'test', 'MOCK_IDENTITY_ENABLED' => 'true', 'QUEUE_CONNECTION' => 'database',
        ];
        $workers = [];
        for ($i = 0; $i < 2; $i++) {
            $process = new Process([PHP_BINARY, 'artisan', 'queue:work', '--queue=notifications', '--stop-when-empty', '--tries=3'], base_path(), $environment, null, 30);
            $process->start();
            $workers[] = $process;
        }
        foreach ($workers as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        }
        $this->assertSame('delivered', $event->fresh()->status);
        $this->assertDatabaseCount('notification_receipts', 1);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'foundation.check.completed')->count());
    }
}
