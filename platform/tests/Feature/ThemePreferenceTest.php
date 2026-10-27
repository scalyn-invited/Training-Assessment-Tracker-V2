<?php

namespace Tests\Feature;

use App\Models\Identity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@example.invalid')->firstOrFail();
        $this->loginAs($this->admin);
    }

    private function loginAs(User $user): void
    {
        $this->post('/login', ['identity_id' => Identity::where('user_id', $user->id)->firstOrFail()->id])->assertRedirect('/dashboard');
    }

    private function branding(array $extra = []): array
    {
        return $extra + ['client_id' => $this->admin->organisation_id, 'version' => 0, 'preview_mode' => 'white-label', 'preview_client' => 'Client', 'preview_name' => 'Academy', 'preview_accent' => '#ffffff', 'default_theme' => 'client-dark'];
    }

    private function preference(?string $theme, int $version = 0, array $extra = []): array
    {
        return $extra + ['client_id' => $this->admin->organisation_id, 'version' => $version, 'theme' => $theme];
    }

    public function test_default_personal_override_reset_and_new_session(): void
    {
        $this->post('/settings/appearance', $this->branding())->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertSee('data-theme="client-dark"', false);
        $this->postJson('/settings/appearance/preference', $this->preference('neon-cyan'))->assertOk()->assertJsonPath('version', 1);
        $this->post('/settings/appearance', $this->branding(['version' => 1, 'default_theme' => 'light']))->assertSessionHasNoErrors();
        $this->post('/logout');
        $this->loginAs($this->admin);
        $this->get('/dashboard')->assertSee('data-theme="neon-cyan"', false);
        $this->postJson('/settings/appearance/preference', $this->preference(null, 1))->assertOk()->assertJsonPath('theme', 'light');
        $this->assertDatabaseHas('client_theme_preferences', ['user_id' => $this->admin->id, 'theme' => null, 'version' => 2]);
        $this->loginAs(User::where('email', 'member-a@example.invalid')->firstOrFail());
        $this->get('/dashboard')->assertSee('data-theme="light"', false);
        $this->post('/settings/appearance', $this->branding(['version' => 2]))->assertForbidden();
    }

    public function test_conflicts_invalid_foreign_and_revoked_requests_do_not_write(): void
    {
        $this->postJson('/settings/appearance/preference', $this->preference('dark'))->assertOk();
        $this->postJson('/settings/appearance/preference', $this->preference('light'))->assertStatus(409);
        $this->postJson('/settings/appearance/preference', $this->preference('invalid', 1))->assertStatus(422);
        $this->postJson('/settings/appearance/preference', $this->preference('light', 1, ['client_id' => 'foreign']))->assertStatus(422);
        $this->assertDatabaseCount('client_theme_preferences', 1);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.theme.preference.saved')->count());
        DB::table('users')->where('id', $this->admin->id)->update(['active' => false]);
        $this->postJson('/settings/appearance/preference', $this->preference('light', 1))->assertRedirect('/login');
        $this->assertDatabaseHas('client_theme_preferences', ['theme' => 'dark', 'version' => 1]);
    }

    public function test_unavailable_client_theme_rejected_and_legacy_alias_converted(): void
    {
        $this->postJson('/settings/appearance/preference', $this->preference('client-dark'))->assertStatus(422);
        $this->post('/settings/appearance', $this->branding())->assertSessionHasNoErrors();
        $this->postJson('/settings/appearance/preference', $this->preference('client'))->assertOk()->assertJsonPath('theme', 'client-light');
        $this->post('/logout');
        $this->get('/login')->assertDontSee('data-theme="client-light"', false);
    }
}
