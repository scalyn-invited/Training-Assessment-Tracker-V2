<?php

namespace Tests\Feature;

use App\Jobs\DeliverOutbox;
use App\Livewire\JobReceipts;
use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Access;
use App\Services\MockIdentity;
use App\Services\Outbox;
use App\Services\SandboxDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['training.mock_identity' => true, 'training.environment' => 'test']);
        Storage::fake('local');
        Queue::fake();
        $this->seed();
    }

    private function user(string $subject = 'member-a'): User
    {
        return User::where('email', $subject.'@example.invalid')->firstOrFail();
    }

    private function enrolment(string $subject = 'member-a'): Enrolment
    {
        return Enrolment::where('member_id', $this->user($subject)->id)->firstOrFail();
    }

    private function login(string $subject = 'member-a'): User
    {
        $user = $this->user($subject);
        $this->post('/login', ['identity_id' => Identity::where('user_id', $user->id)->firstOrFail()->id])->assertRedirect('/dashboard');

        return $user;
    }

    private function httpFailure(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected HTTP exception '.$status);
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    private function event(array $changes = []): array
    {
        return array_replace(['event_id' => (string) Str::uuid(), 'entity_id' => 'sandbox-member-a',
            'entity_version' => 1, 'environment' => 'test', 'source' => 'primary-platform',
            'event_type' => 'person.upserted', 'payload' => ['display_name' => 'Updated synthetic learner', 'active' => true]], $changes);
    }

    public function test_guest_is_redirected_and_login_is_clearly_mocked(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('live SSO and MFA are not connected')->assertSee('LOCAL SANDBOX');
    }

    public function test_member_sees_own_records_without_peer_counts_or_files(): void
    {
        $this->login();
        $this->get('/dashboard')->assertOk()->assertSee('Clear, reproducible procedures')->assertDontSee('Customer communication fundamentals');
        $this->assertSame(1, app(Access::class)->enrolments($this->user())->count());
        $this->get('/enrolments/'.$this->enrolment('member-b')->id)->assertNotFound();
        $this->get('/files/'.$this->enrolment('member-b')->files->first()->id)->assertNotFound();
        $this->get('/people')->assertForbidden();
    }

    public function test_coordinator_requires_effective_assignment_and_named_responsibility(): void
    {
        $this->login('coordinator-a');
        $this->get('/enrolments/'.$this->enrolment()->id)->assertOk();
        $this->get('/enrolments/'.$this->enrolment('member-b')->id)->assertNotFound();
        DB::table('coordinator_assignments')->where('user_id', $this->user('coordinator-a')->id)->update(['ends_at' => now()]);
        $this->get('/enrolments/'.$this->enrolment()->id)->assertNotFound();
    }

    public function test_future_assignment_cannot_grant_access(): void
    {
        $this->login('coordinator-a');
        DB::table('coordinator_assignments')->update(['starts_at' => now()->addDay()]);
        $this->get('/enrolments/'.$this->enrolment()->id)->assertNotFound();
    }

    public function test_admin_needs_separate_sensitive_file_capability(): void
    {
        $this->login('admin');
        $this->get('/people')->assertOk();
        $file = $this->enrolment()->files->first();
        $file->update(['sensitive' => true]);
        $this->get('/files/'.$file->id)->assertNotFound();
        $this->user('admin')->update(['sensitive_access' => true]);
        auth()->forgetGuards();
        $this->get('/files/'.$file->id)->assertOk()->assertDownload('synthetic-evidence.txt');
    }

    public function test_authorised_download_is_private_and_audited(): void
    {
        $this->login();
        $file = $this->enrolment()->files->first();
        $this->get('/files/'.$file->id)->assertOk()->assertDownload('synthetic-evidence.txt')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('audit_events', ['action' => 'evidence.downloaded', 'target_id' => $file->id]);
        $this->get('/storage/'.$file->storage_key)->assertNotFound();
        $this->assertFalse(config('filesystems.disks.local.serve'));
    }

    public function test_cross_organisation_and_environment_records_are_hidden(): void
    {
        $this->login();
        $other = Organisation::create(['name' => 'Other organisation']);
        $e = $this->enrolment();
        $e->update(['organisation_id' => $other->id]);
        $this->get('/enrolments/'.$e->id)->assertNotFound();
        $e->update(['organisation_id' => $this->user()->organisation_id, 'environment' => 'production']);
        $this->get('/enrolments/'.$e->id)->assertNotFound();
    }

    public function test_real_local_only_member_can_train_locally_but_is_not_exported(): void
    {
        config(['training.environment' => 'production']);
        $member = $this->user();
        $coordinator = $this->user('coordinator-a');
        $e = $this->enrolment();
        // Construct a separate real-production fixture without using the application's immutable-history mutation path.
        DB::table('users')->where('id', $member->id)->update(['environment' => 'production', 'is_synthetic' => false, 'sync_policy' => 'local_only']);
        $member->refresh();
        $coordinator->update(['environment' => 'production', 'is_synthetic' => false]);
        $e->group->update(['environment' => 'production']);
        DB::table('memberships')->where('user_id', $member->id)->update(['environment' => 'production']);
        DB::table('coordinator_assignments')->where('user_id', $coordinator->id)->update(['environment' => 'production']);
        $e->update(['environment' => 'production', 'status' => 'active']);
        $this->assertTrue(app(Access::class)->canView($member->fresh(), $e));
        $this->assertCount(0, app(Access::class)->externalEnrolments($member->fresh())->get());
        DB::table('users')->where('id', $member->id)->update(['is_synthetic' => true]);
        $this->assertFalse(app(Access::class)->canView($member->fresh(), $e));
    }

    public function test_disabled_identity_and_account_revoke_session(): void
    {
        $this->login();
        Identity::where('user_id', $this->user()->id)->update(['active' => false]);
        $this->get('/dashboard')->assertRedirect('/login');
        Identity::where('user_id', $this->user()->id)->update(['active' => true]);
        $this->login();
        $this->user()->update(['active' => false]);
        auth()->forgetGuards();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_absolute_and_idle_session_limits_are_enforced(): void
    {
        $this->login();
        $this->withSession(['login_at' => time() - 28800])->get('/dashboard')->assertRedirect('/login');
        $this->login();
        $this->withSession(['last_seen' => time() - 1800])->get('/dashboard')->assertRedirect('/login');
    }

    public function test_mock_sign_in_and_tokens_are_disabled_outside_test_boundary(): void
    {
        config(['training.environment' => 'production']);
        $identity = Identity::first();
        $this->post('/login', ['identity_id' => $identity->id])->assertStatus(503);
        $this->getJson('/api/v1/me/training')->assertStatus(503);
        config(['training.environment' => 'test', 'training.mock_identity' => false]);
        $this->post('/login', ['identity_id' => $identity->id])->assertStatus(503);
    }

    public function test_valid_delegation_never_exports_synthetic_records(): void
    {
        $token = app(MockIdentity::class)->issue($this->user());
        $this->withToken($token)->getJson('/api/v1/me/training')->assertOk()->assertJsonPath('items', []);
        $this->withToken($token)->getJson('/api/v1/members/'.$this->user('member-b')->id.'/summary')->assertNotFound();
    }

    public function test_forged_expired_wrong_audience_and_machine_tokens_fail(): void
    {
        $mock = app(MockIdentity::class);
        $user = $this->user();
        foreach ([
            $mock->issue($user).'-forged',
            $mock->issue($user, ['exp' => time() - 1]),
            $mock->issue($user, ['aud' => 'wrong']),
            $mock->issue($user, ['kind' => 'machine']),
            $mock->issue($user, ['client_id' => 'untrusted']),
        ] as $token) {
            $this->withToken($token)->withHeader('X-User-Id', $user->id)->getJson('/api/v1/me/training')->assertUnauthorized();
        }
        $this->withToken($mock->issue($user, ['scope' => []]))->getJson('/api/v1/me/training')->assertForbidden();
    }

    public function test_stale_permissions_fail_closed_for_privileged_and_external_reads(): void
    {
        $coordinator = $this->user('coordinator-a');
        $coordinator->update(['origin' => 'primary_import', 'permissions_synced_at' => now()->subSeconds(301)]);
        $this->login('coordinator-a');
        $this->get('/dashboard')->assertStatus(503);
        $token = app(MockIdentity::class)->issue($coordinator->fresh());
        $this->withToken($token)->getJson('/api/v1/me/training')->assertStatus(503);
    }

    public function test_new_uploads_are_quarantined_and_unsafe_extensions_rejected(): void
    {
        $this->login();
        $e = $this->enrolment();
        $this->post('/enrolments/'.$e->id.'/files', ['evidence' => UploadedFile::fake()->createWithContent('sample.pdf', "%PDF-1.4\nsynthetic test")])->assertRedirect();
        $file = EvidenceFile::where('scan_status', 'quarantined')->firstOrFail();
        Storage::disk('local')->assertExists($file->storage_key);
        $this->get('/files/'.$file->id)->assertNotFound(); // Sensitive capability is separate from ownership.
        $this->user()->update(['sensitive_access' => true]);
        auth()->forgetGuards();
        $this->get('/files/'.$file->id)->assertStatus(409);
        $this->post('/enrolments/'.$e->id.'/files', ['evidence' => UploadedFile::fake()->createWithContent('attack.php', '<?php echo 1;')])->assertSessionHasErrors('evidence');
        $this->post('/enrolments/'.$e->id.'/files', ['evidence' => UploadedFile::fake()->create('huge.pdf', 20481, 'application/pdf')])->assertSessionHasErrors('evidence');
    }

    public function test_outbox_and_audit_commit_atomically_and_replay_deduplicates(): void
    {
        $actor = $this->user();
        $e = $this->enrolment();
        $event = app(Outbox::class)->requestCheck($actor, $e, 1, 'request-key-001');
        $same = app(Outbox::class)->requestCheck($actor, $e, 1, 'request-key-001');
        $this->assertSame($event->id, $same->id);
        $this->assertDatabaseCount('outbox_events', 1);
        $this->assertDatabaseHas('audit_events', ['target_id' => $event->id, 'action' => 'foundation.check.requested']);
        $job = new DeliverOutbox($event->id);
        $job->handle(app(Access::class));
        $job->handle(app(Access::class));
        $this->assertDatabaseCount('notification_receipts', 1);
        $this->assertSame('delivered', $event->fresh()->status);
        $this->assertDatabaseHas('notification_receipts', ['transport' => 'database-sink', 'environment' => 'test']);
    }

    public function test_transaction_rollback_leaves_no_business_event_or_receipt(): void
    {
        try {
            DB::transaction(function () {
                app(Outbox::class)->requestCheck($this->user(), $this->enrolment(), 1, 'rollback-key-001');
                throw new \RuntimeException('Simulated transaction crash');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('outbox_events', 0);
        $this->assertDatabaseCount('notification_receipts', 0);
        $this->assertDatabaseMissing('audit_events', ['action' => 'foundation.check.requested']);
    }

    public function test_reused_idempotency_key_or_stale_version_conflicts(): void
    {
        $actor = $this->user();
        $e = $this->enrolment();
        app(Outbox::class)->requestCheck($actor, $e, 1, 'request-key-001');
        $e->update(['version' => 2]);
        $this->httpFailure(409, fn () => app(Outbox::class)->requestCheck($actor, $e, 2, 'request-key-001'));
        $this->httpFailure(409, fn () => app(Outbox::class)->requestCheck($actor, $e, 1, 'request-key-002'));
    }

    public function test_queued_job_rechecks_revocation_and_version_before_delivery(): void
    {
        $event = app(Outbox::class)->requestCheck($this->user(), $this->enrolment(), 1, 'revocation-key');
        $this->user()->update(['active' => false]);
        (new DeliverOutbox($event->id))->handle(app(Access::class));
        $this->assertSame('cancelled', $event->fresh()->status);
        $this->assertDatabaseCount('notification_receipts', 0);
        $this->user()->update(['active' => true]);
        $event2 = app(Outbox::class)->requestCheck($this->user(), $this->enrolment(), 1, 'version-change');
        $this->enrolment()->update(['version' => 2]);
        (new DeliverOutbox($event2->id))->handle(app(Access::class));
        $this->assertSame('cancelled', $event2->fresh()->status);
    }

    public function test_exhausted_jobs_stop_automatic_recovery(): void
    {
        $event = app(Outbox::class)->requestCheck($this->user(), $this->enrolment(), 1, 'failed-key-001');
        (new DeliverOutbox($event->id))->failed(new \RuntimeException('simulated'));
        $this->assertSame('failed', $event->fresh()->status);
        Queue::fake();
        $this->artisan('training:outbox')->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_committed_dispatch_gap_is_recovered(): void
    {
        $event = app(Outbox::class)->requestCheck($this->user(), $this->enrolment(), 1, 'dispatch-gap');
        Queue::fake();
        $this->artisan('training:outbox')->assertSuccessful();
        Queue::assertPushed(DeliverOutbox::class, fn ($job) => $job->eventId === $event->id);
    }

    public function test_job_status_and_livewire_receipts_are_scoped_to_actor(): void
    {
        $event = app(Outbox::class)->requestCheck($this->user(), $this->enrolment(), 1, 'private-job-key');
        $this->login('member-b');
        $this->get('/jobs/'.$event->id)->assertNotFound();
        Livewire::actingAs($this->user('member-b'))->test(JobReceipts::class)->assertDontSee(substr($event->id, 0, 8));
    }

    public function test_duplicate_stale_and_conflicting_directory_events(): void
    {
        $adapter = app(SandboxDirectory::class);
        $org = $this->user()->organisation;
        $event = $this->event(['entity_version' => 2]);
        $this->assertSame('applied', $adapter->apply($org, $event));
        $this->assertSame('duplicate', $adapter->apply($org, $event));
        $this->assertSame('stale', $adapter->apply($org, $this->event(['entity_version' => 1])));
        $this->httpFailure(409, fn () => $adapter->apply($org, array_replace($event, ['entity_version' => 3])));
        $this->assertSame(2, $this->user()->directory_version);
        $this->assertDatabaseCount('inbound_events', 2);
    }

    public function test_directory_deactivation_invalidates_existing_delegation_and_session(): void
    {
        $user = $this->login();
        $token = app(MockIdentity::class)->issue($user);
        app(SandboxDirectory::class)->apply($user->organisation, $this->event(['event_type' => 'person.deactivated', 'payload' => ['reason' => 'Synthetic test']]));
        auth()->forgetGuards();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->withToken($token)->getJson('/api/v1/me/training')->assertUnauthorized();
    }

    public function test_membership_removal_revokes_member_and_coordinator_record_access(): void
    {
        $e = $this->enrolment();
        $member = $this->user();
        app(SandboxDirectory::class)->apply($member->organisation, $this->event([
            'entity_id' => 'membership-a', 'event_type' => 'membership.changed',
            'payload' => ['person_source_id' => $member->external_person_id, 'group_id' => $e->group_id, 'active' => false],
        ]));
        $this->assertFalse(app(Access::class)->canView($member->fresh(), $e));
        $this->assertFalse(app(Access::class)->canView($this->user('coordinator-a'), $e));
    }

    public function test_import_never_links_by_email_or_creates_identity_or_role(): void
    {
        $event = $this->event(['entity_id' => 'new-synthetic-person', 'payload' => ['display_name' => 'Imported fixture', 'active' => true, 'email' => $this->user('admin')->email, 'role' => 'admin']]);
        app(SandboxDirectory::class)->apply($this->user()->organisation, $event);
        $import = User::where('external_person_id', 'new-synthetic-person')->firstOrFail();
        $this->assertSame('member', $import->role);
        $this->assertNotSame($this->user('admin')->id, $import->id);
        $this->assertSame(0, Identity::where('user_id', $import->id)->count());
    }

    public function test_synthetic_status_cannot_change_after_enrolment(): void
    {
        $this->expectException(ValidationException::class);
        $this->user()->update(['is_synthetic' => false]);
    }

    public function test_directory_does_not_overwrite_real_or_local_only_profiles(): void
    {
        $this->httpFailure(422, fn () => app(SandboxDirectory::class)->apply($this->user()->organisation,
            $this->event(['entity_id' => 'sandbox-member-b'])));
        $this->assertSame('Jordan · Support learner', $this->user('member-b')->name);
        $this->assertDatabaseCount('inbound_events', 0);
    }
}
