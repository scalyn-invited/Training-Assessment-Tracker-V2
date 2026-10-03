import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { browserEnv } from '../../playwright.config.js';

export function resetScenarioCache() {
    if (browserEnv.DB_CONNECTION !== 'sqlite' || browserEnv.DB_DATABASE !== path.resolve('.runtime/browser.sqlite') || browserEnv.CACHE_STORE !== 'database') {
        throw new Error('Refusing to clear cache outside the isolated browser fixture.');
    }
    // Keep real throttling enabled within each scenario; separate scenarios do not share a login budget.
    const result = spawnSync('php', ['artisan', 'cache:clear'], { env: browserEnv, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(result.stderr + result.stdout);
}
