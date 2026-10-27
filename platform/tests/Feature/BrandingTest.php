<?php

namespace Tests\Feature;

use App\Models\Enrolment;
use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Branding;
use App\Services\Tenancy\ClientContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
    }

    private function login(string $subject = 'member-a'): User
    {
        $user = User::where('email', $subject.'@example.invalid')->firstOrFail();
        $this->post('/login', ['identity_id' => Identity::where('user_id', $user->id)->firstOrFail()->id])->assertRedirect('/dashboard');

        return $user;
    }

    public function test_branding_is_selected_by_authenticated_organisation_and_environment_only(): void
    {
        $user = $this->login();
        config(['branding.organisations' => [
            $user->organisation_id => ['test' => ['mode' => 'co-branded', 'client_name' => 'Client Alpha'], 'production' => ['client_name' => 'Production Secret']],
            'other' => ['test' => ['client_name' => 'Other Client Secret']],
        ]]);
        $this->get('/dashboard?organisation_id=other&environment=production')->assertOk()->assertSee('Client Alpha')->assertDontSee('Other Client Secret')->assertDontSee('Production Secret');
        $other = clone $user;
        $other->organisation_id = 'other';
        $this->assertSame('Client Alpha', app(Branding::class)->forUser($other)['client_name']);
        $this->assertSame(app(Branding::class)->forUser($user)['theme_key'], app(Branding::class)->forUser($other)['theme_key']);
        $firstKey = app(Branding::class)->forUser($user)['theme_key'];
        $secondClient = Organisation::create(['name' => 'Second branding client']);
        $this->explicitMember($user, $secondClient->id);
        config(['branding.organisations.'.$secondClient->id.'.test.client_name' => 'Selected Client']);
        app(ClientContext::class)->select($user, $secondClient->id);
        $this->assertSame('Selected Client', app(Branding::class)->forUser($user)['client_name']);
        $this->assertNotSame($firstKey, app(Branding::class)->forUser($user)['theme_key']);
        $other->active = false;
        $this->assertSame('Scalyn', app(Branding::class)->forUser($other)['client_name']);
        $this->assertSame('Scalyn', app(Branding::class)->forUser(null)['client_name']);
    }

    public function test_white_label_removes_cerebru_identity_and_uses_valid_assets(): void
    {
        $user = $this->login();
        config(['branding.organisations' => [$user->organisation_id => ['test' => [
            'mode' => 'white-label', 'client_name' => 'Northstar', 'app_name' => 'Northstar Academy',
            'logo_light' => '/images/training-mark.svg', 'logo_dark' => null, 'favicon' => '/images/training-mark.svg',
        ]]]]);
        $this->assertSame('/images/training-mark.svg', app(Branding::class)->forUser($user)['logo_dark']);
        $this->get('/dashboard')->assertOk()->assertSee('Northstar Academy')->assertSee('/images/training-mark.svg')->assertDontSee('Cerebru')->assertDontSee('opsbrain-eosin');
        $this->get('/settings/appearance')->assertOk()->assertDontSee('Branding preview');
    }

    public function test_unsafe_colour_asset_and_parent_urls_fall_back_and_text_is_escaped(): void
    {
        $this->login();
        config(['branding.defaults.client_name' => '<script>alert(1)</script>', 'branding.defaults.mode' => 'co-branded',
            'branding.defaults.accent_dark' => 'red; background:url(https://example.invalid)',
            'branding.defaults.logo_dark' => 'https://example.invalid/tracker.svg',
            'branding.defaults.logo_light' => '/images/../secret.svg',
            'branding.defaults.favicon' => 'javascript:alert(1)',
            'branding.defaults.cerebru_url' => 'javascript:alert(1)',
            'branding.defaults.default_theme' => 'unknown']);
        $brand = app(Branding::class)->forUser(auth()->user());
        $this->assertSame('#67e8f9', $brand['accent_dark']);
        $this->assertNull($brand['logo_light']);
        $this->assertNull($brand['cerebru_url']);
        $this->assertSame('/images/training-mark.svg', $brand['favicon']);
        $this->get('/dashboard')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('data-theme="dark"', false);
    }

    public function test_paginated_dashboard_keeps_scope_and_renders_theme_compatible_controls(): void
    {
        $user = $this->login();
        $plan = Enrolment::where('member_id', $user->id)->firstOrFail();
        for ($i = 0; $i < 13; $i++) {
            $copy = $plan->replicate();
            $copy->title = 'Additional plan '.$i;
            $copy->save();
        }
        $this->get('/dashboard')->assertOk()->assertSee('aria-label="Pagination"', false)->assertSee('14 plans')->assertDontSee('Customer communication fundamentals');
        $this->get('/dashboard?page=2')->assertOk()->assertSee('Previous')->assertDontSee('Customer communication fundamentals');
    }

    public function test_appearance_is_authenticated_and_preview_is_admin_only(): void
    {
        $this->get('/settings/appearance')->assertRedirect('/login');
        $this->login();
        $this->get('/settings/appearance')->assertOk()->assertSee('Neon Emerald')->assertDontSee('brand-preview-form');
        $this->post('/logout');
        $this->login('admin');
        $this->get('/settings/appearance')->assertOk()->assertSee('brand-preview-form');
    }
}
