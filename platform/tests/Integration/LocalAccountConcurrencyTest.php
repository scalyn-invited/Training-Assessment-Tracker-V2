<?php

namespace Tests\Integration;

use App\Models\User;
use App\Services\LocalAccounts;
use App\Services\Tenancy\PlatformAdministration;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LocalAccountConcurrencyTest extends TestCase
{
    private User $admin;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__.'/../Support/client-qualification-guard.php';
        assertClientQualificationDatabase();
        Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
        $this->admin = User::where('email', 'admin@example.invalid')->firstOrFail();
        app(PlatformAdministration::class)->appoint(null, $this->admin->id, 'Concurrent synthetic bootstrap', 'm2-test');
        config(['training.authentication_mode' => 'local_password']);
        $service = app(LocalAccounts::class);
        $link = $service->bootstrap($this->admin->fresh(), 'm2-test', 'Initial synthetic setup');
        $service->setPassword($link['token'], 'setup', 'Fifteen plus characters!', 'Fifteen plus characters!');
        $this->admin->refresh();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop(1);
            }
        }
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function race(array $data): void
    {
        $db = config('database.connections.mariadb');
        $env = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mariadb',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'], 'DB_URL' => false,
            'TRAINING_ENVIRONMENT' => 'test', 'TRAINING_AUTH_MODE' => 'local_password', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync'];
        $inputs = [];
        for ($i = 0; $i < 2; $i++) {
            $input = new InputStream;
            $worker = new Process([PHP_BINARY, 'tests/Support/local-account-worker.php'], base_path(), $env, $input, 90);
            $worker->start();
            $this->workers[] = $worker;
            $inputs[] = $input;
        }
        foreach ($this->workers as $worker) {
            $deadline = microtime(true) + 60;
            while (! str_contains($worker->getOutput(), 'READY') && $worker->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertStringContainsString('READY', $worker->getOutput(), $worker->getErrorOutput());
        }
        foreach ($inputs as $input) {
            $input->write(json_encode($data)."\n");
            $input->close();
        }
        $codes = [];
        foreach ($this->workers as $worker) {
            $worker->wait();
            $codes[] = $worker->getExitCode();
            $this->assertContains($worker->getExitCode(), [0, 2], $worker->getOutput().$worker->getErrorOutput());
        }
        sort($codes);
        $this->assertSame([0, 2], $codes);
    }

    public function test_duplicate_creation_commits_one_pending_account(): void
    {
        $this->race(['action' => 'create', 'actor' => $this->admin->id, 'client' => $this->admin->organisation_id]);
        $this->assertSame(1, User::where('email', 'race@example.invalid')->count());
        $user = User::where('email', 'race@example.invalid')->firstOrFail();
        $this->assertSame(0, DB::table('client_memberships')->where('user_id', $user->id)->count());
    }

    public function test_simultaneous_setup_consumes_once_and_grants_one_membership(): void
    {
        $service = app(LocalAccounts::class);
        $link = $service->create($this->admin, $this->admin->organisation_id, 'Race member', 'race@example.invalid', 'Fifteen plus characters!', 'Prepare setup race');
        $this->race(['action' => 'redeem', 'token' => $link['token']]);
        $user = User::where('email', 'race@example.invalid')->firstOrFail();
        $this->assertSame(1, DB::table('client_memberships')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('platform_audit_events')->where('target_id', $link['id'])->where('action', 'local.setup.consumed')->count());
    }

    public function test_simultaneous_invitation_acceptance_grants_once(): void
    {
        $service = app(LocalAccounts::class);
        $link = $service->create($this->admin, $this->admin->organisation_id, 'Race member', 'race@example.invalid', 'Fifteen plus characters!', 'Prepare setup race');
        $service->setPassword($link['token'], 'setup', 'Fifteen plus characters!', 'Fifteen plus characters!');
        $user = User::where('email', 'race@example.invalid')->firstOrFail();
        $client = app(PlatformAdministration::class)->client($this->admin, null, 'Concurrent destination', 0, 'Create destination client');
        $link = $service->create($this->admin, $client, $user->name, $user->email, 'Fifteen plus characters!', 'Prepare invitation race');
        $this->race(['action' => 'accept', 'user' => $user->id, 'token' => $link['token']]);
        $this->assertSame(1, DB::table('client_memberships')->where('user_id', $user->id)->where('organisation_id', $client)->count());
    }
}
