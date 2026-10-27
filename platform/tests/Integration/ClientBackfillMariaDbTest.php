<?php

namespace Tests\Integration;

use App\Services\Tenancy\ClientAccess;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Feature\ClientBackfillTest;

class ClientBackfillMariaDbTest extends ClientBackfillTest
{
    protected bool $maria = true;

    private array $workers = [];

    protected function tearDown(): void
    {
        foreach ($this->workers as [$worker]) {
            if ($worker->isRunning()) {
                $worker->stop(1);
            }
        }
        parent::tearDown();
    }

    private function startWorker(string $mode, array $m, array $extra = []): array
    {
        $directory = base_path('.runtime/client-backfill');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = tempnam($directory, 'manifest-');
        file_put_contents($path, json_encode($m, JSON_THROW_ON_ERROR));
        $db = config('database.connections.mariadb');
        $environment = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mariadb',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'], 'DB_URL' => false,
            'TRAINING_ENVIRONMENT' => 'test', 'MOCK_IDENTITY_ENABLED' => 'true', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync'];
        $input = new InputStream;
        $worker = new Process([PHP_BINARY, 'tests/Support/client-backfill-worker.php', $mode, $path, ...$extra], base_path(), $environment, $input, 20);
        $worker->start();
        $this->workers[] = [$worker, $input];
        $this->waitFor($worker, 'READY');

        return [$worker, $input];
    }

    private function waitFor(Process $worker, string $marker): void
    {
        $deadline = microtime(true) + 10;
        while (! str_contains($worker->getOutput(), $marker) && $worker->isRunning() && microtime(true) < $deadline) {
            usleep(10000);
        }
        $this->assertStringContainsString($marker, $worker->getOutput(), $worker->getErrorOutput());
    }

    public function test_concurrent_identical_applications_commit_one_mapping_and_audit(): void
    {
        $m = $this->manifest();
        $workers = [$this->startWorker('apply', $m), $this->startWorker('apply', $m)];
        foreach ($workers as [, $input]) {
            $input->write("GO\n");
            $input->close();
        }
        $results = [];
        foreach ($workers as [$worker]) {
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $results[] = trim(str_replace('READY', '', $worker->getOutput()));
        }
        sort($results);
        $this->assertSame(['already_applied', 'applied'], $results);
        $this->assertDatabaseCount('client_memberships', 5);
        $this->assertDatabaseCount('role_assignments', 5);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.rehearsal.applied')->count());
    }

    public function test_concurrent_revocation_waits_for_mapping_and_is_not_lost_on_retry(): void
    {
        $m = $this->manifest();
        $admin = $this->person('admin');
        $coordinator = $this->person();
        [$worker, $input] = $this->startWorker('revoke', $m, [$admin->organisation_id, $admin->id, $coordinator->id]);
        $this->runManifest($m, true, function () use ($worker, $input) {
            $input->write("GO\n");
            $input->close();
            $this->waitFor($worker, 'TRY_LOCK');
            usleep(250000);
            $this->assertTrue($worker->isRunning());
            $this->assertStringNotContainsString('REVOKED', $worker->getOutput());
        });
        $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
        $this->assertStringContainsString('REVOKED', $worker->getOutput());
        $enrolment = $this->enrolment();
        $member = DB::table('client_memberships')->where('user_id', $enrolment->member_id)->first();
        $this->assertFalse(app(ClientAccess::class)->manageTraining($coordinator, $member->id, $enrolment->group_id, now()));
        $this->assertSame('already_applied', $this->runManifest($m)['status']);
        $this->assertFalse(app(ClientAccess::class)->viewTraining($coordinator, $member->id, $enrolment->group_id, now()));
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.role.revoked')->count());
    }
}
