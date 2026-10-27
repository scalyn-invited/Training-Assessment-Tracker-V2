import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { browserDatabase, browserEnv } from '../playwright.ui.config.js';

if (browserDatabase !== path.resolve('.runtime/ui-sidebar/ui.sqlite') || browserEnv.DB_DATABASE !== browserDatabase) {
    throw new Error('Refusing to prepare a database outside the dedicated UI fixture.');
}
fs.mkdirSync(path.dirname(browserDatabase), { recursive: true });
if (fs.existsSync(browserDatabase)) throw new Error('UI database already exists; preserve it and review before preparing another.');
fs.writeFileSync(browserDatabase, '');
const result = spawnSync('php', ['artisan', 'migrate', '--seed', '--no-interaction'], { env: browserEnv, stdio: 'inherit' });
process.exit(result.status ?? 1);
