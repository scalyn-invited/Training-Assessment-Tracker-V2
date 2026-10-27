import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { browserEnv } from '../../playwright.config.js';

export function resetScenarioCache() {
    if (browserEnv.DB_CONNECTION !== 'sqlite' || browserEnv.DB_DATABASE !== path.resolve(process.env.TRAINING_UI_REHEARSAL === '1' ? '.runtime/ui-sidebar/ui.sqlite' : '.runtime/browser.sqlite') || browserEnv.CACHE_STORE !== 'database') {
        throw new Error('Refusing to clear cache outside the isolated browser fixture.');
    }
    // Keep real throttling enabled within each scenario; separate scenarios do not share a login budget.
    const result = spawnSync('php', ['artisan', 'cache:clear'], { env: browserEnv, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(result.stderr + result.stdout);
    // Each scenario starts with a fresh synthetic import; real expiry remains enforced.
    const refresh = spawnSync('php', [], { env: browserEnv, encoding: 'utf8', input: `<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
if (realpath(config('database.connections.sqlite.database')) !== realpath(getenv('DB_DATABASE'))) { throw new RuntimeException('Wrong fixture database'); }
App\\Models\\User::where('is_synthetic', true)->where('environment', 'test')->where('origin', 'primary_import')->update(['permissions_synced_at' => now()]);
` });
    if (refresh.status !== 0) throw new Error(refresh.stderr + refresh.stdout);
}
