<?php

namespace App\Console\Commands;

use App\Models\Identity;
use App\Models\User;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\PlatformAdministration;
use Database\Seeders\RealisticDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExtendGlobalAdminDemo extends Command
{
    protected $signature = 'training:demo-global-admin {--database=} {--check}';

    protected $description = 'Extend an existing isolated synthetic demo with one fictional Global Admin';

    public function handle(): int
    {
        $root = RealisticDemoSeeder::guard();
        if ($this->option('database') !== config('demo.database')) {
            throw new \RuntimeException('Explicit demo database must match.');
        }
        $manifest = json_decode(file_get_contents($root.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        if (($manifest['status'] ?? '') !== 'complete' || User::where('email', 'not like', '%.invalid')->exists()) {
            throw new \RuntimeException('A complete fictional demo is required.');
        }
        if ($this->option('check')) {
            $this->info('Isolated fictional demo guard passed.');

            return self::SUCCESS;
        }
        $id = '71cfe6b1-e25c-5a29-950c-8ffdd9f92b10';
        DB::transaction(function () use ($id) {
            $service = app(PlatformAdministration::class);
            $service->lock();
            if ($existing = User::find($id)) {
                if ($existing->email !== 'global.admin@platform-demo.invalid' || ! $existing->is_synthetic || ! app(ClientAccess::class)->active($existing) || ! $service->admins()->where('ra.user_id', $id)->exists()) {
                    throw new \RuntimeException('Existing extension changed. Review manually; no automatic regrant.');
                }

                return;
            }
            if (User::where('email', 'global.admin@platform-demo.invalid')->exists() || $service->admins()->exists()) {
                throw new \RuntimeException('Unexpected platform admin. Review before extension.');
            }
            User::create(['id' => $id, 'name' => 'Alex Rowan — Fictional Global Admin', 'email' => 'global.admin@platform-demo.invalid',
                'password' => Str::random(96), 'environment' => 'test', 'is_synthetic' => true, 'active' => true, 'role' => 'member', 'origin' => 'manual', 'sync_policy' => 'local_only', 'organisation_id' => null]);
            Identity::create(['id' => '98cde696-c982-5ae3-a2fd-8ccce812c023', 'user_id' => $id, 'issuer' => config('training.mock_issuer'), 'subject' => 'demo-platform-global-admin', 'active' => true]);
            $service->appoint(null, $id, 'Approved fictional Global Admin demo extension', 'demo-extension-v1');
        }, 3);
        $this->info('Fictional Global Admin available: Alex Rowan — Fictional Global Admin. Existing demo records preserved.');

        return self::SUCCESS;
    }
}
