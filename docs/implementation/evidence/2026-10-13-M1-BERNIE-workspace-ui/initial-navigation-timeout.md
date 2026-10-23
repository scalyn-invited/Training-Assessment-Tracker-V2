# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: appearance.spec.js >> preferences are isolated between identities and storage failure stays usable
- Location: tests\Browser\appearance.spec.js:37:1

# Error details

```
TimeoutError: locator.click: Timeout 15000ms exceeded.
Call log:
  - waiting for getByRole('link', { name: 'Appearance', exact: true })
    - locator resolved to <a href="http://127.0.0.1:8141/settings/appearance">…</a>
  - attempting click action
    - waiting for element to be visible, enabled and stable
    - element is visible, enabled and stable
    - scrolling into view if needed
    - done scrolling
    - performing click action
    - click action done
    - waiting for scheduled navigations to finish

```

# Page snapshot

```yaml
- generic [active] [ref=f10e1]:
  - link "Skip to content" [ref=f10e2] [cursor=pointer]:
    - /url: "#main"
  - complementary "Application sidebar" [ref=f10e3]:
    - link "Cerebru Assessment Training Tracker home" [ref=f10e5] [cursor=pointer]:
      - /url: http://127.0.0.1:8141/dashboard
      - generic [ref=f10e7]:
        - strong [ref=f10e8]: Cerebru
        - generic [ref=f10e9]: Assessment Training Tracker
    - paragraph [ref=f10e10]: Your workspace
    - navigation "Main navigation" [ref=f10e11]:
      - link "Overview" [ref=f10e12] [cursor=pointer]:
        - /url: http://127.0.0.1:8141/dashboard
      - link "Learning plans" [ref=f10e16] [cursor=pointer]:
        - /url: http://127.0.0.1:8141/dashboard#learning-plans
      - link "Updates" [ref=f10e20] [cursor=pointer]:
        - /url: http://127.0.0.1:8141/notifications
      - generic [ref=f10e24]: Manage
      - link "Appearance" [ref=f10e25] [cursor=pointer]:
        - /url: http://127.0.0.1:8141/settings/appearance
    - generic [ref=f10e30]:
      - link "Back to Cerebru" [ref=f10e31] [cursor=pointer]:
        - /url: https://opsbrain-eosin.vercel.app/home
      - paragraph [ref=f10e34]:
        - generic [ref=f10e35]: One step at a time
        - text: Progress startswith showing up.
      - group [ref=f10e36]:
        - generic "Theme Light" [ref=f10e37] [cursor=pointer]:
          - generic [ref=f10e40]: Theme
          - generic [ref=f10e41]: Light
  - generic [ref=f10e42]:
    - banner [ref=f10e43]:
      - generic [ref=f10e44]: Assessment Training Tracker
      - generic [ref=f10e45]:
        - generic [aria-hidden] [ref=f10e46]: S
        - strong [ref=f10e48]: Sam · Operations learner
        - button "Sign out" [ref=f10e50] [cursor=pointer]
    - generic [ref=f10e53]:
      - strong [ref=f10e55]: LOCAL SANDBOX
      - generic [ref=f10e56]: Synthetic people · Mock sign-in · No email delivery
    - main [ref=f10e57]:
      - generic [ref=f10e59]:
        - paragraph [ref=f10e60]: YOUR WORKSPACE
        - heading "Appearance" [level=1] [ref=f10e61]
        - paragraph [ref=f10e62]: Make your learning space feel right for you.
      - region [ref=f10e63]:
        - heading "Choose your theme" [level=2] [ref=f10e64]
        - paragraph [ref=f10e65]: Choose a look for this account on this browser. Your preference stays with you as you move between pages.
        - group "Workspace theme" [ref=f10e66]:
          - button "Light" [pressed] [ref=f10e67] [cursor=pointer]:
            - generic [aria-hidden] [ref=f10e70]: ✓
          - button "Dark" [ref=f10e71] [cursor=pointer]
          - button "Neon Cyan" [ref=f10e74] [cursor=pointer]
          - button "Neon Emerald" [ref=f10e77] [cursor=pointer]
          - button "Neon Amber" [ref=f10e80] [cursor=pointer]
        - button "Use workspace default" [ref=f10e84] [cursor=pointer]
    - contentinfo [ref=f10e85]:
      - generic [ref=f10e86]: Assessment Training Tracker
      - generic [ref=f10e87]: Learning with purpose. Progress with evidence.
  - status [ref=f10e88]
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | import AxeBuilder from '@axe-core/playwright';
  3  | import { resetScenarioCache } from './isolated-cache.js';
  4  | 
  5  | test.beforeEach(resetScenarioCache);
  6  | const themes = { light: 'Light', dark: 'Dark', 'neon-cyan': 'Neon Cyan', 'neon-emerald': 'Neon Emerald', 'neon-amber': 'Neon Amber' };
  7  | async function login(page, name = /Alex/) {
  8  |     await page.goto('/login');
  9  |     await page.getByRole('button', { name }).click();
  10 |     await expect(page).toHaveURL(/dashboard/);
  11 | }
  12 | 
  13 | test('all five themes persist across pages and pass automated contrast checks', async ({ page }) => {
  14 |     test.setTimeout(180000);
  15 |     const errors = [];
  16 |     page.on('pageerror', error => errors.push(error.message));
  17 |     await login(page);
  18 |     await page.getByRole('link', { name: 'Appearance', exact: true }).click();
  19 |     for (const [id, name] of Object.entries(themes)) {
  20 |         await page.getByRole('group', { name: 'Workspace theme' }).getByRole('button', { name, exact: true }).click();
  21 |         await expect(page.locator('html')).toHaveAttribute('data-theme', id);
  22 |         await page.reload();
  23 |         await expect(page.locator('html')).toHaveAttribute('data-theme', id);
  24 |         await expect(page.getByRole('group', { name: 'Workspace theme' }).getByRole('button', { name, exact: true })).toHaveAttribute('aria-pressed', 'true');
  25 |         const audit = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
  26 |         expect(audit.violations).toEqual([]);
  27 |         await page.getByRole('link', { name: 'Overview', exact: true }).click();
  28 |         await expect(page.locator('html')).toHaveAttribute('data-theme', id);
  29 |         await page.screenshot({ path: 'test-results/dashboard-' + id + '.png', fullPage: true });
  30 |         await page.getByRole('link', { name: 'Appearance', exact: true }).click();
  31 |     }
  32 |     await page.getByRole('button', { name: 'Use workspace default' }).click();
  33 |     await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  34 |     expect(errors).toEqual([]);
  35 | });
  36 | 
  37 | test('preferences are isolated between identities and storage failure stays usable', async ({ page }) => {
  38 |     await login(page, /Sam/);
  39 |     await page.getByRole('link', { name: 'Appearance', exact: true }).click();
  40 |     await page.getByRole('group', { name: 'Workspace theme' }).getByRole('button', { name: 'Neon Cyan', exact: true }).click();
  41 |     await page.getByRole('button', { name: 'Sign out' }).click();
  42 |     await login(page, /Jordan/);
  43 |     await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  44 |     await page.getByRole('button', { name: 'Sign out' }).click();
  45 |     await login(page, /Sam/);
  46 |     await expect(page.locator('html')).toHaveAttribute('data-theme', 'neon-cyan');
  47 |     await page.addInitScript(() => { Object.defineProperty(window, 'localStorage', { get() { throw new Error('Storage unavailable'); } }); });
  48 |     await page.reload();
  49 |     await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
> 50 |     await page.getByRole('link', { name: 'Appearance', exact: true }).click();
     |                                                                       ^ TimeoutError: locator.click: Timeout 15000ms exceeded.
  51 |     await page.getByRole('group', { name: 'Workspace theme' }).getByRole('button', { name: 'Light', exact: true }).click();
  52 |     await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  53 |     await expect(page.locator('#appearance-status')).toContainText('Browser storage is unavailable');
  54 | });
  55 | 
  56 | test('mobile sidebar supports keyboard dismissal, navigation and narrow layouts', async ({ page }) => {
  57 |     await page.setViewportSize({ width: 390, height: 844 });
  58 |     await login(page, /Sam/);
  59 |     await expect(page.getByRole('navigation', { name: 'Main navigation' })).not.toBeVisible();
  60 |     const toggle = page.getByRole('button', { name: 'Open navigation', exact: true });
  61 |     await toggle.click();
  62 |     await expect(page.locator('#nav-toggle')).toHaveAttribute('aria-expanded', 'true');
  63 |     await page.keyboard.press('Escape');
  64 |     await expect(toggle).toBeFocused();
  65 |     await toggle.click();
  66 |     await page.getByRole('link', { name: 'Appearance', exact: true }).click();
  67 |     await expect(page.getByRole('heading', { name: 'Appearance', exact: true })).toBeVisible();
  68 |     await expect(page.locator('#nav-toggle')).toHaveAttribute('aria-expanded', 'false');
  69 |     await expect(page.locator('.theme-tile').first()).toHaveCSS('display', 'flex');
  70 |     await expect(page.locator('.theme-preview').first()).toHaveCSS('height', '72px');
  71 |     for (const width of [320, 390, 768]) {
  72 |         await page.setViewportSize({ width, height: 844 });
  73 |         expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  74 |     }
  75 |     await expect(page.locator('.secondary')).toHaveCSS('min-height', '38px');
  76 |     expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
  77 |     await page.screenshot({ path: 'test-results/appearance-mobile.png', fullPage: true });
  78 | });
  79 | 
  80 | test('admin can preview co-branding and white-label without saving changes', async ({ page }) => {
  81 |     await login(page);
  82 |     await page.getByRole('link', { name: 'Appearance', exact: true }).click();
  83 |     await page.getByLabel('Branding mode').selectOption('co-branded');
  84 |     await page.getByLabel('Client name').fill('Northstar');
  85 |     await page.getByLabel('Application name').fill('Northstar Academy');
  86 |     await expect(page.locator('[data-preview-brand]')).toHaveText('Northstar');
  87 |     await expect(page.locator('[data-preview-parent]')).toBeVisible();
  88 |     await page.getByLabel('Branding mode').selectOption('white-label');
  89 |     await expect(page.locator('[data-preview-parent]')).not.toBeVisible();
  90 |     await expect(page.locator('[data-preview-name]')).toHaveText('Northstar Academy');
  91 |     await expect(page.locator('.sidebar-top .brand-copy strong')).toHaveText('Cerebru');
  92 |     await page.screenshot({ path: 'test-results/white-label-preview.png', fullPage: true });
  93 |     await page.reload();
  94 |     await expect(page.getByLabel('Branding mode')).toHaveValue('cerebru');
  95 | });
  96 | 
```