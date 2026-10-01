import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { browserEnv, browserDatabase } from '../playwright.config.js';
fs.mkdirSync(path.dirname(browserDatabase), { recursive: true });
if (!fs.existsSync(browserDatabase)) fs.writeFileSync(browserDatabase, '');
// This fixed, ignored database belongs only to the browser suite. Never reset the app database.
if (browserDatabase !== path.resolve('.runtime/browser.sqlite') || browserEnv.DB_CONNECTION !== 'sqlite' || browserEnv.DB_DATABASE !== browserDatabase) {
    throw new Error('Refusing to reset a database outside the isolated browser fixture.');
}
const result = spawnSync('php', ['artisan', 'migrate:fresh', '--seed', '--no-interaction'], { env: browserEnv, stdio: 'inherit' });
process.exit(result.status ?? 1);
