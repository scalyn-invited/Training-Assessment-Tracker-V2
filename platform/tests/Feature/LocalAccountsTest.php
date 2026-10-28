<?php

namespace Tests\Feature;

use App\Models\Identity;
use App\Models\User;
use App\Services\Administration;
use App\Services\LocalAccountDelivery;
use App\Services\LocalAccounts;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientAdministration;
use App\Services\Tenancy\PlatformAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LocalAccountsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Fifteen plus characters!';

    private User $admin;

    private LocalAccounts $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@example.invalid')->firstOrFail();
        app(PlatformAdministration::class)->appoint(null, $this->admin->id, 'Synthetic operator bootstrap', 'm2-tests');
        config(['training.authentication_mode' => 'local_password']);
        $this->accounts = app(LocalAccounts::class);
        $link = $this->accounts->bootstrap($this->admin->fresh(), 'm2-tests', 'Initial synthetic password');
        $this->accounts->setPassword($link['token'], 'setup', self::PASSWORD, self::PASSWORD);
        $this->admin->refresh();
    }

    private function create(string $email = 'new@example.invalid'): array
    {
        return $this->accounts->create($this->admin, $this->admin->organisation_id, 'Synthetic new member', $email, self::PASSWORD, 'Create synthetic account');
    }

    private function ready(string $email = 'new@example.invalid'): User
    {
        $link = $this->create($email);
        $this->accounts->setPassword($link['token'], 'setup', self::PASSWORD, self::PASSWORD);

        return User::where('email', $email)->firstOrFail();
    }

    private function login(User $user): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect();
    }

    private function denied(callable $action, int $code): void
    {
        try {
            $action();
            $this->fail('Expected denial '.$code);
        } catch (HttpException $e) {
            $this->assertSame($code, $e->getStatusCode());
        }
    }

    public function test_pending_account_setup_login_and_member_only_access(): void
    {
        $link = $this->create();
        $user = User::where('email', 'new@example.invalid')->firstOrFail();
        $this->assertNull($user->local_password_enabled_at);
        $this->assertDatabaseMissing('client_memberships', ['user_id' => $user->id]);
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->get('/password/setup/'.$link['token'])->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertNull(DB::table('local_account_links')->where('id', $link['id'])->value('consumed_at'));
        $this->post('/password/setup/'.$link['token'], ['password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertRedirect('/login');
        $user->refresh();
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertTrue(app(ClientAccess::class)->clientRole($user, $user->organisation_id, 'member'));
        $this->assertFalse(app(ClientAccess::class)->clientRole($user, $user->organisation_id, 'admin'));
        $this->login($user);
        $this->get('/dashboard')->assertOk();
        $this->get('/platform')->assertForbidden();
        $this->get('/password/setup/'.$link['token'])->assertStatus(410);
    }

    public function test_wrong_password_uninitialised_and_mock_selector_fail(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'missing@example.invalid', 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->post('/login', ['identity_id' => Identity::where('user_id', $this->admin->id)->value('id')])->assertSessionHasErrors(['email', 'password']);
        $this->get('/login')->assertOk()->assertDontSee('Choose a test identity')->assertSee('Sign in');
        $this->admin->update(['active' => false]);
        $this->post('/login', ['email' => $this->admin->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
    }

    public function test_account_and_ip_throttles(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'absent@example.invalid', 'password' => 'wrong'])->assertSessionHasErrors();
        }
        $this->post('/login', ['email' => 'absent@example.invalid', 'password' => 'wrong'])->assertStatus(429);
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => 'absent'.$i.'@example.invalid', 'password' => 'wrong'])->assertSessionHasErrors();
        }
        $this->post('/login', ['email' => 'different@example.invalid', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_setup_expiry_reissue_and_purpose_are_enforced(): void
    {
        $link = $this->create();
        $user = User::where('email', 'new@example.invalid')->firstOrFail();
        $replacement = $this->accounts->recover($this->admin, $user->id, $user->organisation_id, 'setup', self::PASSWORD, 'Reissue expired initial setup');
        $this->get('/password/setup/'.$link['token'])->assertStatus(410);
        $this->get('/password/reset/'.$replacement['token'])->assertStatus(410);
        $this->travel(1)->hours();
        $this->get('/password/setup/'.$replacement['token'])->assertStatus(410);
    }

    public function test_revoked_issuer_and_suspended_client_block_redemption(): void
    {
        $link = $this->create();
        $this->admin->increment('permission_version');
        $this->denied(fn () => $this->accounts->setPassword($link['token'], 'setup', self::PASSWORD, self::PASSWORD), 403);
        $this->assertDatabaseHas('platform_audit_events', ['action' => 'local.redemption.denied', 'target_id' => $link['id']]);
        $link = $this->create('next@example.invalid');
        DB::table('organisations')->where('id', $this->admin->organisation_id)->update(['active' => false]);
        $this->denied(fn () => $this->accounts->setPassword($link['token'], 'setup', self::PASSWORD, self::PASSWORD), 403);
        $this->assertNull(DB::table('local_account_links')->where('id', $link['id'])->value('consumed_at'));
    }

    public function test_existing_account_invitation_requires_correct_recipient_and_consent(): void
    {
        $user = $this->ready();
        $client = app(PlatformAdministration::class)->client($this->admin, null, 'Second synthetic client', 0, 'Create invitation target');
        $link = $this->accounts->create($this->admin, $client, 'Ignored existing name', $user->email, self::PASSWORD, 'Invite existing account');
        $this->assertSame('invite', $link['purpose']);
        $this->assertDatabaseMissing('client_memberships', ['user_id' => $user->id, 'organisation_id' => $client]);
        $this->denied(fn () => $this->accounts->accept($this->admin, $link['token']), 403);
        $this->login($user);
        $this->get('/invitations/'.$link['token'])->assertOk()->assertSee('Second synthetic client');
        $this->post('/invitations/'.$link['token'])->assertSessionHasErrors('accept');
        $this->post('/invitations/'.$link['token'], ['accept' => '1'])->assertRedirect('/login');
        $this->assertDatabaseHas('client_memberships', ['user_id' => $user->id, 'organisation_id' => $client]);
        $this->assertDatabaseHas('client_memberships', ['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $this->assertTrue(app(ClientAccess::class)->clientRole($user->fresh(), $client, 'member'));
        $this->denied(fn () => $this->accounts->accept($user->fresh(), $link['token']), 410);
        $this->assertSame(1, User::where('email', $user->email)->count());
    }

    public function test_invitation_expiry_and_clientless_preclient_boundary(): void
    {
        $user = $this->ready();
        $client = app(PlatformAdministration::class)->client($this->admin, null, 'Clientless target', 0, 'Create invitation target');
        $link = $this->accounts->create($this->admin, $client, $user->name, $user->email, self::PASSWORD, 'Invite clientless account');
        DB::table('client_memberships')->where('user_id', $user->id)->update(['status' => 'ended', 'ended_at' => now()]);
        $this->login($user);
        $this->get('/dashboard')->assertRedirect('/clients');
        $this->get('/invitations/'.$link['token'])->assertOk();
        $this->travel(7)->days();
        $this->denied(fn () => $this->accounts->accept($user, $link['token']), 410);
    }

    public function test_reset_and_change_revoke_all_sessions_and_outstanding_links(): void
    {
        $user = $this->ready();
        $link = $this->accounts->recover($this->admin, $user->id, null, 'reset', self::PASSWORD, 'Recover synthetic account');
        $this->login($user);
        $version = $user->permission_version;
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->accounts->setPassword($link['token'], 'reset', 'A different long password', 'A different long password');
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertGreaterThan($version, $user->fresh()->permission_version);
        $this->get('/dashboard')->assertRedirect('/login');
        $link = $this->accounts->recover($this->admin, $user->id, null, 'reset', self::PASSWORD, 'Issue another recovery');
        $this->accounts->changePassword($user->fresh(), 'A different long password', self::PASSWORD, self::PASSWORD);
        $this->get('/password/reset/'.$link['token'])->assertStatus(410);
    }

    public function test_client_admin_recovery_and_protected_targets(): void
    {
        $clientAdmin = $this->ready('client-admin@example.invalid');
        $client = $clientAdmin->organisation_id;
        $membership = app(ClientAccess::class)->membership($clientAdmin, $client);
        app(ClientAdministration::class)->grantRole($this->admin, $client, $membership->id, 'admin', null, 'Explicit client administrator');
        $clientAdmin->refresh();
        $user = $this->ready();
        $this->accounts->recover($clientAdmin, $user->id, $client, 'reset', self::PASSWORD, 'Client administrator recovery');
        $this->denied(fn () => $this->accounts->recover($clientAdmin, $this->admin->id, $client, 'reset', self::PASSWORD, 'Attempt global administrator recovery'), 403);
        $this->denied(fn () => $this->accounts->recover($clientAdmin, $clientAdmin->id, $client, 'reset', self::PASSWORD, 'Attempt own recovery'), 403);
        $this->denied(fn () => $this->accounts->create($user, $client, 'Denied', 'denied@example.invalid', self::PASSWORD, 'Attempt unauthorised creation'), 403);
        $this->denied(fn () => $this->accounts->recover($user, $clientAdmin->id, $client, 'reset', self::PASSWORD, 'Attempt unauthorised recovery'), 403);
        $this->assertDatabaseHas('platform_audit_events', ['action' => 'local.authorisation.denied']);
    }

    public function test_secrets_not_flashed_or_audited_and_admin_actions_render_once(): void
    {
        $this->login($this->admin);
        $response = $this->post('/accounts/actions', ['action' => 'create', 'client' => $this->admin->organisation_id,
            'name' => 'Created by UI', 'email' => 'ui@example.invalid', 'current_password' => self::PASSWORD, 'reason' => 'Create account through UI']);
        $response->assertOk()->assertSee('Private account link')->assertHeader('Referrer-Policy', 'no-referrer');
        $result = $response->viewData('result');
        $this->assertStringNotContainsString($result['token'], json_encode(DB::table('platform_audit_events')->get()));
        $this->assertStringNotContainsString($result['token'], json_encode(session()->all()));
        $this->post('/accounts/actions', ['action' => 'reset', 'current_password' => self::PASSWORD, 'reason' => 'x'])->assertSessionHasErrors();
        $this->assertArrayNotHasKey('current_password', session()->getOldInput());
        $this->get('/accounts/links')->assertOk();
        $this->accounts->cancel($this->admin, $result['id'], $this->admin->organisation_id, self::PASSWORD, 'Cancel unused setup link');
        $this->get('/password/setup/'.$result['token'])->assertStatus(410);
    }

    public function test_session_expiry_and_local_mode_disabled_outside_qualification(): void
    {
        $this->login($this->admin);
        $this->withSession(['last_seen' => time() - 1801])->get('/platform')->assertRedirect('/login');
        $this->login($this->admin);
        $this->withSession(['login_at' => time() - 28801])->get('/platform')->assertRedirect('/login');
        config(['training.environment' => 'production']);
        $this->denied(fn () => $this->accounts->bootstrap($this->admin, 'test', 'Wrong environment'), 503);
        $this->post('/login', ['email' => $this->admin->email, 'password' => self::PASSWORD])->assertStatus(503);
    }

    public function test_old_admin_paths_cannot_bypass_setup_or_invitation(): void
    {
        $this->denied(fn () => app(Administration::class)->person($this->admin, []), 409);
        $user = $this->ready();
        $client = app(PlatformAdministration::class)->client($this->admin, null, 'Consent required', 0, 'New invitation target');
        $this->denied(fn () => app(PlatformAdministration::class)->clientRole($this->admin, $client, $user->id, 'join', $user->permission_version, 'Attempt direct membership'), 409);
        $this->assertDatabaseMissing('client_memberships', ['user_id' => $user->id, 'organisation_id' => $client]);
    }

    public function test_password_bounds_confirmation_and_imported_freshness(): void
    {
        $link = $this->create();
        foreach (['short', str_repeat('a', 73)] as $password) {
            $this->post('/password/setup/'.$link['token'], ['password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('password');
        }
        $this->post('/password/setup/'.$link['token'], ['password' => self::PASSWORD, 'password_confirmation' => 'mismatch'])->assertSessionHasErrors('password');
        $this->accounts->setPassword($link['token'], 'setup', self::PASSWORD, self::PASSWORD);
        $user = User::where('email', 'new@example.invalid')->firstOrFail();
        $user->update(['origin' => 'primary_import', 'permissions_synced_at' => now()->subMinutes(6)]);
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
    }

    public function test_ordinary_email_classification_and_non_test_account_lifecycle(): void
    {
        $this->login($this->admin);
        $this->get('/platform')->assertSee('Test account')->assertDontSee('example.invalid accounts only');
        $response = $this->post('/accounts/actions', ['action' => 'create', 'client' => $this->admin->organisation_id,
            'name' => 'Local person', 'email' => 'person@example.com', 'is_synthetic' => '0',
            'current_password' => self::PASSWORD, 'reason' => 'Local development account']);
        $response->assertOk();
        $link = $response->viewData('result');
        $user = User::where('email', 'person@example.com')->firstOrFail();
        $this->assertFalse($user->is_synthetic);
        $this->assertNull($user->local_password_enabled_at);
        $this->accounts->setPassword($link['token'], 'setup', self::PASSWORD, self::PASSWORD);
        $this->assertSame($user->id, $this->accounts->authenticate($user->email, self::PASSWORD)->id);
        $this->get('/accounts/links')->assertOk()->assertSee('person@example.com');
        $reset = $this->accounts->recover($this->admin, $user->id, null, 'reset', self::PASSWORD, 'Reset local development password');
        DB::table('sessions')->insert(['id' => 'non-test-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->accounts->setPassword($reset['token'], 'reset', self::PASSWORD, self::PASSWORD);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->accounts->changePassword($user->fresh(), self::PASSWORD, 'Another long local password', 'Another long local password');
        $this->assertSame($user->id, $this->accounts->authenticate($user->email, 'Another long local password')->id);
        $client = app(PlatformAdministration::class)->client($this->admin, null, 'Local second client', 0, 'Create invitation destination');
        $invite = $this->accounts->create($this->admin, $client, 'Ignored', $user->email, self::PASSWORD, 'Invite local development person', true);
        $this->assertSame('invite', $invite['purpose']);
        $this->assertFalse($user->fresh()->is_synthetic);
        $this->accounts->accept($user->fresh(), $invite['token']);
        $this->assertTrue(app(ClientAccess::class)->clientRole($user->fresh(), $client, 'member'));
        $this->post('/logout')->assertRedirect();
        $this->post('/login', ['email' => $user->email, 'password' => 'Another long local password'])->assertRedirect();
        $this->get('/platform')->assertForbidden();
        $default = $this->create('test-person@example.org');
        $this->assertTrue(User::where('email', 'test-person@example.org')->firstOrFail()->is_synthetic);
        $this->assertSame('setup', $default['purpose']);
    }

    public function test_account_email_disabled_and_denied_do_not_consume_links(): void
    {
        $link = $this->create('mail-test@example.com');
        $delivery = app(LocalAccountDelivery::class);
        Mail::shouldReceive('mailer')->never();
        $this->assertStringContainsString('not sent', $delivery->send($this->admin, $link));
        config(['training.smtp_enabled' => true, 'training.account_mail_enabled' => true, 'mail.default' => 'smtp']);
        $this->assertStringContainsString('not sent', $delivery->send($this->admin, $link));
        $this->assertNull(DB::table('local_account_links')->where('id', $link['id'])->value('consumed_at'));
    }

    public function test_approved_synchronous_email_and_sanitised_transport_failure(): void
    {
        $link = $this->create('mail-test@example.com');
        config(['training.smtp_enabled' => true, 'training.account_mail_enabled' => true, 'mail.default' => 'smtp',
            'training.account_mail_recipients' => ['mail-test@example.com']]);
        $mailer = \Mockery::mock();
        Mail::shouldReceive('mailer')->with('smtp')->twice()->andReturn($mailer);
        $mailer->shouldReceive('raw')->once()->withArgs(fn ($body, $callback) => str_contains($body, $link['url']) && is_callable($callback))->andReturn(new \stdClass);
        $mailer->shouldReceive('raw')->once()->andThrow(new \RuntimeException('Sensitive transport failure '.$link['token']));
        $delivery = app(LocalAccountDelivery::class);
        $this->assertStringContainsString('accepted', $delivery->send($this->admin, $link));
        $this->assertStringContainsString('could not be confirmed', $delivery->send($this->admin, $link));
        $this->assertNull(DB::table('local_account_links')->where('id', $link['id'])->value('consumed_at'));
        $this->assertDatabaseCount('jobs', 0);
        $this->assertStringNotContainsString($link['token'], json_encode(DB::table('platform_audit_events')->get()));
        $this->accounts->setPassword($link['token'], 'setup', self::PASSWORD, self::PASSWORD);
        $this->denied(fn () => $delivery->send($this->admin, $link), 410);
    }

    public function test_credential_forms_have_safe_return_targets_without_referrers(): void
    {
        $this->login($this->admin);
        $this->get('/platform')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/clients')->assertHeader('Referrer-Policy', 'same-origin');
        $this->withSession(['_previous' => ['url' => url('/platform-logo')]])->post('/platform/actions', [
            'action' => 'create', 'name' => 'Return target client', 'version' => 0, 'reason' => 'Verify safe return location',
        ])->assertRedirect('/platform/clients');
        $this->withSession(['_previous' => ['url' => url('/platform-logo')]])->post('/accounts/actions', [
            'action' => 'create', 'client' => $this->admin->organisation_id, 'email' => 'invalid',
            'current_password' => self::PASSWORD, 'reason' => 'Verify invalid input return',
        ])->assertSessionHasErrors('email')->assertRedirect('/platform');
        $this->assertArrayNotHasKey('current_password', session()->getOldInput());
    }
}
