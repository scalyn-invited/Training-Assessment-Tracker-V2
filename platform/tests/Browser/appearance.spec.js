import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { resetScenarioCache } from './isolated-cache.js';

test.beforeEach(resetScenarioCache);
const themes = { light: 'Light', dark: 'Dark', 'neon-cyan': 'Neon Cyan', 'neon-emerald': 'Neon Emerald', 'neon-amber': 'Neon Amber' };
async function login(page, name = /Alex/) {
    await page.goto('/login');
    await page.getByRole('button', { name }).click();
    await expect(page).toHaveURL(/dashboard/);
}

test('all five themes persist across pages and pass automated contrast checks', async ({ page }) => {
    test.setTimeout(180000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await login(page);
    await page.getByRole('link', { name: 'Appearance', exact: true }).click();
    for (const [id, name] of Object.entries(themes)) {
        await page.getByRole('group', { name: 'Workspace theme' }).getByRole('button', { name, exact: true }).click();
        await expect(page.locator('html')).toHaveAttribute('data-theme', id);
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', id);
        await expect(page.getByRole('group', { name: 'Workspace theme' }).getByRole('button', { name, exact: true })).toHaveAttribute('aria-pressed', 'true');
        const audit = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        expect(audit.violations).toEqual([]);
        await page.getByRole('link', { name: 'Overview', exact: true }).click();
        await expect(page.locator('html')).toHaveAttribute('data-theme', id);
        await page.screenshot({ path: 'test-results/dashboard-' + id + '.png', fullPage: true });
        await page.getByRole('link', { name: 'Appearance', exact: true }).click();
    }
    await page.getByRole('button', { name: 'Use workspace default' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    expect(errors).toEqual([]);
});

test('preferences are isolated between identities and storage failure stays usable', async ({ page }) => {
    await login(page, /Sam/);
    await page.getByRole('link', { name: 'Appearance', exact: true }).click();
    await page.getByRole('group', { name: 'Workspace theme' }).getByRole('button', { name: 'Neon Cyan', exact: true }).click();
    await page.getByRole('button', { name: 'Sign out' }).click();
    await login(page, /Jordan/);
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await page.getByRole('button', { name: 'Sign out' }).click();
    await login(page, /Sam/);
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'neon-cyan');
    await page.addInitScript(() => { Object.defineProperty(window, 'localStorage', { get() { throw new Error('Storage unavailable'); } }); });
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await page.getByRole('link', { name: 'Appearance', exact: true }).click();
    await page.getByRole('group', { name: 'Workspace theme' }).getByRole('button', { name: 'Light', exact: true }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await expect(page.locator('#appearance-status')).toContainText('Browser storage is unavailable');
});

test('mobile sidebar supports keyboard dismissal, navigation and narrow layouts', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, /Sam/);
    await expect(page.getByRole('navigation', { name: 'Main navigation' })).not.toBeVisible();
    const toggle = page.getByRole('button', { name: 'Open navigation', exact: true });
    await toggle.click();
    await expect(page.locator('#nav-toggle')).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Escape');
    await expect(toggle).toBeFocused();
    await toggle.click();
    await page.getByRole('link', { name: 'Appearance', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Appearance', exact: true })).toBeVisible();
    await expect(page.locator('#nav-toggle')).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('.theme-tile').first()).toHaveCSS('display', 'flex');
    await expect(page.locator('.theme-preview').first()).toHaveCSS('height', '72px');
    for (const width of [320, 390, 768]) {
        await page.setViewportSize({ width, height: 844 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    await expect(page.locator('.secondary')).toHaveCSS('min-height', '38px');
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    await page.screenshot({ path: 'test-results/appearance-mobile.png', fullPage: true });
});

test('admin can preview co-branding and white-label without saving changes', async ({ page }) => {
    await login(page);
    await page.getByRole('link', { name: 'Appearance', exact: true }).click();
    await page.getByLabel('Branding mode').selectOption('co-branded');
    await page.getByLabel('Client name').fill('Northstar');
    await page.getByLabel('Application name').fill('Northstar Academy');
    await expect(page.locator('[data-preview-brand]')).toHaveText('Northstar');
    await expect(page.locator('[data-preview-parent]')).toBeVisible();
    await page.getByLabel('Branding mode').selectOption('white-label');
    await expect(page.locator('[data-preview-parent]')).not.toBeVisible();
    await expect(page.locator('[data-preview-name]')).toHaveText('Northstar Academy');
    await expect(page.locator('.sidebar-top .brand-copy strong')).toHaveText('Scalyn');
    await page.screenshot({ path: 'test-results/white-label-preview.png', fullPage: true });
    await page.reload();
    await expect(page.getByLabel('Branding mode')).toHaveValue('white-label');
});
