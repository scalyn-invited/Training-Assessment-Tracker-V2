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
    await page.getByRole('button', { name: 'Use client default' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    expect(errors).toEqual([]);
});

test('preferences are isolated between identities and storage failure stays usable', async ({ page }) => {
    await login(page, /Sam/);
    await page.getByRole('link', { name: 'Appearance', exact: true }).click();
    await page.getByRole('group', { name: 'Workspace theme' }).getByRole('button', { name: 'Neon Cyan', exact: true }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'neon-cyan');
    await page.getByRole('button', { name: 'Sign out' }).click();
    await login(page, /Jordan/);
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await page.getByRole('button', { name: 'Sign out' }).click();
    await login(page, /Sam/);
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'neon-cyan');
    await page.addInitScript(() => { Object.defineProperty(window, 'localStorage', { get() { throw new Error('Storage unavailable'); } }); });
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'neon-cyan');
    await page.getByRole('link', { name: 'Appearance', exact: true }).click();
    await page.getByRole('group', { name: 'Workspace theme' }).getByRole('button', { name: 'Light', exact: true }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await expect(page.locator('#appearance-status')).toContainText('theme selected');
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
    await expect(page.getByRole('button', { name: 'Use client default' })).toHaveCSS('min-height', '38px');
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    await page.screenshot({ path: 'test-results/appearance-mobile.png', fullPage: true });
});

test('admin can preview co-branding and white-label without saving changes', async ({ page }) => {
    await login(page);
    const savedBrand = await page.locator('.sidebar-top .brand-copy strong').textContent();
    await page.getByRole('link', { name: 'Appearance', exact: true }).click();
    await page.getByLabel('Branding mode').selectOption('co-branded');
    await page.getByLabel('Client name').fill('Northstar');
    await page.getByLabel('Application name').fill('Northstar Academy');
    await expect(page.locator('[data-preview-brand]').first()).toHaveText('Northstar');
    await expect(page.locator('[data-preview-parent]').first()).toBeVisible();
    await page.getByLabel('Branding mode').selectOption('white-label');
    await expect(page.locator('[data-preview-parent]').first()).not.toBeVisible();
    await expect(page.locator('[data-preview-name]').first()).toHaveText('Northstar Academy');
    await expect(page.locator('.sidebar-top .brand-copy strong')).toHaveText(savedBrand);
    await page.screenshot({ path: 'test-results/white-label-preview.png', fullPage: true });
    await page.reload();
    await expect(page.getByLabel('Branding mode')).toHaveValue('white-label');
});


test('client branding saves with a logo and survives desktop and mobile navigation', async ({ page }) => {
    await login(page);
    await page.goto('/settings/appearance');
    await page.getByLabel('Client name', { exact: true }).fill('Northstar');
    await page.getByLabel('Application name').fill('Northstar Academy');
    await page.getByLabel('Brand colour', { exact: true }).fill('#ffffff');
    await page.getByLabel('Client logo', {exact:true}).setInputFiles('public/images/scalyn-opc-logo.png');
    await page.getByRole('button', {name:'Save client branding'}).click();
    await expect(page.getByText('Client branding saved.', {exact:false})).toBeVisible();
    await expect(page.locator('.sidebar-top .brand-copy strong')).toHaveText('Northstar');
    const image = page.locator('.brand-logo.logo-light');
    await expect(image).toHaveAttribute('src', /\/branding\/.+\/logo/);
    await expect.poll(() => image.evaluate(img => img.complete && img.naturalWidth > 0)).toBeTruthy();
    for (const [id, name] of Object.entries(themes)) {
        await page.getByRole('group', {name:'Workspace theme'}).getByRole('button',{name,exact:true}).click();
        await expect(page.locator('html')).toHaveAttribute('data-theme', id);
        expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
    }
    await page.screenshot({path:'test-results/saved-branding-desktop.png', fullPage:true});
    await page.setViewportSize({width:390,height:844});
    await expect(page.getByRole('button',{name:'Save client branding'})).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
    await page.screenshot({path:'test-results/saved-branding-mobile.png',fullPage:true});
    await page.getByLabel('Client name',{exact:true}).fill('Unsaved name');
    await page.getByRole('link',{name:'Discard changes'}).click();
    await expect(page.getByLabel('Client name',{exact:true})).toHaveValue('Northstar');
    await expect(page.locator('html')).toHaveAttribute('data-theme','neon-amber');
});



