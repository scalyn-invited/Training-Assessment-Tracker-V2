<?php

namespace Tests\Feature;

use App\Services\Installation\InstallationDatabase;
use App\Services\Installation\InstallationState;
use App\Services\Installation\Installer;
use App\Services\LocalAccounts;
use App\Services\Tenancy\ClientAccess;
use Dotenv\Dotenv;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PDO;
use RuntimeException;
use Tests\TestCase;

class InstallationTest extends TestCase
{
    private string $sandbox;

    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = base_path();
        $this->sandbox = sys_get_temp_dir().'/training-installer-'.bin2hex(random_bytes(6));
        foreach (['storage/app', 'storage/framework/cache/data', 'bootstrap/cache', 'database/migrations'] as $directory) {
            mkdir($this->sandbox.'/'.$directory, 0700, true);
        }
        copy($this->source.'/.env.example', $this->sandbox.'/.env.example');
        (new Filesystem)->copyDirectory($this->source.'/database/migrations', $this->sandbox.'/database/migrations');
        $this->app->setBasePath($this->sandbox);
        $this->app->useAppPath($this->source.'/app');
        $this->app->useStoragePath($this->sandbox.'/storage');
        $this->app->useEnvironmentPath($this->sandbox);
        $this->app->instance('installation.process_environment', []);
        config(['app.key' => null, 'installation.status' => 'pending', 'installation.mode' => 'local',
            'installation.localhost_only' => false, 'database.default' => 'sqlite',
            'cache.stores.file.path' => $this->sandbox.'/storage/framework/cache/data']);
    }

    protected function tearDown(): void
    {
        DB::disconnect('installation');
        $this->app->setBasePath($this->source);
        (new Filesystem)->deleteDirectory($this->sandbox);
        parent::tearDown();
    }

    public function test_installer_state_does_not_reopen_for_existing_keys_cached_config_or_completed_receipts(): void
    {
        $state = app(InstallationState::class);
        $this->assertTrue($state->fresh());
        config(['app.key' => 'existing-key']);
        $this->assertFalse($state->fresh());
        config(['app.key' => null]);
        $pending = $state->initialize('local');
        $pending['status'] = 'installed';
        $state->write($pending);
        $this->assertFalse($state->fresh());
        unlink($state->directory().'/state.json');
        config(['installation.status' => 'installed']);
        $this->assertFalse($state->fresh());
    }

    public function test_legacy_database_file_blocks_automatic_setup(): void
    {
        file_put_contents($this->sandbox.'/database/database.sqlite', 'preserved legacy database');
        $this->assertFalse(app(InstallationState::class)->fresh());
    }

    public function test_lost_configuration_and_marker_do_not_reopen_existing_private_workspace(): void
    {
        mkdir(storage_path('app/installations/existing/private'), 0700, true);
        $this->assertFalse(app(InstallationState::class)->fresh());
    }

    public function test_tokens_are_hashed_expiring_and_cannot_be_issued_after_completion(): void
    {
        $store = app(InstallationState::class);
        $state = $store->initialize('hosted');
        $token = $store->issueToken();
        $receipt = $store->read();
        $this->assertSame(hash('sha256', $token), $receipt['token_hash']);
        $this->assertGreaterThan(time(), $receipt['token_expires']);
        $this->assertStringNotContainsString($token, file_get_contents($store->directory().'/state.json'));
        $receipt['status'] = 'installed';
        $store->write($receipt);
        $this->expectException(RuntimeException::class);
        $store->issueToken();
    }

    public function test_database_dsn_injection_is_rejected_before_connecting(): void
    {
        $this->expectException(RuntimeException::class);
        app(InstallationDatabase::class)->connect(['host' => 'localhost;dbname=other', 'database' => 'safe', 'port' => 3306, 'username' => 'user', 'db_password' => 'secret']);
    }

    public function test_local_installer_rejects_remote_requests_before_starting_a_session(): void
    {
        $this->app->instance('installation.testing', true);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])->get('/setup')->assertForbidden();
        $this->assertNull(app(InstallationState::class)->read());
    }

    public function test_completed_setup_endpoint_is_closed_without_database_access(): void
    {
        $this->app->instance('installation.testing', true);
        config(['installation.status' => 'installed']);
        $this->get('/setup')->assertNotFound();
        $this->post('/setup', ['action' => 'install'])->assertNotFound();
    }

    public function test_hosted_installer_requires_https_and_never_trusts_forwarded_proto_by_default(): void
    {
        $this->app->instance('installation.testing', true);
        app(InstallationState::class)->initialize('hosted');
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/setup')->assertForbidden();
    }

    public function test_hosted_token_form_requires_csrf_and_consumes_token_without_exposing_it(): void
    {
        $this->app->instance('installation.testing', true);
        $this->app->instance('env', 'production');
        $store = app(InstallationState::class);
        $store->initialize('hosted');
        $token = $store->issueToken();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20', 'HTTPS' => 'on', 'SERVER_PORT' => 443]);
        $page = $this->get('https://training.example.org/setup')->assertOk()->assertSee('Unlock installation');
        preg_match('/name="_token" value="([^"]+)"/', $page->getContent(), $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($page->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
        $this->post('https://training.example.org/setup', ['action' => 'token', 'setup_token' => $token])->assertStatus(419);
        $this->post('https://training.example.org/setup', ['_token' => $matches[1], 'action' => 'token', 'setup_token' => $token])
            ->assertOk()->assertSee('Before we begin')->assertDontSee($token);
        $this->assertArrayNotHasKey('token_hash', $store->read());
        $this->assertNotEmpty($store->read()['session_owner']);
    }

    public function test_state_lock_rejects_concurrent_writers(): void
    {
        $store = app(InstallationState::class);
        $store->initialize('local');
        $this->expectException(RuntimeException::class);
        $store->locked(fn () => $store->locked(fn () => null));
    }

    public function test_environment_roundtrip_preserves_special_database_passwords_and_unrelated_settings(): void
    {
        $state = app(InstallationState::class)->initialize('local');
        file_put_contents($this->sandbox.'/.env', "UNCHANGED=preserved\n");
        $method = new \ReflectionMethod(Installer::class, 'writeEnvironment');
        foreach (['false', 'null', 'quotes" and dollars${name} and slash\\ and apostrophe\''] as $password) {
            $method->invoke(app(Installer::class), $state, ['host' => '127.0.0.1', 'port' => 3306,
                'database' => 'fixture', 'username' => 'operator', 'db_password' => $password], 'mariadb', 'http://127.0.0.1:8000');
            $parsed = Dotenv::parse(file_get_contents($this->sandbox.'/.env'));
            $this->assertSame($password, base64_decode($parsed['TRAINING_DB_PASSWORD_B64'], true));
            $this->assertSame('preserved', $parsed['UNCHANGED']);
            $this->assertSame($state['key'], $parsed['APP_KEY']);
        }
    }

    public function test_external_live_service_override_cannot_silently_override_the_safe_profile(): void
    {
        $state = app(InstallationState::class)->initialize('local');
        $this->app->instance('installation.process_environment', ['AI_LIVE_ENABLED' => 'true']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI_LIVE_ENABLED');
        (new \ReflectionMethod(Installer::class, 'writeEnvironment'))->invoke(app(Installer::class), $state,
            ['host' => '127.0.0.1', 'port' => 3306, 'database' => 'fixture', 'username' => 'operator', 'db_password' => ''],
            'mariadb', 'http://127.0.0.1:8000');
    }

    public function test_legacy_upgrade_preserves_configuration_key_and_database(): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key, 'app.url' => 'http://127.0.0.1:8000']);
        file_put_contents($this->sandbox.'/.env', "APP_KEY=\"$key\"\nUNCHANGED=preserved\nAPP_ENV=\"production\"\n");
        file_put_contents($this->sandbox.'/database/database.sqlite', 'legacy database bytes');
        $this->artisan('training:prepare-local-upgrade')->assertSuccessful();
        $state = app(InstallationState::class);
        $this->assertSame($key, $state->read()['key']);
        $this->assertTrue($state->read()['upgrade']);
        $this->assertSame("APP_KEY=\"$key\"\nUNCHANGED=preserved\nAPP_ENV=\"production\"\n", file_get_contents($state->directory().'/environment-before-upgrade.env'));
        $this->assertStringContainsString('APP_ENV=local', file_get_contents($this->sandbox.'/.env'));
        $this->assertSame('legacy database bytes', file_get_contents($this->sandbox.'/database/database.sqlite'));
        $oldToken = $state->read()['token_hash'];
        $this->artisan('training:setup-token')->assertSuccessful();
        $this->assertNotSame($oldToken, $state->read()['token_hash']);
        $this->artisan('training:prepare-local-upgrade')->assertFailed();
    }

    public function test_mysql_family_installation_stages_preserve_credentials_and_seed_only_local_mode(): void
    {
        if (getenv('INSTALLER_TEST_DATABASES') !== '1') {
            $this->markTestSkipped('Set INSTALLER_TEST_DATABASES=1 to create isolated training_installer_test_* databases.');
        }
        $host = getenv('INSTALLER_TEST_HOST') ?: '127.0.0.1';
        $port = getenv('INSTALLER_TEST_PORT') ?: '3306';
        $username = getenv('INSTALLER_TEST_USER') ?: 'root';
        $password = getenv('INSTALLER_TEST_PASSWORD') ?: '';
        $server = new PDO("mysql:host=$host;port=$port", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database = 'training_installer_test_'.bin2hex(random_bytes(6));
        $server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            $mode = getenv('INSTALLER_TEST_MODE') ?: (str_contains($server->query('SELECT VERSION()')->fetchColumn(), 'MariaDB') ? 'local' : 'hosted');
            $store = app(InstallationState::class);
            $state = $store->initialize($mode);
            $input = ['host' => $host, 'port' => $port, 'database' => $database, 'username' => $username, 'db_password' => $password];
            $admin = ['name' => 'Installation operator', 'email' => 'operator@example.org',
                'fingerprint' => hash('sha256', 'isolated-test-credentials'),
                'password_hash' => Hash::make('Personal installation password!'), 'demo_hash' => Hash::make('Fictional demo password!')];
            $installer = app(Installer::class);
            $stages = ['migrated'];
            if ($mode === 'local') {
                for ($i = 1; $i < 25; $i++) {
                    $stages[] = 'demo '.$i.'/25';
                }
            }
            foreach ([...$stages, 'seeded', 'administrator', 'complete'] as $expected) {
                if ($expected === 'complete') {
                    mkdir($this->sandbox.'/.env');
                    try {
                        $installer->advance($input, $admin, $mode === 'local' ? 'http://127.0.0.1:8000' : 'https://training.example.org');
                        $this->fail('An unwritable environment target must not report installation success.');
                    } catch (RuntimeException $error) {
                        $this->assertStringContainsString('Cannot replace', $error->getMessage());
                        $this->assertSame('complete', DB::table('training_installations')->value('stage'));
                        $this->assertSame('installing', $store->read()['status']);
                    } finally {
                        rmdir($this->sandbox.'/.env');
                    }
                }
                $this->assertSame($expected, $installer->advance($input, $admin, $mode === 'local' ? 'http://127.0.0.1:8000' : 'https://training.example.org'));
                if ($expected === 'migrated') {
                    try {
                        $installer->advance($input, array_replace($admin, ['fingerprint' => 'different']), 'http://127.0.0.1:8000');
                        $this->fail('An interrupted installation must not accept replacement credentials.');
                    } catch (RuntimeException $error) {
                        $this->assertStringContainsString('same administrator', $error->getMessage());
                    }
                }
            }
            $this->assertSame($mode === 'local' ? 31 : 1, DB::table('users')->count());
            $this->assertSame($mode === 'local' ? 21 : 0, DB::table('enrolments')->count());
            $this->assertSame('installed', $store->read()['status']);
            $this->assertSame('complete', DB::table('training_installations')->value('stage'));
            $user = app(LocalAccounts::class)->authenticate($admin['email'], 'Personal installation password!');
            $this->assertTrue(app(ClientAccess::class)->globalAdmin($user));
            if ($mode === 'local') {
                $demo = app(LocalAccounts::class)->authenticate('demo.1.0@example.invalid', 'Fictional demo password!');
                $this->assertTrue($demo->is_synthetic);
            }
            $environment = file_get_contents($this->sandbox.'/.env');
            $this->assertStringNotContainsString('Personal installation password!', $environment);
            $this->assertStringNotContainsString('Fictional demo password!', $environment);
            $parsed = Dotenv::parse($environment);
            $this->assertSame($password, base64_decode($parsed['TRAINING_DB_PASSWORD_B64'], true));
            $this->assertSame($state['key'], $parsed['APP_KEY']);
            $this->expectException(RuntimeException::class);
            $installer->advance($input, $admin, 'http://127.0.0.1:8000');
        } finally {
            DB::disconnect('installation');
            $server->exec('DROP DATABASE `'.$database.'`');
        }
    }
}
