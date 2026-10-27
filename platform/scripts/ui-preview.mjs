import fs from 'node:fs';
import { spawn } from 'node:child_process';
import { browserDatabase, browserEnv } from '../playwright.ui.config.js';

if (!fs.existsSync(browserDatabase)) throw new Error('Prepare the isolated UI fixture with node scripts/ui-prepare.mjs first.');
console.log('UI preview: http://127.0.0.1:8124/login');
console.log('Synthetic sign-in: Alex (administrator), Casey or Morgan (coordinator), Jordan (member).');
const server = spawn('php', ['-S', '127.0.0.1:8124', '-t', 'public', 'scripts/browser-router.php'], {
    env: browserEnv, stdio: 'inherit', windowsHide: true,
});
server.on('error', error => { console.error(error.message); process.exitCode = 1; });
server.on('exit', code => { process.exitCode = code ?? 1; });
process.on('SIGINT', () => server.kill());
process.on('SIGTERM', () => server.kill());
