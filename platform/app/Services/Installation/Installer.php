<?php

namespace App\Services\Installation;

use App\Models\Identity;
use App\Models\User;
use App\Services\LocalAccounts;
use App\Services\Tenancy\PlatformAdministration;
use Database\Seeders\LocalDevelopmentSeeder;
use Faker\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Installer
{
    public function requirements(): array
    {
        $checks = ['PHP 8.2 or newer' => PHP_VERSION_ID >= 80200];
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'dom', 'curl'] as $extension) {
            $checks['PHP '.$extension] = extension_loaded($extension);
        }
        if ((app(InstallationState::class)->read()['mode'] ?? config('installation.mode')) === 'local') {
            $checks['Local demo dependencies (composer install)'] = class_exists(Factory::class);
        }
        $checks['Writable application directory'] = is_writable(base_path());
        $checks['Writable storage'] = is_writable(storage_path());
        $checks['Writable bootstrap cache'] = is_writable(base_path('bootstrap/cache'));
        $checks['No cached configuration'] = ! app()->configurationIsCached();

        return $checks;
    }

    public function advance(array $database, array $administrator, string $url): string
    {
        $store = app(InstallationState::class);

        return $store->locked(function () use ($store, $database, $administrator, $url) {
            $state = $store->read();
            if (! $state || $state['status'] === 'installed') {
                throw new RuntimeException('Installation is no longer available.');
            }
            if (in_array(false, $this->requirements(), true)) {
                throw new RuntimeException('Installation requirements are no longer satisfied. Check extensions, directory permissions and cached configuration.');
            }
            $fingerprint = hash('sha256', json_encode([$database['host'], $database['port'], $database['database']], JSON_THROW_ON_ERROR));
            if (isset($state['database']) && ! hash_equals($state['database'], $fingerprint)) {
                throw new RuntimeException('An installation has already started against another database. Restore the configuration backup before operator recovery.');
            }
            $accountFingerprint = $administrator['fingerprint'];
            if (isset($state['administrator']) && ! hash_equals($state['administrator'], $accountFingerprint)) {
                throw new RuntimeException('Resume with the same administrator details and passwords used when installation started. Existing account passwords will not be replaced.');
            }
            $connection = app(InstallationDatabase::class);
            $details = $connection->inspect($database, $state);
            $this->assertOverrides($this->environmentValues($state, $database, $details['engine'], $url));
            $state['database'] = $fingerprint;
            $state['administrator'] = $accountFingerprint;
            $state['status'] = 'installing';
            $store->write($state);
            $connection->claim($database, $state);
            $connection->activate($database, $details['engine']);
            $local = $state['mode'] === 'local';
            app()->instance('env', $local ? 'local' : 'production');
            config(['training.environment' => $local ? 'test' : 'production', 'training.authentication_mode' => 'local_password',
                'training.mock_identity' => false, 'training.localhost_production' => false,
                'training.hosted_password_login' => ! $local, 'app.debug' => false, 'app.url' => $url,
                'session.secure' => ! $local, 'session.http_only' => true, 'session.same_site' => 'strict',
                'ai.live_enabled' => false, 'training.smtp_enabled' => false, 'training.account_mail_enabled' => false,
                'mail.default' => 'array', 'queue.default' => 'database',
                'filesystems.disks.local.root' => storage_path('app/installations/'.$state['id'].'/private')]);
            app('filesystem')->forgetDisk('local');
            $stage = DB::table('training_installations')->where('installation_id', $state['id'])->value('stage');
            if ($stage === 'claimed') {
                if (Artisan::call('migrate', ['--database' => 'installation', '--force' => true, '--no-interaction' => true]) !== 0) {
                    throw new RuntimeException('Migrations stopped. Ask the operator to check database privileges and migration compatibility, then retry.');
                }
                $this->stage($state, 'migrated');

                return 'migrated';
            }
            if ($stage === 'migrated') {
                return DB::transaction(function () use ($state, $administrator, $local) {
                    if ($local) {
                        $step = app(LocalDevelopmentSeeder::class)->seedStep($state['id'], $administrator['demo_hash']);
                        if ($step < LocalDevelopmentSeeder::STEPS) {
                            return 'demo '.$step.'/'.LocalDevelopmentSeeder::STEPS;
                        }
                    }
                    $this->stage($state, 'seeded');

                    return 'seeded';
                });
            }
            if ($stage === 'seeded') {
                DB::transaction(function () use ($state, $administrator, $local) {
                    if (User::where('email', $administrator['email'])->exists()) {
                        throw new RuntimeException('That administrator email belongs to a demo account. Use your own email.');
                    }
                    $user = User::create(['name' => $administrator['name'], 'email' => $administrator['email'],
                        'password' => $administrator['password_hash'], 'role' => 'admin', 'organisation_id' => null,
                        'environment' => $local ? 'test' : 'production', 'is_synthetic' => false, 'active' => true,
                        'origin' => 'manual', 'sync_policy' => 'local_only', 'sensitive_access' => false,
                        'local_password_enabled_at' => now()]);
                    Identity::create(['user_id' => $user->id, 'issuer' => LocalAccounts::ISSUER, 'subject' => $user->id, 'active' => true]);
                    app(PlatformAdministration::class)->appoint(null, $user->id, 'First-install administrator setup', $administrator['name']);
                    $this->stage($state, 'administrator');
                });

                return 'administrator';
            }
            if (! in_array($stage, ['administrator', 'complete'], true)) {
                throw new RuntimeException('Unexpected installation stage. Ask the operator to inspect the installation receipt.');
            }
            // Persist database completion first: artisan serve may restart as soon as .env changes.
            // An owned receipt can resume only configuration saving after this point.
            $this->stage($state, 'complete');
            $this->writeEnvironment($state, $database, $details['engine'], $url);
            $state['status'] = 'installed';
            $state['completed_at'] = gmdate('c');
            unset($state['token_hash'], $state['token_expires'], $state['session_owner']);
            $store->write($state);

            return 'complete';
        });
    }

    private function stage(array $state, string $stage): void
    {
        DB::table('training_installations')->where('installation_id', $state['id'])->update([
            'stage' => $stage, 'fixture_version' => $state['mode'] === 'local' ? LocalDevelopmentSeeder::VERSION : null, 'updated_at' => now(),
        ]);
    }

    private function writeEnvironment(array $state, array $database, string $engine, string $url): void
    {
        $store = app(InstallationState::class);
        $path = base_path('.env');
        $source = is_file($path) ? file_get_contents($path) : file_get_contents(base_path('.env.example'));
        $backup = $store->directory().'/environment-before-install.env';
        if (is_file($path) && ! is_file($backup)) {
            $store->atomic($backup, $source);
        }
        $values = $this->environmentValues($state, $database, $engine, $url);
        $this->assertOverrides($values);
        foreach ($values as $key => $value) {
            if (preg_match('/[\r\n\x00]/', (string) $value)) {
                throw new RuntimeException('Configuration values cannot contain control characters.');
            }
            $encoded = in_array($value, ['true', 'false', 'null'], true) ? $value
                : '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], (string) $value).'"';
            $line = $key.'='.$encoded;
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $source = preg_match($pattern, $source) ? preg_replace_callback($pattern, fn () => $line, $source) : rtrim($source)."\n".$line."\n";
        }
        $store->atomic($path, $source);
    }

    private function environmentValues(array $state, array $database, string $engine, string $url): array
    {
        $local = $state['mode'] === 'local';

        return ['APP_ENV' => $local ? 'local' : 'production', 'APP_DEBUG' => 'false', 'APP_KEY' => $state['key'],
            'APP_URL' => $url, 'DB_CONNECTION' => $engine, 'DB_HOST' => $database['host'], 'DB_PORT' => $database['port'],
            'DB_DATABASE' => $database['database'], 'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => '',
            'TRAINING_DB_PASSWORD_B64' => base64_encode($database['db_password']),
            'DB_URL' => '', 'DB_SOCKET' => '', 'TRAINING_INSTALL_STATE' => 'installed', 'TRAINING_INSTALL_MODE' => $state['mode'],
            'TRAINING_LOCAL_DEVELOPMENT' => $local ? 'true' : 'false', 'TRAINING_ENVIRONMENT' => $local ? 'test' : 'production',
            'TRAINING_AUTH_MODE' => 'local_password', 'MOCK_IDENTITY_ENABLED' => 'false', 'TRAINING_LOCALHOST_PRODUCTION' => 'false',
            'TRAINING_HOSTED_PASSWORD_LOGIN' => $local ? 'false' : 'true', 'SESSION_DRIVER' => 'database',
            'SESSION_SECURE_COOKIE' => $local ? 'false' : 'true', 'SESSION_HTTP_ONLY' => 'true', 'SESSION_SAME_SITE' => 'lax',
            'SESSION_DOMAIN' => 'null', 'SESSION_COOKIE' => 'training_'.substr($state['id'], 0, 12),
            'QUEUE_CONNECTION' => 'database', 'CACHE_STORE' => 'database', 'MAIL_MAILER' => 'array',
            'TRAINING_SMTP_ENABLED' => 'false', 'LOCAL_ACCOUNT_MAIL_ENABLED' => 'false', 'AI_LIVE_ENABLED' => 'false',
            'TRAINING_DEMO_ENABLED' => 'false', 'TRAINING_PRIVATE_STORAGE' => str_replace('\\', '/', storage_path('app/installations/'.$state['id'].'/private'))];
    }

    private function assertOverrides(array $values): void
    {
        $external = app()->bound('installation.process_environment') ? app('installation.process_environment') : [];
        foreach ($values as $key => $value) {
            if (array_key_exists($key, $external) && (string) $external[$key] !== (string) $value) {
                throw new RuntimeException('A server process environment override conflicts with '.$key.'. Remove or align that override, restart the process and resume setup.');
            }
        }
    }
}
