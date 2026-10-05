import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { browserEnv } from '../../playwright.config.js';
import { resetScenarioCache } from './isolated-cache.js';

test.beforeEach(resetScenarioCache);

async function login(page, label) {
    await page.goto('/login');
    await page.getByRole('button', { name: label }).click();
    await expect(page).toHaveURL(/dashboard/);
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(244, 247, 245)');
}
test('learner cannot open a peer plan; private download and queued receipt work', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await login(page, /Jordan/);
    const foreignPlan = await page.locator('.plan-card').first().getAttribute('href');
    await page.getByRole('button', { name: 'Sign out' }).click();
    await login(page, /Sam/);
    await expect(page.locator('.plan-card')).toHaveCount(1);
    await expect(page.getByText('Customer communication fundamentals')).toHaveCount(0);
    const denied = await page.goto(foreignPlan);
    expect(denied.status()).toBe(404);
    await page.goto('/dashboard');
    await page.locator('.plan-card').first().click();
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(244, 247, 245)');
    const downloaded = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Download' }).click();
    expect((await downloaded).suggestedFilename()).toBe('synthetic-evidence.txt');
    await page.getByRole('button', { name: 'Queue foundation check' }).click();
    await expect(page.getByText('Check queued.', { exact: false })).toBeVisible();
    const worker = spawnSync('php', ['artisan', 'queue:work', '--queue=notifications', '--stop-when-empty', '--tries=3'], { env: browserEnv, encoding: 'utf8', timeout: 30000 });
    expect(worker.status, worker.stderr + worker.stdout).toBe(0);
    await page.getByRole('region', { name: 'Background check receipts' }).scrollIntoViewIfNeeded();
    expect(errors).toEqual([]);
    await expect(page.getByText('Delivered', { exact: true }).first()).toBeVisible({ timeout: 15000 });
    await page.screenshot({ path: 'test-results/learner-plan.png', fullPage: true });
    expect(errors).toEqual([]);
});
test('coordinator and admin dashboards expose only their intended scope', async ({ page }) => {
    await login(page, /Casey/);
    await expect(page.locator('.plan-card')).toHaveCount(1);
    await expect(page.getByRole('link', { name: 'People', exact: true })).toHaveCount(0);
    await page.screenshot({ path: 'test-results/coordinator-dashboard.png', fullPage: true });
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(results.violations).toEqual([]);
    await page.getByRole('button', { name: 'Sign out' }).click();
    await login(page, /Alex/);
    await expect(page.locator('.plan-card')).toHaveCount(2);
    await page.getByRole('link', { name: 'People', exact: true }).click();
    await expect(page.getByRole('cell', { name: /Sam/ })).toBeVisible();
});
test('mobile sign-in and learner view have no horizontal overflow or automated accessibility violations', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(244, 247, 245)');
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to content' })).toBeFocused();
    await page.screenshot({ path: 'test-results/mobile-login.png', fullPage: true });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    await page.getByRole('button', { name: /Sam/ }).click();
    await page.locator('.plan-card').first().click();
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(244, 247, 245)');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    await page.screenshot({ path: 'test-results/mobile-plan.png', fullPage: true });
});
