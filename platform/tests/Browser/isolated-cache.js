import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { browserEnv as defaultEnv } from '../../playwright.config.js';

export function resetScenarioCache() {
    const branding = process.env.TRAINING_BRANDING_REHEARSAL === '1';
    const theme = process.env.TRAINING_THEME_REHEARSAL === '1';
    const preferences = process.env.TRAINING_PREFERENCES_REHEARSAL === '1';
    const automatic = process.env.TRAINING_AUTOMATIC_THEME_REHEARSAL === '1';
    const fixture = preferences ? '.runtime/preferences/browser.sqlite' : automatic ? '.runtime/automatic-theme/browser.sqlite' : theme ? '.runtime/client-theme/browser.sqlite' : '.runtime/branding/preview.sqlite';
    const browserEnv = branding ? { ...defaultEnv, DB_DATABASE: path.resolve(fixture), APP_CONFIG_CACHE: path.resolve(preferences ? '.runtime/preferences/config.php' : automatic ? '.runtime/automatic-theme/config.php' : theme ? '.runtime/client-theme/config.php' : '.runtime/branding/config.php') } : defaultEnv;
    if (browserEnv.DB_CONNECTION !== 'sqlite' || browserEnv.DB_DATABASE !== path.resolve(branding ? fixture : process.env.TRAINING_UI_REHEARSAL === '1' ? '.runtime/ui-sidebar/ui.sqlite' : '.runtime/browser.sqlite') || browserEnv.CACHE_STORE !== 'database') {
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
if (Illuminate\\Support\\Facades\\Schema::hasTable('client_theme_preferences')) { Illuminate\\Support\\Facades\\DB::table('client_theme_preferences')->delete(); Illuminate\\Support\\Facades\\DB::table('client_brandings')->update(['default_theme'=>null]); }
App\\Models\\User::where('is_synthetic', true)->where('environment', 'test')->where('origin', 'primary_import')->update(['permissions_synced_at' => now()]);
` });
    if (refresh.status !== 0) throw new Error(refresh.stderr + refresh.stdout);
}
