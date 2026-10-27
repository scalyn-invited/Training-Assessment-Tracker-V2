<?php

namespace Tests\Integration;

use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use App\Services\LearningPlans;
use App\Services\Tenancy\ClientAdministration;
use App\Services\Tenancy\PlatformAdministration;
use App\Services\Tenancy\SensitiveAccess;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class ClientConcurrencyTest extends TestCase
{
    private User $admin;

    private string $client;

    private ClientAdministration $service;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__.'/../Support/client-qualification-guard.php';
        assertClientQualificationDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->service = app(ClientAdministration::class);
        $this->client = Organisation::create(['name' => 'Concurrent synthetic client', 'active' => true])->id;
        DB::table('platform_client_states')->insert(['organisation_id' => $this->client, 'environment' => 'test', 'version' => 0, 'suspended' => false]);
        $this->admin = $this->person();
        DB::table('role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->admin->id,
            'role_id' => DB::table('roles')->where('code', 'admin')->value('id'), 'environment' => 'test',
            'scope_type' => 'platform', 'scope_key' => 'platform', 'starts_at' => now()->subMinute(), 'active_slot' => 1,
            'granted_by' => $this->admin->id, 'reason' => 'Test bootstrap', 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as [$worker]) {
            if ($worker->isRunning()) {
                $worker->stop(1);
            }
        }
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function person(): User
    {
        $user = User::factory()->create(['organisation_id' => null, 'environment' => 'test', 'active' => true,
            'role' => 'member', 'origin' => 'manual', 'is_synthetic' => true]);
        Identity::create(['user_id' => $user->id, 'issuer' => 'https://concurrency.example.invalid',
            'subject' => (string) Str::uuid(), 'active' => true]);

        return $user;
    }

    private function member(string $role): object
    {
        $member = $this->service->join($this->admin, $this->client, $this->person()->id, 'Synthetic concurrency membership');
        $this->service->grantRole($this->admin, $this->client, $member->id, $role, null, 'Synthetic concurrency role grant');

        return $member;
    }

    private function start(array $arguments): array
    {
        $db = config('database.connections.mariadb');
        $env = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mariadb',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'], 'DB_URL' => false,
            'TRAINING_ENVIRONMENT' => 'test', 'MOCK_IDENTITY_ENABLED' => 'true', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync'];
        $input = new InputStream;
        $worker = new Process([PHP_BINARY, 'tests/Support/client-concurrency-worker.php', ...$arguments], base_path(), $env, $input, 20);
        $worker->start();
        $this->workers[] = [$worker, $input];

        return [$worker, $input];
    }

    private function ready(array $workers): void
    {
        $deadline = microtime(true) + 10;
        foreach ($workers as [$worker]) {
            while (! str_contains($worker->getOutput(), 'READY') && $worker->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertStringContainsString('READY', $worker->getOutput(), $worker->getErrorOutput());
        }
    }

    private function release(array $workers): void
    {
        foreach ($workers as [, $input]) {
            $input->write("GO\n");
            $input->close();
        }
    }

    private function results(array $workers): array
    {
        $codes = [];
        foreach ($workers as [$worker]) {
            $worker->wait();
            $this->assertContains($worker->getExitCode(), [0, 2, 3], $worker->getErrorOutput().$worker->getOutput());
            $codes[] = $worker->getExitCode();
        }
        sort($codes);

        return $codes;
    }

    public function test_competing_primary_allocations_commit_exactly_one_and_preserve_audit(): void
    {
        $member = $this->member('member');
        $one = $this->member('coordinator');
        $two = $this->member('coordinator');
        $workers = [$this->start(['primary', $this->admin->id, $this->client, $member->id, $one->id]),
            $this->start(['primary', $this->admin->id, $this->client, $member->id, $two->id])];
        $this->ready($workers);
        $this->release($workers);
        $this->assertSame([0, 2], $this->results($workers));
        $this->assertSame(1, DB::table('coordinator_allocations')->where('member_membership_id', $member->id)->where('primary_slot', 1)->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.coordinator.allocated')->count());
    }

    public function test_concurrent_global_admin_removals_leave_one_active_admin(): void
    {
        $service = app(PlatformAdministration::class);
        $other = $this->person();
        $service->appoint($this->admin, $other->id, 'Second synthetic platform administrator');
        $one = $service->admins()->where('ra.user_id', $this->admin->id)->value('ra.id');
        $two = $service->admins()->where('ra.user_id', $other->id)->value('ra.id');
        $workers = [$this->start(['platform-revoke', $this->admin->id, $one]), $this->start(['platform-revoke', $other->id, $two])];
        $this->ready($workers);
        $this->release($workers);
        $this->assertSame([0, 2], $this->results($workers));
        $this->assertSame(1, $service->admins()->count());
        $this->assertSame(1, DB::table('platform_audit_events')->where('action', 'platform.admin.revoked')->count());
    }

    public function test_concurrent_platform_grants_are_idempotent(): void
    {
        $other = $this->person();
        $workers = [$this->start(['platform-appoint', $this->admin->id, $other->id]), $this->start(['platform-appoint', $this->admin->id, $other->id])];
        $this->ready($workers);
        $this->release($workers);
        $this->assertSame([0, 0], $this->results($workers));
        $this->assertSame(1, DB::table('platform_audit_events')->where('target_id', $other->id)->count());
    }

    public function test_concurrent_client_edits_reject_stale_version(): void
    {
        $workers = [$this->start(['platform-client', $this->admin->id, $this->client]), $this->start(['platform-client', $this->admin->id, $this->client])];
        $this->ready($workers);
        $this->release($workers);
        $this->assertSame([0, 2], $this->results($workers));
        $this->assertSame(1, (int) DB::table('platform_client_states')->value('version'));
    }

    public function test_competing_identical_grants_commit_one_active_grant(): void
    {
        $member = $this->service->join($this->admin, $this->client, $this->person()->id, 'Synthetic ungranted membership');
        $workers = [$this->start(['grant', $this->admin->id, $this->client, $member->id]),
            $this->start(['grant', $this->admin->id, $this->client, $member->id])];
        $this->ready($workers);
        $this->release($workers);
        $this->assertSame([0, 2], $this->results($workers));
        $this->assertSame(1, DB::table('role_assignments')->where('client_membership_id', $member->id)->where('active_slot', 1)->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.role.granted')->count());
    }

    public function test_waiting_admin_mutation_rechecks_revocation_after_lock_acquisition(): void
    {
        $member = $this->member('admin');
        $grant = DB::table('role_assignments')->where('client_membership_id', $member->id)->first();
        $workers = [$this->start(['team', $member->user_id, $this->client])];
        $this->ready($workers);
        DB::beginTransaction();
        try {
            Organisation::whereKey($this->client)->lockForUpdate()->firstOrFail();
            $this->release($workers);
            usleep(250000);
            $this->assertTrue($workers[0][0]->isRunning(), 'Mutation must wait on the held organisation lock.');
            $this->service->revokeRole($this->admin, $this->client, $grant->id, 'Revoke while mutation is waiting');
            DB::commit();
        } catch (\Throwable $error) {
            DB::rollBack();
            throw $error;
        }
        $this->assertSame([3], $this->results($workers));
        $this->assertStringContainsString('DENIED:403', $workers[0][0]->getOutput());
        $this->assertSame(0, DB::table('groups')->where('name', 'Revocation race group')->count());
    }

    public function test_loaded_actor_cannot_keep_read_authority_after_revocation(): void
    {
        $member = $this->member('admin');
        $grant = DB::table('role_assignments')->where('client_membership_id', $member->id)->first();
        $workers = [$this->start(['read', $member->user_id, $this->client])];
        $this->ready($workers);
        $this->service->revokeRole($this->admin, $this->client, $grant->id, 'Revoke before deferred read');
        $this->release($workers);
        $this->assertSame([0], $this->results($workers));
        $this->assertStringContainsString('DENIED', $workers[0][0]->getOutput());
    }

    public function test_concurrent_sensitive_grants_create_one_grant_and_one_audit_event(): void
    {
        $localAdmin = $this->member('admin');
        $member = $this->member('member');
        $coordinator = $this->member('coordinator');
        $allocation = $this->service->allocate($this->admin, $this->client, $member->id, $coordinator->id, 'primary', 'Synthetic primary allocation');
        $version = User::findOrFail($coordinator->user_id)->permission_version;
        $workers = [$this->start(['sensitive', $localAdmin->user_id, $this->client, $allocation->id]),
            $this->start(['sensitive', $localAdmin->user_id, $this->client, $allocation->id])];
        $this->ready($workers);
        $this->release($workers);
        $this->assertSame([0, 2], $this->results($workers));
        $this->assertSame(1, DB::table('client_sensitive_grants')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.sensitive.granted')->count());
        $this->assertSame($version + 1, User::findOrFail($coordinator->user_id)->permission_version);
    }

    public function test_waiting_sensitive_grant_rechecks_ended_allocation_without_partial_write(): void
    {
        $localAdmin = $this->member('admin');
        $member = $this->member('member');
        $coordinator = $this->member('coordinator');
        $allocation = $this->service->allocate($this->admin, $this->client, $member->id, $coordinator->id, 'primary', 'Synthetic primary allocation');
        $workers = [$this->start(['sensitive', $localAdmin->user_id, $this->client, $allocation->id])];
        $this->ready($workers);
        DB::beginTransaction();
        try {
            Organisation::whereKey($this->client)->lockForUpdate()->firstOrFail();
            $this->release($workers);
            usleep(250000);
            $this->assertTrue($workers[0][0]->isRunning());
            $this->service->endAllocation($this->admin, $this->client, $allocation->id, 'End allocation before waiting grant.');
            DB::commit();
        } catch (\Throwable $error) {
            DB::rollBack();
            throw $error;
        }
        $this->assertSame([3], $this->results($workers));
        $this->assertSame(0, DB::table('client_sensitive_grants')->count());
    }

    public function test_waiting_programme_approval_rechecks_sensitive_revocation(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->seed();
        $fixture = new class
        {
            use LearningFixtures;

            public function pending(): array
            {
                $plan = $this->fill($this->prepare());
                app(LearningPlans::class)->review($this->person(), $this->enrolment(), $plan->version, false, '');

                return [$this->person(), $this->enrolment(), $plan];
            }
        };
        [$actor, $enrolment, $plan] = $fixture->pending();
        $client = $enrolment->organisation_id;
        $admin = User::where('email', 'admin@example.invalid')->firstOrFail();
        $grant = DB::table('client_sensitive_grants as sg')->join('coordinator_allocations as ca', 'ca.id', '=', 'sg.allocation_id')
            ->join('client_memberships as cm', 'cm.id', '=', 'ca.coordinator_membership_id')->where('cm.user_id', $actor->id)->value('sg.id');
        $workers = [$this->start(['approve', $actor->id, $client, $enrolment->id, (string) $plan->version])];
        $this->ready($workers);
        DB::beginTransaction();
        try {
            Organisation::whereKey($client)->lockForUpdate()->firstOrFail();
            $this->release($workers);
            usleep(250000);
            $this->assertTrue($workers[0][0]->isRunning());
            app(SensitiveAccess::class)->revoke($admin, $client, $grant, 'Revoke sensitive access before approval.');
            DB::commit();
        } catch (\Throwable $error) {
            DB::rollBack();
            throw $error;
        }
        $this->assertSame([3], $this->results($workers));
        $this->assertSame('needs_review', $plan->fresh()->state);
        $this->assertDatabaseMissing('programme_versions', ['enrolment_id' => $enrolment->id, 'state' => 'approved']);
    }

    public function test_competing_branding_saves_commit_one_version_and_audit(): void
    {
        $workers = [$this->start(['branding', $this->admin->id, $this->client]),
            $this->start(['branding', $this->admin->id, $this->client])];
        $this->ready($workers);
        $this->release($workers);
        $this->assertSame([0, 2], $this->results($workers));
        $this->assertSame(1, DB::table('client_brandings')->where('organisation_id', $this->client)->value('version'));
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.branding.saved')->count());
    }

    public function test_competing_theme_preferences_commit_one_version(): void
    {
        $workers = [$this->start(['preference', $this->admin->id, $this->client]), $this->start(['preference', $this->admin->id, $this->client])];
        $this->ready($workers);
        $this->release($workers);
        $this->assertSame([0, 2], $this->results($workers));
        $this->assertSame(1, DB::table('client_theme_preferences')->where('organisation_id', $this->client)->value('version'));
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.theme.preference.saved')->count());
    }

    public function test_waiting_approval_is_denied_after_client_suspension(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->seed();
        $fixture = new class
        {
            use LearningFixtures;

            public function pending(): array
            {
                $plan = $this->fill($this->prepare());
                app(LearningPlans::class)->review($this->person(), $this->enrolment(), $plan->version, false, '');

                return [$this->person(), $this->enrolment(), $plan];
            }
        };
        [$actor,$enrolment,$plan] = $fixture->pending();
        $client = $enrolment->organisation_id;
        $workers = [$this->start(['approve', $actor->id, $client, $enrolment->id, (string) $plan->version])];
        $this->ready($workers);
        DB::beginTransaction();
        try {
            app(PlatformAdministration::class)->lock();
            Organisation::whereKey($client)->lockForUpdate()->firstOrFail();
            $this->release($workers);
            usleep(250000);
            $this->assertTrue($workers[0][0]->isRunning());
            app(PlatformAdministration::class)->client($this->admin, $client, Organisation::findOrFail($client)->name, 0, 'Suspend before waiting approval', true);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $this->assertSame([3], $this->results($workers));
        $this->assertSame('needs_review', $plan->fresh()->state);
        $this->assertDatabaseMissing('programme_versions', ['enrolment_id' => $enrolment->id, 'state' => 'approved']);
    }

    public function test_waiting_global_admin_write_reads_current_grant_after_revocation(): void
    {
        $service = app(PlatformAdministration::class);
        $other = $this->person();
        $service->appoint($this->admin, $other->id, 'Appoint waiting mutation actor');
        $grant = $service->admins()->where('ra.user_id', $other->id)->value('ra.id');
        $workers = [$this->start(['branding', $other->id, $this->client])];
        $this->ready($workers);
        DB::beginTransaction();
        try {
            User::whereKey($other->id)->lockForUpdate()->firstOrFail();
            $this->release($workers);
            usleep(250000);
            $this->assertTrue($workers[0][0]->isRunning());
            $service->revoke($this->admin, $grant, 'Revoke while client write waits');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $this->assertSame([3], $this->results($workers));
        $this->assertDatabaseCount('client_brandings', 0);
    }
}
