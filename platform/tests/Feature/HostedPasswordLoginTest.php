<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LocalAccounts;
use App\Services\MockIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HostedPasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Disposable hosted password!';

    private const URL = 'https://training.example.org';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => self::URL, 'app.debug' => false,
            'training.environment' => 'production', 'training.authentication_mode' => 'local_password',
            'training.localhost_production' => false, 'training.hosted_password_login' => true,
            'session.secure' => true, 'session.http_only' => true, 'session.same_site' => 'lax']);
        $this->app->instance('env', 'production');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);
    }

    private function admin(): User
    {
        $accounts = app(LocalAccounts::class);
        $initial = $accounts->initializeProduction('Hosted operator', 'operator@example.org', 'test-operator', 'Isolated hosted setup test');
        $accounts->setPassword($initial['token'], 'setup', self::PASSWORD, self::PASSWORD);

        return User::where('email', 'operator@example.org')->firstOrFail();
    }

    private function signIn(string $password = self::PASSWORD, string $email = 'operator@example.org')
    {
        $token = bin2hex(random_bytes(20));

        return $this->withSession(['_token' => $token])->post(self::URL.'/login', [
            '_token' => $token, 'email' => $email, 'password' => $password,
        ]);
    }

    public function test_hosted_form_login_and_permission_revocation(): void
    {
        $admin = $this->admin();
        $this->get(self::URL.'/login')->assertOk()->assertSee('autocomplete="current-password"', false)
            ->assertDontSee('Choose a test identity')->assertDontSee('SSO');
        $this->assertFalse(app(MockIdentity::class)->enabled());
        $response = $this->signIn()->assertRedirect('/platform');
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertAuthenticatedAs($admin);
        $this->get(self::URL.'/platform')->assertOk();
        $admin->increment('permission_version');
        $this->get(self::URL.'/platform')->assertRedirect('/login');
        $this->assertGuest();
        $this->signIn()->assertRedirect('/platform');
        config(['training.hosted_password_login' => false]);
        $this->get(self::URL.'/platform')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_opt_in_and_secure_configuration_are_required(): void
    {
        foreach (['training.hosted_password_login' => false, 'session.secure' => false,
            'session.http_only' => false, 'session.same_site' => 'none', 'app.debug' => true,
            'app.url' => 'http://training.example.org', 'training.environment' => 'test'] as $key => $value) {
            RateLimiter::clear('local-login:'.hash('sha256', 'operator@example.org'));
            $old = config($key);
            config([$key => $value]);
            $this->assertFalse(app(LocalAccounts::class)->enabled(), $key);
            $this->get(self::URL.'/login')->assertOk()->assertSee('Sign-in is unavailable')
                ->assertDontSee('Choose a test identity')->assertDontSee('name="password"', false);
            $this->signIn()->assertStatus(503);
            config([$key => $old]);
        }
    }

    public function test_http_and_forged_forwarded_proto_are_rejected(): void
    {
        $this->get('http://training.example.org/login')->assertForbidden();
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])->post('http://training.example.org/login', [
            'email' => 'operator@example.org', 'password' => self::PASSWORD,
        ])->assertForbidden();
        $this->assertGuest();
    }

    public function test_credentials_csrf_and_throttling_remain_enforced(): void
    {
        $this->admin();
        $this->post(self::URL.'/login', ['email' => 'operator@example.org', 'password' => self::PASSWORD])->assertStatus(419);
        for ($i = 0; $i < 5; $i++) {
            $this->signIn('Incorrect password')->assertSessionHasErrors(['email' => 'Sign-in failed. Check your details or contact your administrator.']);
        }
        $this->signIn()->assertStatus(429);
        $this->assertGuest();
    }

    public function test_ineligible_accounts_cannot_login(): void
    {
        $admin = $this->admin();
        foreach (['active' => false, 'is_synthetic' => true, 'environment' => 'test', 'local_password_enabled_at' => null] as $field => $value) {
            $original = $admin->$field;
            $admin->update([$field => $value]);
            $this->signIn()->assertSessionHasErrors('email');
            $this->assertGuest();
            $admin->update([$field => $original]);
        }
        DB::table('identities')->where('user_id', $admin->id)->update(['active' => false]);
        $this->signIn()->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_setup_is_single_use_and_cannot_replace_existing_admin(): void
    {
        $accounts = app(LocalAccounts::class);
        $first = $accounts->initializeProduction('Hosted operator', 'operator@example.org', 'test-operator', 'Isolated hosted setup test');
        $accounts->setPassword($first['token'], 'setup', self::PASSWORD, self::PASSWORD);
        foreach ([fn () => $accounts->setPassword($first['token'], 'setup', self::PASSWORD, self::PASSWORD),
            fn () => $accounts->initializeProduction('Another operator', 'other@example.org', 'test-operator', 'Attempt replacement bootstrap')] as $action) {
            try {
                $action();
                $this->fail('Expected setup denial');
            } catch (HttpException $exception) {
                $this->assertContains($exception->getStatusCode(), [409, 410]);
            }
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertStringNotContainsString($first['token'], json_encode(DB::table('platform_audit_events')->get()));
    }
}
