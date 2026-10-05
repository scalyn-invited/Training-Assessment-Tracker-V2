import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { browserEnv } from '../../playwright.config.js';
import { resetScenarioCache } from './isolated-cache.js';

test.beforeEach(resetScenarioCache);

test('coordinator reviews extracted text and confirms a version; member cannot open raw extraction', async ({ page }) => {
    test.setTimeout(120000);
    await page.goto('/login');
    await page.getByRole('button', { name: /Morgan/ }).click();
    await page.locator('.plan-card').first().click();
    await page.getByRole('link', { name: 'Extract and review' }).click();
    const extractionUrl = page.url();
    await page.getByRole('button', { name: 'Request extraction' }).click();
    // Seeder supplies a clean, original synthetic text fixture; no scan bypass is available in the UI.
    const worker = spawnSync('php', ['artisan', 'queue:work', '--queue=extraction', '--stop-when-empty', '--tries=1', '--timeout=120'], { env: browserEnv, encoding: 'utf8', timeout: 45000 });
    expect(worker.status, worker.stderr + worker.stdout).toBe(0);
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Extraction status: ready' })).toBeVisible();
    await expect(page.getByText('Synthetic evidence fixture.', { exact: false })).toBeVisible();
    await expect(page.locator('form[data-preserve-input]')).toHaveAttribute('data-ready', 'true');
    await page.getByLabel('Provider or observer').fill('Synthetic browser observer');
    await page.getByLabel('Assessment date').fill('2026-09-01');
    await page.getByLabel('Scoring scale (or not scored)').fill('Not scored; synthetic download fixture only.');
    await page.getByLabel('Findings to confirm').fill('Reviewed source is a synthetic access fixture and makes no competence claim. Needs a separate assessment before real training decisions.');
    await page.getByLabel('Evidence type: provider score, self report or observed evidence').selectOption('observed evidence');
    await page.getByRole('button', { name: 'Save review draft' }).click();
    await expect(page.getByText('Review draft saved. Findings have not been confirmed or sent to AI.')).toBeVisible();
    await page.reload();
    await expect(page.getByLabel('Findings to confirm')).toHaveValue(/Reviewed source is a synthetic access fixture/);
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    await page.screenshot({ path: 'test-results/document-review-mobile.png', fullPage: true });
    await expect(page.locator('form[data-preserve-input]')).toHaveAttribute('data-ready', 'true');
    await page.getByRole('button', { name: 'Confirm reviewed findings' }).click();
    await expect(page.getByRole('heading', { name: 'Extraction status: confirmed' })).toBeVisible();
    await page.getByRole('link', { name: 'Back to assessment findings' }).click();
    await expect(page.getByLabel('Findings to confirm')).toHaveValue(/Reviewed source is a synthetic access fixture/);
    await expect(page.locator('#assessment-confirmation')).toHaveText('Confirmed by coordinator for the saved assessment.');
    await page.getByRole('button', { name: 'Sign out' }).click();
    await page.getByRole('button', { name: /Jordan/ }).click();
    const denied = await page.goto(extractionUrl);
    expect(denied.status()).toBe(403);
});
