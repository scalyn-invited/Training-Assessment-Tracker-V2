<?php

namespace Tests\Feature;

use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SavedBrandingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
        $this->admin = User::where('email', 'admin@example.invalid')->firstOrFail();
        $this->signIn($this->admin);
    }

    private function signIn(User $user): void
    {
        $this->post('/login', ['identity_id' => Identity::where('user_id', $user->id)->firstOrFail()->id])->assertRedirect('/dashboard');
    }

    private function fields(array $replace = []): array
    {
        return array_replace(['client_id' => $this->admin->organisation_id, 'version' => 0,
            'preview_mode' => 'white-label', 'preview_client' => 'Northstar',
            'preview_name' => 'Northstar Academy', 'preview_accent' => '#ffffff'], $replace);
    }

    public function test_save_is_persistent_audited_and_does_not_rename_directory_or_platform(): void
    {
        $name = DB::table('organisations')->where('id', $this->admin->organisation_id)->value('name');
        $this->post('/settings/appearance', $this->fields())->assertRedirect('/settings/appearance');
        $this->get('/dashboard')->assertOk()->assertSee('Northstar Academy');
        $this->get('/clients')->assertOk()->assertDontSee('Northstar Academy');
        $this->assertDatabaseHas('organisations', ['id' => $this->admin->organisation_id, 'name' => $name]);
        $this->assertDatabaseHas('audit_events', ['action' => 'client.branding.saved', 'actor_id' => $this->admin->id]);
        $this->signIn(User::where('email', 'member-a@example.invalid')->firstOrFail());
        $this->get('/dashboard')->assertOk()->assertSee('Northstar Academy');
        $this->post('/settings/appearance', $this->fields(['version' => 1]))->assertForbidden();
        $this->post('/logout');
        $this->get('/login')->assertOk()->assertDontSee('Northstar Academy');
    }

    public function test_stale_version_and_forged_client_cannot_overwrite(): void
    {
        $this->post('/settings/appearance', $this->fields())->assertRedirect();
        $this->post('/settings/appearance', $this->fields(['preview_client' => 'Lost update']))->assertSessionHasErrors('version');
        $this->post('/settings/appearance', $this->fields(['client_id' => 'foreign']))->assertSessionHasErrors('client_id');
        $this->assertDatabaseHas('client_brandings', ['client_name' => 'Northstar', 'version' => 1]);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.branding.saved')->count());
    }

    public function test_upload_reencodes_private_logo_and_replacement_and_removal_clean_up(): void
    {
        $this->post('/settings/appearance', $this->fields(['logo' => UploadedFile::fake()->image('logo.jpg')]))->assertSessionHasNoErrors();
        $old = DB::table('client_brandings')->value('logo_path');
        Storage::disk('local')->assertExists($old);
        $this->assertStringStartsWith("\x89PNG", Storage::disk('local')->get($old));
        $url = '/branding/'.$this->admin->organisation_id.'/logo';
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/branding/foreign/logo')->assertNotFound();
        $this->post('/settings/appearance', $this->fields(['version' => 1, 'logo' => UploadedFile::fake()->image('second.png')]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $new = DB::table('client_brandings')->value('logo_path');
        $this->post('/settings/appearance', $this->fields(['version' => 2, 'remove_logo' => 1]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($new);
        $this->get($url)->assertNotFound();
    }

    public function test_bad_uploads_and_failed_transaction_preserve_previous_state(): void
    {
        foreach ([UploadedFile::fake()->createWithContent('bad.png', '<script>bad</script>'),
            UploadedFile::fake()->createWithContent('bad.svg', '<svg/>'),
            UploadedFile::fake()->image('large.png')->size(2049),
            UploadedFile::fake()->image('wide.png', 4097, 1)] as $file) {
            $this->post('/settings/appearance', $this->fields(['logo' => $file]))->assertSessionHasErrors('logo');
        }
        $this->post('/settings/appearance', $this->fields())->assertSessionHasNoErrors();
        $this->post('/settings/appearance', $this->fields(['logo' => UploadedFile::fake()->image('stale.png')]))->assertSessionHasErrors('version');
        $this->assertSame([], Storage::disk('local')->allFiles('branding'));
        $this->assertDatabaseHas('client_brandings', ['version' => 1, 'logo_path' => null]);
    }

    public function test_revoked_admin_cannot_save_or_serve_logo(): void
    {
        $this->post('/settings/appearance', $this->fields(['logo' => UploadedFile::fake()->image('logo.png')]))->assertSessionHasNoErrors();
        User::whereKey($this->admin->id)->increment('permission_version');
        $this->post('/settings/appearance', $this->fields(['version' => 1]))->assertRedirect('/login');
        $this->get('/branding/'.$this->admin->organisation_id.'/logo')->assertRedirect('/login');
        $this->assertDatabaseHas('client_brandings', ['version' => 1]);
    }

    public function test_audit_failure_rolls_back_database_and_removes_new_logo(): void
    {
        $this->post('/settings/appearance', $this->fields())->assertSessionHasNoErrors();
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into') && str_contains($query->sql, 'audit_events')) {
                throw new \RuntimeException('Injected audit failure');
            }
        });
        $this->post('/settings/appearance', $this->fields(['version' => 1,
            'preview_client' => 'Must roll back', 'logo' => UploadedFile::fake()->image('failure.png')]))->assertSessionHasErrors('branding');
        $this->assertDatabaseHas('client_brandings', ['version' => 1, 'client_name' => 'Northstar', 'logo_path' => null]);
        $this->assertSame([], Storage::disk('local')->allFiles('branding'));
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.branding.saved')->count());
    }

    public function test_foreign_client_and_environment_branding_are_not_used(): void
    {
        $this->post('/settings/appearance', $this->fields())->assertSessionHasNoErrors();
        $record = (array) DB::table('client_brandings')->first();
        DB::table('client_brandings')->insert(array_replace($record, ['environment' => 'production', 'client_name' => 'Production secret']));
        $foreign = Organisation::create(['name' => 'Other client']);
        DB::table('client_brandings')->insert(array_replace($record, ['organisation_id' => $foreign->id, 'client_name' => 'Foreign secret']));
        $this->get('/dashboard')->assertOk()->assertDontSee('Production secret')->assertDontSee('Foreign secret');
        $this->post('/settings/appearance', $this->fields(['client_id' => $foreign->id]))->assertSessionHasErrors('client_id');
        $this->get('/branding/'.$foreign->id.'/logo')->assertNotFound();
    }

    public function test_storage_failure_does_not_publish_partial_branding(): void
    {
        $this->post('/settings/appearance', $this->fields())->assertSessionHasNoErrors();
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->post('/settings/appearance', $this->fields(['version' => 1,
            'preview_client' => 'Must not save', 'logo' => UploadedFile::fake()->image('failure.png')]))->assertSessionHasErrors('branding');
        $this->assertDatabaseHas('client_brandings', ['version' => 1, 'client_name' => 'Northstar', 'logo_path' => null]);
    }

    public function test_unprepared_database_reports_setup_problem_without_attempting_save(): void
    {
        Schema::shouldReceive('hasTable')->with('client_brandings')->andReturn(false);
        $this->post('/settings/appearance', $this->fields())->assertSessionHasErrors([
            'branding' => 'Branding settings are not ready in this workspace. Contact your administrator.',
        ]);
        $this->assertSame(0, DB::table('audit_events')->where('action', 'client.branding.saved')->count());
    }

    public function test_generated_palettes_store_only_source_and_version_and_are_client_scoped(): void
    {
        $this->post('/settings/appearance', $this->fields(['preview_accent' => '#FFEE00']))->assertSessionHasNoErrors();
        $this->assertSame(['generation' => 1, 'source' => '#ffee00'], json_decode(DB::table('client_brandings')->value('theme_colours'), true));
        $audit = json_decode(DB::table('audit_events')->where('action', 'client.branding.saved')->value('metadata'), true);
        $this->assertArrayHasKey('theme_colours', $audit['changes']);
        $this->get('/settings/appearance')->assertOk()->assertSee('Client Light')->assertSee('Client Dark')
            ->assertSee('--client-light-primary:', false)->assertSee('--client-dark-primary:', false)
            ->assertSee('data-default-theme="light"', false)->assertDontSee('name="theme_primary"', false);
        $this->signIn(User::where('email', 'member-a@example.invalid')->firstOrFail());
        $this->get('/settings/appearance')->assertOk()->assertSee('Client Light')->assertDontSee('Save client branding');
        $this->get('/clients')->assertOk()->assertDontSee('Client Light')->assertDontSee('--client-light-primary', false);
        $this->post('/logout');
        $this->get('/login')->assertOk()->assertDontSee('Client Light')->assertDontSee('--client-light-primary', false);
    }

    public function test_manual_palette_is_preserved_until_explicit_save_and_used_as_editor_source(): void
    {
        $this->post('/settings/appearance', $this->fields())->assertSessionHasNoErrors();
        $legacy = json_encode(['primary' => '#8b1e3f', 'background' => '#fff8f0', 'card' => '#ffffff', 'text' => '#222222']);
        DB::table('client_brandings')->update(['theme_colours' => $legacy]);
        $this->get('/settings/appearance')->assertOk()->assertSee('data-theme-choice="client"', false)
            ->assertSee('value="#8b1e3f"', false)->assertDontSee('data-theme-choice="client-light"', false);
        $this->assertSame($legacy, DB::table('client_brandings')->value('theme_colours'));
        $this->post('/settings/appearance', $this->fields(['version' => 1, 'theme_primary' => '#123456']))->assertSessionHasErrors('branding');
        $this->assertSame($legacy, DB::table('client_brandings')->value('theme_colours'));
        $this->post('/settings/appearance', $this->fields(['version' => 1, 'preview_accent' => '#8b1e3f']))->assertSessionHasNoErrors();
        $this->get('/settings/appearance')->assertOk()->assertSee('data-theme-choice="client-light"', false)
            ->assertSee('data-theme-choice="client-dark"', false)->assertDontSee('data-theme-choice="client"', false);
        $this->assertSame(['generation' => 1, 'source' => '#8b1e3f'], json_decode(DB::table('client_brandings')->value('theme_colours'), true));
    }

    public function test_invalid_sources_and_stale_or_foreign_submissions_preserve_palette(): void
    {
        $this->post('/settings/appearance', $this->fields(['preview_accent' => '#123456']))->assertSessionHasNoErrors();
        foreach (['white', '#fff', 'red; background:url(https://example.invalid)', '#12345z'] as $bad) {
            $this->post('/settings/appearance', $this->fields(['version' => 1, 'preview_accent' => $bad]))->assertSessionHasErrors('preview_accent');
        }
        $this->post('/settings/appearance', $this->fields())->assertSessionHasErrors('version');
        $this->post('/settings/appearance', $this->fields(['version' => 1, 'client_id' => 'foreign']))->assertSessionHasErrors('client_id');
        $this->assertDatabaseHas('client_brandings', ['version' => 1]);
        $this->assertSame(['generation' => 1, 'source' => '#123456'], json_decode(DB::table('client_brandings')->value('theme_colours'), true));
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.branding.saved')->count());
    }

    public function test_invalid_stored_generation_fails_closed_and_foreign_palette_does_not_leak(): void
    {
        $this->post('/settings/appearance', $this->fields())->assertSessionHasNoErrors();
        $row = (array) DB::table('client_brandings')->first();
        $foreign = Organisation::create(['name' => 'Other theme client']);
        DB::table('client_brandings')->insert(array_replace($row, ['organisation_id' => $foreign->id,
            'theme_colours' => json_encode(['generation' => 1, 'source' => '#abc123'])]));
        $this->get('/settings/appearance')->assertOk()->assertDontSee('value="#abc123"', false);
        foreach ([['generation' => 99, 'source' => '#123456'], ['generation' => 1, 'source' => 'invalid']] as $bad) {
            DB::table('client_brandings')->where('organisation_id', $this->admin->organisation_id)->update(['theme_colours' => json_encode($bad)]);
            $this->get('/dashboard')->assertOk()->assertDontSee('data-theme-choice="client-light"', false)->assertDontSee('--client-light-primary', false);
        }
    }
}
