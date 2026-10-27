import fs from 'node:fs';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { brandingEnv } from '../playwright.branding.config.js';
if (brandingEnv.DB_DATABASE !== path.resolve('.runtime/branding/preview.sqlite')) throw new Error('Wrong preview database');
if (process.argv.includes('--prepare')) {
    if (fs.existsSync(brandingEnv.DB_DATABASE)) throw new Error('Preserve the existing preview; choose a fresh reviewed rehearsal instead.');
    fs.mkdirSync(path.dirname(brandingEnv.DB_DATABASE), {recursive:true});
    fs.writeFileSync(brandingEnv.DB_DATABASE, '');
    const result = spawnSync('php', ['-d','extension=gd','artisan','migrate','--seed','--no-interaction'], {env:brandingEnv, stdio:'inherit'});
    process.exit(result.status ?? 1);
}
if (!fs.existsSync(brandingEnv.DB_DATABASE)) throw new Error('Run with --prepare first.');
console.log('Branding preview: http://127.0.0.1:8125/login — Alex (Client Admin), Jordan (member).');
const server = spawn('php',['-d','extension=gd','-S','127.0.0.1:8125','-t','public','scripts/browser-router.php'], {env:brandingEnv, stdio:'inherit', windowsHide:true});
server.on('exit', code => {process.exitCode = code ?? 1;});
server.on('error', error => {console.error(error.message);process.exitCode = 1;});
process.on('SIGINT', () => server.kill());
process.on('SIGTERM', () => server.kill());
