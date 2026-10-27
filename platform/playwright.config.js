import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const uiRehearsal = process.env.TRAINING_UI_REHEARSAL === '1';
export const browserDatabase = path.resolve(uiRehearsal ? '.runtime/ui-sidebar/ui.sqlite' : '.runtime/browser.sqlite');
export const browserEnv = {
    ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: browserDatabase,
    MOCK_IDENTITY_ENABLED: 'true', TRAINING_ENVIRONMENT: 'test', MAIL_MAILER: 'array', QUEUE_CONNECTION: 'database',
    SESSION_DRIVER: 'database', CACHE_STORE: 'database', APP_URL: uiRehearsal ? 'http://127.0.0.1:8124' : 'http://127.0.0.1:8123',
    APP_CONFIG_CACHE: path.resolve(uiRehearsal ? '.runtime/ui-sidebar/config.php' : '.runtime/browser-config.php'),
};

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    timeout: 60000,
    expect: { timeout: 15000 },
    workers: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: { baseURL: 'http://127.0.0.1:8123', channel: 'chrome', trace: 'retain-on-failure', screenshot: 'only-on-failure', actionTimeout: 15000 },
    webServer: {
        command: 'php -S 127.0.0.1:8123 -t public scripts/browser-router.php',
        url: 'http://127.0.0.1:8123/login',
        reuseExistingServer: false,
        timeout: 60000,
        stdout: 'ignore',
        stderr: 'ignore',
        env: browserEnv,
    },
    projects: [{ name: 'desktop', use: { ...devices['Desktop Chrome'] } }],
});