test('one brand colour generates accessible light/dark themes with consistent preview and selection', async ({ page }) => {
    test.setTimeout(240000);
    await login(page);
    await page.goto('/settings/appearance');
    const gallery = page.getByRole('group', {name:'Workspace theme'});
    await gallery.getByRole('button', {name:'Light',exact:true}).click();
    await expect(page.locator('input[type=color]')).toHaveCount(1);
    await expect(page.getByLabel('Primary colour',{exact:true})).toHaveCount(0);
    await page.getByLabel('Brand colour',{exact:true}).fill('#ffef00');
    await expect(page.getByLabel('Brand colour hex value')).toHaveValue('#ffef00');
    await expect(page.locator('[data-palette-preview]')).toHaveCount(2);
    const previewPrimary = await page.locator('[data-palette-preview=light]').evaluate(el => getComputedStyle(el).getPropertyValue('--preview-primary').trim());
    await page.getByRole('button',{name:'Save client branding'}).click();
    await expect(page.getByText('Client branding saved.',{exact:false})).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('data-theme','light');
    await gallery.getByRole('button',{name:'Client Light',exact:true}).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme','client-light');
    expect(await page.locator('html').evaluate(el => getComputedStyle(el).getPropertyValue('--client-primary').trim())).toBe(previewPrimary);
    expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
    await page.screenshot({path:'test-results/automatic-theme-desktop.png',fullPage:true});
    await gallery.getByRole('button',{name:'Client Dark',exact:true}).click();
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme','client-dark');
    expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
    await page.setViewportSize({width:390,height:844});
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
    await page.screenshot({path:'test-results/automatic-theme-mobile.png',fullPage:true});
    for (const source of ['#000000','#ffffff','#ff0000','#00ff00','#0000ff','#fefefe','#808080']) {
        await page.getByLabel('Brand colour hex value').fill(source);
        await expect(page.getByLabel('Brand colour',{exact:true})).toHaveValue(source);
        await page.getByRole('button',{name:'Save client branding'}).click();
        await expect(page.getByText('Client branding saved.',{exact:false})).toBeVisible();
        for (const theme of ['Client Light','Client Dark']) {
            await gallery.getByRole('button',{name:theme,exact:true}).click();
            expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
        }
    }
    // An obsolete browser value cannot override an existing account decision.
    await page.evaluate(() => localStorage.setItem(document.documentElement.dataset.themeKey, 'client'));
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme','client-dark');
    await page.goto('/dashboard');
    await expect(page.locator('html')).toHaveAttribute('data-theme','client-dark');
    expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
    await page.goto('/settings/appearance');
    await gallery.getByRole('button',{name:'Light',exact:true}).click();
    await expect(page.locator('body')).toHaveCSS('background-color','rgb(244, 248, 251)');
    for (const name of Object.values(themes)) await expect(gallery.getByRole('button',{name,exact:true})).toBeVisible();
});


test('client default and account choice follow separate browser contexts', async ({page,browser}) => {
 await login(page);
 await page.goto('/settings/appearance');
 await page.getByLabel('Client default theme',{exact:true}).selectOption('client-dark');
 await page.getByRole('button',{name:'Save client branding'}).click();
 await expect(page.getByText('Client branding saved.',{exact:false})).toBeVisible();
 const origin=new URL(page.url()).origin;
 const other=await browser.newContext(); const member=await other.newPage();
 await member.goto(origin+'/login'); await member.getByRole('button',{name:/Jordan/}).click();
 await expect(member.locator('html')).toHaveAttribute('data-theme','client-dark');
 await member.goto(origin+'/settings/appearance');
 await member.evaluate(()=>localStorage.setItem(document.documentElement.dataset.themeKey,'neon-amber'));
 await member.reload(); await expect(member.getByRole('button',{name:'Keep previous theme'})).toBeVisible();
 await member.getByRole('button',{name:'Keep previous theme'}).click();
 await expect(member.locator('html')).toHaveAttribute('data-theme','neon-amber');
 const third=await browser.newContext(); const again=await third.newPage();
 await again.goto(origin+'/login'); await again.getByRole('button',{name:/Jordan/}).click();
 await expect(again.locator('html')).toHaveAttribute('data-theme','neon-amber');
 await again.goto(origin+'/settings/appearance');
 await again.getByRole('button',{name:'Use client default',exact:true}).click();
 await expect(again.locator('html')).toHaveAttribute('data-theme','client-dark');
 // The first device must reload before overwriting a newer decision.
 await member.getByRole('group',{name:'Workspace theme'}).getByRole('button',{name:'Light',exact:true}).click();
 await expect(member.locator('#appearance-status')).toContainText('changed elsewhere');
 await expect(member.locator('html')).toHaveAttribute('data-theme','neon-amber');
 await member.reload(); await expect(member.locator('#legacy-theme-choice')).toHaveCount(0);
 await expect(member.locator('html')).toHaveAttribute('data-theme','client-dark');
 await page.screenshot({path:'test-results/preferences-desktop.png',fullPage:true});
 await member.setViewportSize({width:390,height:844});
 await member.screenshot({path:'test-results/preferences-mobile.png',fullPage:true});
 expect((await new AxeBuilder({page:member}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
 await other.close(); await third.close();
});
