<?php

namespace App\Console\Commands;

use App\Services\Installation\InstallationState;
use Illuminate\Console\Command;

class PrepareLocalUpgrade extends Command
{
    protected $signature = 'training:prepare-local-upgrade';

    protected $description = 'Preserve a legacy SQLite installation and open a private wizard for a new empty MariaDB workspace';

    public function handle(InstallationState $store): int
    {
        if (config('installation.status') === 'installed' || $store->read()
            || config('database.default') !== 'sqlite' || ! is_file(base_path('.env'))
            || ! in_array(parse_url(config('app.url'), PHP_URL_HOST), ['127.0.0.1', 'localhost', '[::1]'], true)
            || app()->configurationIsCached()) {
            $this->error('Only an un-cached legacy localhost SQLite installation can enter this upgrade. Completed wizard installations cannot reopen.');

            return self::FAILURE;
        }
        $store->locked(function () use ($store) {
            if ($store->read()) {
                throw new \RuntimeException('An installation was already prepared. It will not be replaced.');
            }
            $original = file_get_contents(base_path('.env'));
            $store->atomic($store->directory().'/environment-before-upgrade.env', $original);
            $store->write(['id' => bin2hex(random_bytes(16)), 'status' => 'pending', 'mode' => 'local', 'upgrade' => true,
                'key' => config('app.key') ?: 'base64:'.base64_encode(random_bytes(32)), 'created_at' => gmdate('c')]);
            // artisan serve passes APP_ENV into its child process even when it reloads .env.
            $prepared = preg_match('/^APP_ENV=.*$/m', $original)
                ? preg_replace('/^APP_ENV=.*$/m', 'APP_ENV=local', $original) : rtrim($original)."\nAPP_ENV=local\n";
            $store->atomic(base_path('.env'), $prepared);
        });
        $this->info('Configuration backed up. SQLite and private files are unchanged. Start php artisan serve and open /setup.');
        $this->warn('Private one-time upgrade token; expires in one hour:');
        $this->line($store->issueToken());

        return self::SUCCESS;
    }
}
