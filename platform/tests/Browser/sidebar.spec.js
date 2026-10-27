import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { resetScenarioCache } from './isolated-cache.js';

test.beforeEach(resetScenarioCache);

for (const [name, expected] of [
    [/Alex/, ['Overview', 'Learning plans', 'Updates', 'People', 'Administration', 'AI settings', 'Appearance']],
    [/Morgan/, ['Overview', 'Learning plans', 'Review queue', 'Updates', 'Appearance']],
    [/Sam/, ['Overview', 'Learning plans', 'Updates', 'Appearance']],
]) {
    test(`sidebar permissions and active state ${name}`, async ({ page }) => {
        await page.goto('/login');
        await page.getByRole('button', { name }).click();
        const menu = page.getByRole('navigation', { name: 'Main navigation' });
        await expect(menu.getByRole('link')).toHaveText(expected);
        await expect(menu.getByRole('link', { name: 'Overview', exact: true })).toHaveAttribute('aria-current', 'page');
        await menu.getByRole('link', { name: 'Updates' }).click();
        await page.waitForLoadState('load');
        await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(244, 248, 251)');
        await expect(menu.getByRole('link', { name: 'Updates' })).toHaveAttribute('aria-current', 'page');
        expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    });
}

test('mobile drawer contains focus, closes and restores focus, resizes safely', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByRole('button', { name: /Sam/ }).click();
    const toggle = page.getByRole('button', { name: 'Open navigation', exact: true });
    await expect(page.getByRole('navigation', { name: 'Main navigation' })).not.toBeVisible();
    await toggle.click();
    await expect(page.getByRole('dialog', { name: 'Workspace menu' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Close navigation' })).toBeFocused();
    const links = page.locator('#app-sidebar a');
    const lastControl = page.locator('#app-sidebar .theme-picker summary');
    await lastControl.focus();
    await page.keyboard.press('Tab');
    await expect(links.first()).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(lastControl).toBeFocused();
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    await page.screenshot({ path: '.runtime/ui-sidebar/mobile-menu.png', fullPage: true });
    await page.keyboard.press('Escape');
    await expect(toggle).toBeFocused();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await toggle.click();
    await page.locator('.menu-backdrop').click({ position: { x: 380, y: 400 } });
    await expect(toggle).toBeFocused();
    await toggle.click();
    await page.setViewportSize({ width: 1440, height: 1000 });
    await expect(page.locator('#app-content')).not.toHaveAttribute('inert');
    await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible();
    await page.screenshot({ path: '.runtime/ui-sidebar/desktop-workspace.png', fullPage: true });
});

test('all top-level admin screens remain responsive and accessible', async ({ page }) => {
    test.setTimeout(180000);
    await page.goto('/login');
    await page.getByRole('button', { name: /Alex/ }).click();
    for (const route of ['/dashboard', '/people', '/administration', '/settings/ai', '/notifications', '/clients']) {
        const response = await page.goto(route);
        expect(response.status()).toBe(200);
        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 900 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${route} at ${width}`).toBe(true);
            expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations, route).toEqual([]);
            await page.screenshot({ path: `.runtime/ui-sidebar/${route.replaceAll('/', '-')}-${width}.png`, fullPage: true });
        }
    }
});
