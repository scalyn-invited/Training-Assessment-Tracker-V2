<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\Registry;
use App\Services\LocalAccounts;
use App\Services\MockIdentity;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\PlatformAdministration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LocalProductionTest extends TestCase
{
    private string $database;

    private const PASSWORD = 'Disposable verification password!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = tempnam(sys_get_temp_dir(), 'tat-prod-');
        DB::purge('sqlite');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->database,
            'training.localhost_database' => $this->database, 'training.localhost_production' => true,
            'training.environment' => 'production', 'training.authentication_mode' => 'local_password']);
        $this->app->instance('env', 'production');
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
        if (isset($this->database) && is_file($this->database)) {
            unlink($this->database);
        }
    }

    private function postForm(string $url, array $data = [])
    {
        $token = bin2hex(random_bytes(20));

        return $this->withSession(['_token' => $token])->post($url, ['_token' => $token] + $data);
    }

    private function initialize(string $email = 'operator@example.org'): array
    {
        return app(LocalAccounts::class)->initializeProduction('Operator', $email, 'test-operator', 'Rehearse initial administrator setup');
    }

    private function deny(callable $action, int $code): void
    {
        try {
            $action();
            $this->fail('Expected denial');
        } catch (HttpException $e) {
            $this->assertSame($code, $e->getStatusCode());
        }
    }

    public function test_empty_bootstrap_reissue_expiry_activation_and_no_recovery_bypass(): void
    {
        $accounts = app(LocalAccounts::class);
        $first = $this->initialize();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('organisations', 0);
        $this->assertDatabaseCount('groups', 0);
        $admin = User::firstOrFail();
        $this->assertFalse($admin->is_synthetic);
        $this->assertNull($admin->local_password_enabled_at);
        $this->assertTrue(app(ClientAccess::class)->globalAdmin($admin));
        $this->deny(fn () => $this->initialize('other@example.org'), 409);
        $second = $this->initialize();
        $this->deny(fn () => $accounts->inspect($first['token'], 'setup'), 410);
        $this->travel(1)->hours();
        $this->deny(fn () => $accounts->inspect($second['token'], 'setup'), 410);
        $this->travelBack();
        $third = $this->initialize();
        $accounts->setPassword($third['token'], 'setup', self::PASSWORD, self::PASSWORD);
        $this->deny(fn () => $accounts->setPassword($third['token'], 'setup', self::PASSWORD, self::PASSWORD), 410);
        $this->deny(fn () => $this->initialize(), 409);
        $this->postForm('/login', ['email' => $admin->email, 'password' => self::PASSWORD])->assertRedirect('/platform');
        $this->get('/platform')->assertOk()->assertSee('Create first client')->assertDontSee('Test account')->assertDontSee('qualification')->assertDontSee('Platform ·');
        $this->assertDatabaseCount('role_assignments', 1);
        $this->assertStringNotContainsString($third['token'], json_encode(DB::table('platform_audit_events')->get()));
    }

    public function test_production_accounts_links_permissions_and_sessions(): void
    {
        $accounts = app(LocalAccounts::class);
        $initial = $this->initialize();
        $accounts->setPassword($initial['token'], 'setup', self::PASSWORD, self::PASSWORD);
        $admin = User::firstOrFail();
        $platform = app(PlatformAdministration::class);
        $client = $platform->client($admin, null, 'First client', 0, 'Create first production client');
        $setup = $accounts->create($admin, $client, 'Member', 'member@example.org', self::PASSWORD, 'Create ordinary account');
        $accounts->setPassword($setup['token'], 'setup', self::PASSWORD, self::PASSWORD);
        $member = User::where('email', 'member@example.org')->firstOrFail();
        $this->assertFalse($member->is_synthetic);
        $this->assertSame('production', $member->environment);
        $this->postForm('/login', ['email' => $admin->email, 'password' => self::PASSWORD])->assertRedirect();
        $this->get('/platform')->assertOk()->assertDontSee('Test account')->assertDontSee('Local development');
        $this->get('/accounts/links')->assertOk()->assertSee($member->email);
        $this->postForm('/accounts/actions', ['action' => 'create', 'client' => $client, 'name' => 'Rejected', 'email' => 'no@example.org',
            'is_synthetic' => true, 'current_password' => self::PASSWORD, 'reason' => 'Try forged classification'])->assertSessionHasErrors('is_synthetic');
        $this->deny(fn () => $accounts->create($admin, $client, 'Denied', 'no@example.org', self::PASSWORD, 'Reject synthetic account', true), 422);
        $second = $platform->client($admin, null, 'Second client', 0, 'Create another production client');
        $invite = $accounts->create($admin, $second, 'Ignored', $member->email, self::PASSWORD, 'Invite existing member');
        $this->deny(fn () => $accounts->accept($admin, $invite['token']), 403);
        $accounts->accept($member->fresh(), $invite['token']);
        $this->assertTrue(app(ClientAccess::class)->clientRole($member->fresh(), $second, 'member'));
        $this->postForm('/logout');
        $this->postForm('/login', ['email' => $member->email, 'password' => self::PASSWORD])->assertRedirect('/clients');
        $this->get('/platform')->assertForbidden();
        DB::table('sessions')->insert(['id' => 'production-other-session', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);
        $reset = $accounts->recover($admin, $member->id, null, 'reset', self::PASSWORD, 'Recover ordinary account');
        $accounts->setPassword($reset['token'], 'reset', self::PASSWORD, self::PASSWORD);
        $this->assertDatabaseMissing('sessions', ['user_id' => $member->id]);
        $this->get('/dashboard')->assertRedirect('/login');
        $accounts->changePassword($member->fresh(), self::PASSWORD, 'Another private password!', 'Another private password!');
        $this->assertSame($member->id, $accounts->authenticate($member->email, 'Another private password!')->id);
    }

    public function test_loopback_environment_mock_and_synthetic_guards(): void
    {
        $this->get('/login')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->get('/login', ['X-Forwarded-For' => '127.0.0.1'])->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('http://outside.example.org/login')->assertForbidden();
        $this->assertFalse(app(MockIdentity::class)->enabled());
        $this->deny(fn () => app(Registry::class)->profile('mock', 'generate_programme'), 422);
        $initial = $this->initialize();
        $accounts = app(LocalAccounts::class);
        $accounts->setPassword($initial['token'], 'setup', self::PASSWORD, self::PASSWORD);
        $admin = User::firstOrFail();
        $admin->update(['is_synthetic' => true]);
        $this->assertFalse(app(ClientAccess::class)->active($admin));
        $admin->update(['is_synthetic' => false]);
        $admin->update(['environment' => 'test']);
        $this->assertFalse(app(ClientAccess::class)->active($admin));
        config(['training.environment' => 'test']);
        $this->assertFalse($accounts->enabled());
        config(['training.environment' => 'production', 'training.localhost_production' => false]);
        $this->assertFalse($accounts->enabled());
    }

    public function test_concurrent_bootstrap_creates_one_admin_and_one_pending_link(): void
    {
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $input = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/production-bootstrap-worker.php'), $this->database], base_path(), null, $input, 60);
                $process->start();
                $workers[] = [$process, $input];
            }
            foreach ($workers as [$process, $input]) {
                $deadline = microtime(true) + 30;
                while (! str_contains($process->getOutput(), 'READY') && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
            }
            foreach ($workers as [$process, $input]) {
                $input->write("GO\n");
                $input->close();
            }
            foreach ($workers as [$process]) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
            }
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('role_assignments', 1);
            $this->assertSame(1, DB::table('local_account_links')->whereNull('consumed_at')->whereNull('revoked_at')->count());
            $this->assertSame(1, DB::table('platform_audit_events')->where('action', 'local.production.initialized')->count());
        } finally {
            foreach ($workers as [$process]) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }
}
