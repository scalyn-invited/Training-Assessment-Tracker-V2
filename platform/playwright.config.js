import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const uiRehearsal = process.env.TRAINING_UI_REHEARSAL === '1';
const browserPort = uiRehearsal ? 8124 : Number(process.env.TRAINING_BROWSER_PORT || 8123);
if (!Number.isInteger(browserPort) || browserPort < 1024 || browserPort > 65535) throw new Error('Invalid browser test port');
const browserUrl = 'http://127.0.0.1:' + browserPort;

export const browserDatabase = path.resolve(uiRehearsal ? '.runtime/ui-sidebar/ui.sqlite' : '.runtime/browser.sqlite');
export const browserEnv = {
    ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: browserDatabase,
    MOCK_IDENTITY_ENABLED: 'true', TRAINING_ENVIRONMENT: 'test', MAIL_MAILER: 'array', QUEUE_CONNECTION: 'database',
    SESSION_DRIVER: 'database', CACHE_STORE: 'database', APP_URL: browserUrl,
    APP_CONFIG_CACHE: path.resolve(uiRehearsal ? '.runtime/ui-sidebar/config.php' : '.runtime/browser-config.php'),
};

export default defineConfig({
    testDir: './tests/Browser', testIgnore: /realistic-demo.spec.js/,
    fullyParallel: false,
    timeout: 60000,
    expect: { timeout: 15000 },
    workers: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: { baseURL: browserUrl, channel: 'chrome', trace: 'retain-on-failure', screenshot: 'only-on-failure', actionTimeout: 15000 },
    webServer: {
        command: 'php -S 127.0.0.1:' + browserPort + ' -t public scripts/browser-router.php',
        url: browserUrl + '/login',
        reuseExistingServer: false,
        timeout: 60000,
        stdout: 'ignore',
        stderr: 'ignore',
        env: browserEnv,
    },
    projects: [{ name: 'desktop', use: { ...devices['Desktop Chrome'] } }],
});
