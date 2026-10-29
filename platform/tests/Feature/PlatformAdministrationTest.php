<?php

namespace Tests\Feature;

use App\Models\Enrolment;
use App\Models\Identity;
use App\Models\User;
use App\Services\Access;
use App\Services\Branding;
use App\Services\ClientTheme;
use App\Services\LearningNotices;
use App\Services\MockIdentity;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientContext;
use App\Services\Tenancy\PlatformAdministration;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PlatformAdministration $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Queue::fake();
        Storage::fake('local');
        $this->service = app(PlatformAdministration::class);
        $this->admin = User::where('email', 'admin@example.invalid')->firstOrFail();
        $this->service->appoint(null, $this->admin->id, 'Synthetic operator bootstrap', 'test-operator');
        $this->admin->refresh();
    }

    private function login(User $user): void
    {
        $this->post('/login', ['identity_id' => Identity::where('user_id', $user->id)->value('id')])->assertRedirect(app(ClientAccess::class)->globalAdmin($user) ? '/platform' : '/dashboard');
    }

    public function test_platform_without_client_and_no_sensitive_escalation(): void
    {
        $this->login($this->admin);
        foreach (['', '/clients', '/admins', '/appearance', '/audit'] as $part) {
            $this->get('/platform'.$part)->assertOk()->assertSee('Platform administration');
        }
        $id = $this->service->client($this->admin, null, 'New fictional client', 0, 'Create isolated organisation');
        $this->service->client($this->admin, $id, 'New fictional client', 1, 'Suspend unused organisation', true);
        $this->get('/platform')->assertOk();
        $this->assertTrue(app(ClientAccess::class)->globalAdmin($this->admin));
        $this->assertSame(0, app(Access::class)->enrolments($this->admin, $this->admin->organisation_id)->count());
        $member = User::where('email', 'member-a@example.invalid')->firstOrFail();
        $this->login($member);
        $this->get('/platform')->assertForbidden();
        $this->post('/platform/actions', ['action' => 'appoint', 'user' => $member->id, 'version' => $member->permission_version, 'reason' => 'Attempt forbidden escalation'])->assertForbidden();
    }

    public function test_bootstrap_rerun_last_admin_and_revoked_session(): void
    {
        $count = DB::table('platform_audit_events')->count();
        $this->service->appoint(null, $this->admin->id, 'Synthetic operator bootstrap', 'test-operator');
        $this->assertSame($count, DB::table('platform_audit_events')->count());
        $this->login($this->admin);
        $grant = $this->service->admins()->value('ra.id');
        $this->post('/platform/actions', ['action' => 'remove', 'grant' => $grant, 'version' => 0, 'reason' => 'Attempt last admin removal'])->assertStatus(409);
        $other = User::where('email', 'coordinator-a@example.invalid')->firstOrFail();
        $this->service->appoint($this->admin, $other->id, 'Appoint explicit second admin');
        $this->service->revoke($other->fresh(), $grant, 'Remove previous global authority');
        $this->get('/platform')->assertRedirect('/login');
        $this->assertFalse(app(ClientAccess::class)->globalAdmin($this->admin));
    }

    public function test_suspension_is_environment_scoped_preserves_history_and_does_not_revive_grants(): void
    {
        $client = $this->admin->organisation_id;
        $org = DB::table('organisations')->where('id', $client)->first();
        $history = DB::table('enrolments')->count();
        $this->service->client($this->admin, $client, $org->name, 0, 'Suspend synthetic client', true);
        $this->assertFalse(app(ClientAccess::class)->available($client));
        $this->assertFalse(app(ClientContext::class)->choices($this->admin)->contains('id', $client));
        $this->assertSame($history, DB::table('enrolments')->count());
        config(['training.environment' => 'production']);
        $this->assertTrue(app(ClientAccess::class)->available($client));
        config(['training.environment' => 'test']);
        $this->service->client($this->admin, $client, $org->name, 1, 'Reactivate synthetic client', false);
        $this->assertTrue(app(ClientAccess::class)->available($client));
        $this->assertSame(0, DB::table('client_sensitive_grants')->where('organisation_id', $client)->whereNull('ends_at')->count());
    }

    public function test_client_admin_requires_explicit_join_and_versions(): void
    {
        $client = $this->service->client($this->admin, null, 'Separate client', 0, 'Create role assignment client');
        $user = User::where('email', 'member-a@example.invalid')->firstOrFail();
        $this->login($this->admin);
        $data = ['action' => 'grant', 'client' => $client, 'user' => $user->id, 'version' => $user->permission_version, 'reason' => 'Assign existing client admin'];
        $this->postJson('/platform/actions', $data)->assertStatus(422)->assertJsonValidationErrors('client');
        $this->post('/platform/actions', array_replace($data, ['action' => 'join']))->assertRedirect();
        $this->post('/platform/actions', $data)->assertStatus(409);
        $this->post('/platform/actions', array_replace($data, ['version' => $user->fresh()->permission_version]))->assertRedirect();
        $this->assertTrue(app(ClientAccess::class)->clientRole($user, $client, 'admin'));
        $this->post('/platform/actions', array_replace($data, ['action' => 'revoke', 'version' => $user->fresh()->permission_version]))->assertRedirect();
        $this->assertFalse(app(ClientAccess::class)->clientRole($user, $client, 'admin'));
    }

    private function selection(string $html, string $form, string $field): string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $select = $xpath->query('//form[input[@name="_form" and @value="'.$form.'"]]/label/select[@name="'.$field.'"]')->item(0);
        $this->assertNotNull($select);
        $selected = $xpath->query('option[@selected]', $select)->item(0) ?? $xpath->query('option', $select)->item(0);

        return $selected->getAttribute('value');
    }

    public function test_account_defaults_use_membership_independent_of_client_pagination(): void
    {
        $user = User::where('email', 'member-a@example.invalid')->firstOrFail();
        DB::table('organisations')->where('id', $user->organisation_id)->update(['name' => 'Z Current client']);
        for ($i = 0; $i < 21; $i++) {
            $this->service->client($this->admin, null, 'A Other client '.$i, 0, 'Synthetic pagination client');
        }
        $this->login($this->admin);
        $response = $this->get('/platform/clients')->assertOk();
        $this->assertFalse($response->viewData('clients')->contains('id', $user->organisation_id));
        $this->assertSame($user->organisation_id, $this->selection($response->getContent(), 'platform-account-'.$user->id, 'client'));
        $this->assertSame('', $this->selection($response->getContent(), 'platform-account-'.$user->id, 'action'));
        $this->post('/platform/actions', ['action' => 'grant', 'client' => $user->organisation_id,
            'user' => $user->id, 'version' => $user->permission_version, 'reason' => 'Grant selected client admin'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(app(ClientAccess::class)->clientRole($user->fresh(), $user->organisation_id, 'admin'));
    }

    public function test_ambiguous_and_absent_memberships_require_a_choice(): void
    {
        $user = User::where('email', 'member-a@example.invalid')->firstOrFail();
        $other = $this->service->client($this->admin, null, 'Additional client', 0, 'Synthetic second membership');
        $this->service->clientRole($this->admin, $other, $user->id, 'join', $user->permission_version, 'Explicit second client join');
        $this->login($this->admin);
        $html = $this->get('/platform/clients')->assertOk()->getContent();
        $this->assertSame('', $this->selection($html, 'platform-account-'.$user->id, 'client'));
        DB::table('client_memberships')->where('user_id', $user->id)->update(['ended_at' => now()]);
        $html = $this->get('/platform/clients')->assertOk()->getContent();
        $this->assertSame('', $this->selection($html, 'platform-account-'.$user->id, 'client'));
    }

    public function test_membership_error_returns_to_form_without_leaking_selections(): void
    {
        $user = User::where('email', 'member-a@example.invalid')->firstOrFail();
        $neighbor = User::where('email', 'coordinator-a@example.invalid')->firstOrFail();
        $other = $this->service->client($this->admin, null, 'Wrong client', 0, 'Synthetic wrong client');
        $this->login($this->admin);
        $before = DB::table('role_assignments')->count();
        $this->post('/platform/actions', ['_form' => 'platform-account-'.$user->id, 'action' => 'grant',
            'client' => $other, 'user' => $user->id, 'version' => $user->permission_version,
            'reason' => 'Attempt wrong client grant'])->assertRedirect('/platform/clients')->assertSessionHasErrors('client');
        $response = $this->get('/platform/clients')->assertOk()->assertSee('not an active member');
        $this->assertSame($other, $this->selection($response->getContent(), 'platform-account-'.$user->id, 'client'));
        $this->assertSame('grant', $this->selection($response->getContent(), 'platform-account-'.$user->id, 'action'));
        $this->assertSame($neighbor->organisation_id, $this->selection($response->getContent(), 'platform-account-'.$neighbor->id, 'client'));
        $this->assertSame('', $this->selection($response->getContent(), 'platform-account-'.$neighbor->id, 'action'));
        $this->assertSame($before, DB::table('role_assignments')->count());
        $this->assertFalse(app(ClientAccess::class)->clientRole($user, $other, 'admin'));
    }

    public function test_platform_branding_logo_conflicts_and_public_defaults(): void
    {
        $this->login($this->admin);
        $data = ['version' => 0, 'display_name' => 'Fictional Platform', 'app_name' => 'Training Demo', 'accent' => '#ffffff', 'default_theme' => 'dark', 'reason' => 'Save synthetic platform branding'];
        $this->post('/platform/appearance', $data + ['logo' => UploadedFile::fake()->image('logo.png')])->assertSessionHasNoErrors()->assertRedirect();
        $path = DB::table('platform_settings')->value('logo_path');
        Storage::disk('local')->assertExists($path);
        $this->post('/platform/appearance', $data)->assertSessionHasErrors('version');
        $this->get('/platform-logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->post('/logout');
        $this->get('/login')->assertOk()->assertSee('Fictional Platform')->assertSee('data-theme="dark"', false);
        $this->assertSame('Training Demo', app(Branding::class)->forUser(null)['app_name']);
        $this->login($this->admin);
        $this->post('/platform/appearance', array_replace($data, ['version' => 1, 'remove_logo' => 1]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($path);
        $this->get('/platform-logo')->assertNotFound();
        $this->post('/platform/appearance', array_replace($data, ['version' => 2, 'logo' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')]))->assertSessionHasErrors('logo');
    }

    public function test_suspension_cancels_notice_revokes_tokens_and_denies_member_work(): void
    {
        $member = User::where('email', 'member-a@example.invalid')->firstOrFail();
        $enrolment = Enrolment::where('member_id', $member->id)->firstOrFail();
        $token = app(MockIdentity::class)->issue($member);
        $notice = app(LearningNotices::class)->record($enrolment, $member->id, 'suspend-test', 'review');
        $client = $enrolment->organisation_id;
        $name = DB::table('organisations')->where('id', $client)->value('name');
        $this->service->client($this->admin, $client, $name, 0, 'Suspend queued notification client', true);
        app(LearningNotices::class)->deliver($notice->id);
        $this->assertSame('cancelled', $notice->fresh()->status);
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Client-Id' => $client])->getJson('/api/v1/me/training')->assertUnauthorized();
        $this->assertFalse(app(Access::class)->canView($member, $enrolment));
        $this->service->client($this->admin, $client, $name, 1, 'Reactivate without queued replay', false);
        app(LearningNotices::class)->deliver($notice->id);
        $this->assertSame('cancelled', $notice->fresh()->status);
        $this->assertTrue(app(Access::class)->canView($member, $enrolment));
    }

    public function test_inactive_foreign_accounts_and_wrong_bootstrap_environment_fail_closed(): void
    {
        $this->artisan('training:global-admin', ['user' => $this->admin->id, '--environment' => 'production', '--operator' => 'fixture', '--reason' => 'Explicit bootstrap environment'])->assertExitCode(1);
        $this->login($this->admin);
        $user = User::factory()->create(['environment' => 'production', 'is_synthetic' => true, 'active' => true, 'organisation_id' => null]);
        Identity::create(['user_id' => $user->id, 'issuer' => config('training.mock_issuer'), 'subject' => 'foreign-bootstrap-test', 'active' => true]);
        $user->refresh();
        $this->post('/platform/actions', ['action' => 'appoint', 'user' => $user->id, 'version' => $user->permission_version, 'reason' => 'Forbidden foreign environment'])->assertStatus(409);
        $user->update(['environment' => 'test', 'active' => false]);
        $this->post('/platform/actions', ['action' => 'appoint', 'user' => $user->id, 'version' => $user->permission_version, 'reason' => 'Forbidden inactive identity'])->assertStatus(409);
        $this->assertFalse(app(ClientAccess::class)->globalAdmin($user));
    }

    public function test_platform_remains_available_with_all_clients_suspended_and_filters_audit(): void
    {
        foreach ($this->service->clients()->get() as $client) {
            $this->service->client($this->admin, $client->id, $client->name, 0, 'Suspend all synthetic clients', true);
        }
        $this->login($this->admin->fresh());
        $this->assertCount(0, app(ClientContext::class)->choices($this->admin));
        $this->get('/platform')->assertOk();
        $this->get('/platform/audit?action=platform.admin.granted')->assertOk()->assertSee('Synthetic operator bootstrap')->assertDontSee('Suspend all synthetic clients');
        DB::table('platform_audit_events')->update(['changes' => json_encode(['private_payload' => 'SHOULD_NEVER_RENDER'])]);
        $this->get('/platform/audit')->assertOk()->assertDontSee('SHOULD_NEVER_RENDER');
    }

    public function test_platform_defaults_preserve_client_settings_and_personal_choices(): void
    {
        $this->login($this->admin);
        $this->post('/platform/appearance', ['version' => 0, 'display_name' => 'Platform fallback', 'app_name' => 'Platform Academy', 'accent' => '#000000', 'default_theme' => 'dark', 'reason' => 'Platform default inheritance'])->assertSessionHasNoErrors();
        $client = $this->admin->organisation_id;
        $this->withSession(['client_id' => $client])->get('/dashboard')->assertOk();
        app(ClientContext::class)->id = $client;
        $this->assertSame('dark', app(Branding::class)->forUser($this->admin)['selected_theme']);
        DB::table('client_brandings')->insert(['organisation_id' => $client, 'environment' => 'test', 'version' => 1, 'mode' => 'white-label', 'client_name' => 'Explicit Client', 'app_name' => 'Client Academy', 'accent' => '#112233', 'default_theme' => 'light', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_theme_preferences')->insert(['organisation_id' => $client, 'environment' => 'test', 'user_id' => $this->admin->id, 'theme' => 'neon-cyan', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $branding = app(Branding::class)->forUser($this->admin);
        $this->assertSame('Client Academy', $branding['app_name']);
        $this->assertSame('neon-cyan', $branding['selected_theme']);
        $this->assertSame('light', $branding['default_theme']);
    }

    public function test_platform_palettes_save_render_and_reject_stale_updates(): void
    {
        $this->login($this->admin);
        $data = ['version' => 0, 'display_name' => 'Platform palette', 'app_name' => 'Platform Academy',
            'accent' => '#cc3300', 'default_theme' => 'platform-light', 'reason' => 'Verify platform palette'];
        $this->post('/platform/appearance', $data)->assertSessionHasNoErrors()->assertRedirect();
        $first = app(Branding::class)->forUser(null);
        $this->assertSame('platform-light', $first['selected_theme']);
        $this->assertSame(app(ClientTheme::class)->generate('#cc3300'), $first['platform_previews']);
        $this->get('/platform/appearance')->assertOk()->assertSee('data-theme="platform-light"', false)
            ->assertSee('--platform-light-primary:', false)->assertSee('Use platform default')->assertSee('Preview only until you save.');
        $this->post('/platform/appearance', array_replace($data, ['version' => 1, 'accent' => '#0066cc', 'default_theme' => 'platform-dark']))->assertSessionHasNoErrors();
        $second = app(Branding::class)->forUser(null);
        $this->assertNotSame($first['platform_previews'], $second['platform_previews']);
        $this->assertSame('platform-dark', $second['selected_theme']);
        $this->post('/platform/appearance', $data)->assertSessionHasErrors('version');
        $this->assertDatabaseHas('platform_settings', ['accent' => '#0066cc', 'default_theme' => 'platform-dark', 'version' => 2]);
        $this->assertSame(2, DB::table('platform_audit_events')->where('action', 'platform.appearance.saved')->count());
        $this->post('/platform/appearance', array_replace($data, ['version' => 2, 'default_theme' => 'client-light']))->assertSessionHasErrors('default_theme');
    }

    public function test_shared_pages_ignore_single_or_selected_client_branding_and_preferences(): void
    {
        $this->login($this->admin);
        $client = $this->admin->organisation_id;
        DB::table('organisations')->where('id', '!=', $client)->update(['active' => false]);
        DB::table('client_brandings')->insert(['organisation_id' => $client, 'environment' => 'test', 'version' => 1,
            'mode' => 'white-label', 'client_name' => 'Client only', 'app_name' => 'Client Academy', 'accent' => '#112233',
            'theme_colours' => json_encode(['generation' => 1, 'source' => '#112233']), 'default_theme' => 'client-dark', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_theme_preferences')->insert(['organisation_id' => $client, 'environment' => 'test', 'user_id' => $this->admin->id,
            'theme' => 'neon-cyan', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/platform/appearance', ['version' => 0, 'display_name' => 'Platform only', 'app_name' => 'Platform Academy',
            'accent' => '#cc3300', 'default_theme' => 'platform-light', 'reason' => 'Verify shared page isolation'])->assertSessionHasNoErrors();
        foreach (['/platform', '/platform/appearance', '/clients'] as $url) {
            $this->withSession(['client_id' => $client])->get($url)->assertOk()->assertSee('data-theme="platform-light"', false)
                ->assertSee('data-appearance-scope="platform"', false)->assertDontSee('Client Academy');
        }
        $platformKey = app(Branding::class)->forUser($this->admin)['theme_key'];
        $this->withSession(['client_id' => $client])->get('/dashboard')->assertOk()->assertSee('data-theme="neon-cyan"', false)->assertSee('Client Academy');
        $this->assertNotSame($platformKey, app(Branding::class)->forUser($this->admin)['theme_key']);
        $this->service->client($this->admin, null, 'Second client', 0, 'Verify multiple client context');
        $this->get('/platform')->assertOk()->assertSee('data-theme="platform-light"', false);
        DB::table('organisations')->update(['active' => false]);
        $this->get('/platform')->assertOk()->assertSee('data-theme="platform-light"', false);
        $this->post('/logout');
        $this->get('/login')->assertOk()->assertSee('data-theme="platform-light"', false);
    }

    public function test_unbranded_client_inherits_platform_and_can_keep_personal_theme(): void
    {
        $this->login($this->admin);
        $client = $this->admin->organisation_id;
        $this->post('/platform/appearance', ['version' => 0, 'display_name' => 'Platform only', 'app_name' => 'Platform Academy',
            'accent' => '#0066cc', 'default_theme' => 'platform-dark', 'reason' => 'Verify client inheritance'])->assertSessionHasNoErrors();
        $this->withSession(['client_id' => $client])->get('/dashboard')->assertOk()->assertSee('data-theme="platform-dark"', false);
        $this->postJson('/settings/appearance/preference', ['client_id' => $client, 'version' => 0, 'theme' => 'platform-light'])->assertOk()->assertJsonPath('theme', 'platform-light');
        $this->get('/dashboard')->assertOk()->assertSee('data-theme="platform-light"', false);
        $this->postJson('/settings/appearance/preference', ['client_id' => $client, 'version' => 1, 'theme' => null])->assertOk()->assertJsonPath('theme', 'platform-dark');
        $this->get('/dashboard')->assertOk()->assertSee('data-theme="platform-dark"', false);
    }

    public function test_failed_logo_write_preserves_previous_platform_settings(): void
    {
        $this->login($this->admin);
        $data = ['version' => 0, 'display_name' => 'Preserved platform', 'app_name' => 'Preserved academy', 'accent' => '#112233', 'default_theme' => 'light', 'reason' => 'Initial platform save before failure'];
        $this->post('/platform/appearance', $data)->assertSessionHasNoErrors();
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->post('/platform/appearance', array_replace($data, ['version' => 1, 'display_name' => 'Must not publish', 'logo' => UploadedFile::fake()->image('failure.png')]))->assertSessionHasErrors('logo');
        $this->assertDatabaseHas('platform_settings', ['environment' => 'test', 'version' => 1, 'display_name' => 'Preserved platform', 'logo_path' => null]);
        $this->assertSame(1, DB::table('platform_audit_events')->where('action', 'platform.appearance.saved')->count());
    }
}
