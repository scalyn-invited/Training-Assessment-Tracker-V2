import { defineConfig } from '@playwright/test';
import path from 'node:path';

process.env.TRAINING_UI_REHEARSAL = '1';

export const browserDatabase = path.resolve('.runtime/ui-sidebar/ui.sqlite');
export const browserEnv = {
    ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: browserDatabase,
    MOCK_IDENTITY_ENABLED: 'true', TRAINING_ENVIRONMENT: 'test', MAIL_MAILER: 'array', QUEUE_CONNECTION: 'database',
    SESSION_DRIVER: 'database', CACHE_STORE: 'database', APP_URL: 'http://127.0.0.1:8124',
    APP_CONFIG_CACHE: path.resolve('.runtime/ui-sidebar/config.php'),
};

export default defineConfig({
    testDir: './tests/Browser', testIgnore: /realistic-demo.spec.js/, workers: 1, timeout: 120000,
    expect: { timeout: 10000 },
    outputDir: '.runtime/ui-sidebar/results',
    reporter: [['list'], ['json', { outputFile: '.runtime/ui-sidebar/results.json' }]],
    use: { baseURL: 'http://127.0.0.1:8124', channel: 'chrome', screenshot: 'only-on-failure', trace: 'retain-on-failure' },
    webServer: {
        command: 'php -S 127.0.0.1:8124 -t public scripts/browser-router.php',
        url: 'http://127.0.0.1:8124/login', reuseExistingServer: false, env: browserEnv,
        stdout: 'ignore', stderr: 'ignore',
    },
});
