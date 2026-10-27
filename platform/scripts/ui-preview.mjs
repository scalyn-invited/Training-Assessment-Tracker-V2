import fs from 'node:fs';
import { spawn, spawnSync } from 'node:child_process';
import { browserDatabase, browserEnv } from '../playwright.ui.config.js';

if (!fs.existsSync(browserDatabase)) throw new Error('Prepare the isolated UI fixture with node scripts/ui-prepare.mjs first.');
const readiness = spawnSync('php', ['-d', 'extension=gd', '-r', `require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); if (! Illuminate\\Support\\Facades\\Schema::hasColumn('client_brandings', 'theme_colours') || ! Illuminate\\Support\\Facades\\Schema::hasTable('client_theme_preferences') || ! extension_loaded('gd')) { fwrite(STDERR, 'Preview needs the branding migration and GD. Prepare only the isolated UI database before starting.'); exit(2); }`], {env: browserEnv, stdio: 'inherit'});
if (readiness.status !== 0) process.exit(readiness.status ?? 1);
console.log('UI preview: http://127.0.0.1:8124/login');
console.log('Synthetic sign-in: Alex (administrator), Casey or Morgan (coordinator), Jordan (member).');
const server = spawn('php', ['-d', 'extension=gd', '-S', '127.0.0.1:8124', '-t', 'public', 'scripts/browser-router.php'], {
    env: browserEnv, stdio: 'inherit', windowsHide: true,
});
server.on('error', error => { console.error(error.message); process.exitCode = 1; });
server.on('exit', code => { process.exitCode = code ?? 1; });
process.on('SIGINT', () => server.kill());
process.on('SIGTERM', () => server.kill());
