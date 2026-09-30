import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { browserEnv, browserDatabase } from '../playwright.config.js';
fs.mkdirSync(path.dirname(browserDatabase), { recursive: true });
if (!fs.existsSync(browserDatabase)) fs.writeFileSync(browserDatabase, '');
const result = spawnSync('php', ['artisan', 'migrate', '--seed', '--no-interaction'], { env: browserEnv, stdio: 'inherit' });
process.exit(result.status ?? 1);
