import base, { browserEnv } from './playwright.ui.config.js';
import path from 'node:path';
process.env.TRAINING_BRANDING_REHEARSAL = '1';
export const brandingEnv = { ...browserEnv, DB_DATABASE: path.resolve('.runtime/branding/preview.sqlite'), APP_CONFIG_CACHE: path.resolve('.runtime/branding/config.php'), APP_URL: 'http://127.0.0.1:8125' };
export default { ...base, testMatch: /appearance.spec.js/, outputDir: '.runtime/branding/results', reporter: [['list'], ['json', {outputFile: '.runtime/branding/browser.json'}]], use: {...base.use, baseURL: 'http://127.0.0.1:8125'}, webServer: {...base.webServer, command: 'php -d extension=gd -S 127.0.0.1:8125 -t public scripts/browser-router.php', url: 'http://127.0.0.1:8125/login', env: brandingEnv} };
