import base, { brandingEnv } from './playwright.branding.config.js';
import path from 'node:path';
process.env.TRAINING_THEME_REHEARSAL = '1';
export const themeEnv = {...brandingEnv, DB_DATABASE: path.resolve('.runtime/client-theme/browser.sqlite'), APP_CONFIG_CACHE: path.resolve('.runtime/client-theme/config.php'), APP_URL: 'http://127.0.0.1:8126'};
export default {...base, outputDir: '.runtime/client-theme/results', reporter: [['list'], ['json', {outputFile: '.runtime/client-theme/browser.json'}]], use: {...base.use, baseURL:'http://127.0.0.1:8126'}, webServer: {...base.webServer, command:'php -d extension=gd -S 127.0.0.1:8126 -t public scripts/browser-router.php', url:'http://127.0.0.1:8126/login', env:themeEnv}};
