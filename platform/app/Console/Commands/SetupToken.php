<?php

namespace App\Console\Commands;

use App\Services\Installation\InstallationState;
use Illuminate\Console\Command;

class SetupToken extends Command
{
    protected $signature = 'training:setup-token';

    protected $description = 'Prepare a fresh HTTPS hosted installation and print a private one-hour setup token';

    public function handle(InstallationState $store): int
    {
        $existing = $store->read();
        if ($existing && $existing['mode'] === 'local' && ! empty($existing['upgrade']) && $existing['status'] !== 'installed') {
            $this->warn('Private replacement local-upgrade token; previous operator sessions are invalidated.');
            $this->line($store->issueToken());

            return self::SUCCESS;
        }
        if (parse_url(config('app.url'), PHP_URL_SCHEME) !== 'https') {
            $this->error('Set APP_URL to the intended HTTPS hostname first. Do not generate an application key before fresh setup.');

            return self::FAILURE;
        }
        try {
            $store->initialize('hosted');
            $this->warn('Private one-time setup token; expires in one hour. Enter it at /setup, never in a URL.');
            $this->line($store->issueToken());

            return self::SUCCESS;
        } catch (\RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
