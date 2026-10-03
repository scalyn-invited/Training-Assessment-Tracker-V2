import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { browserEnv } from '../../playwright.config.js';
import { resetScenarioCache } from './isolated-cache.js';

test.beforeEach(resetScenarioCache);

async function login(page, name) {
    await page.goto('/login');
    await page.getByRole('button', { name }).click();
    await expect(page).toHaveURL(/dashboard/);
    await page.locator('.plan-card').first().click();
    await page.getByRole('link', { name: 'Onboarding and plan setup' }).click();
}

async function saved(page) {
    await expect(page.locator('form[data-autosave] .save-status')).toHaveText('Draft saved. This is not an approval.', { timeout: 15000 });
}

async function step(page, name) {
    await page.getByRole('navigation', { name: 'Onboarding steps' }).getByRole('link', { name }).click();
    await expect(page.locator('form').filter({ has: page.locator('.save-status') }).first()).toHaveAttribute('data-ready', 'true');
}

async function fields(page, values) {
    for (const [name, value] of Object.entries(values)) {
        await page.locator('[name="data[' + name + ']"]').fill(value);
    }
    await saved(page);
}

test('onboarding autosaves, survives reload, and retains input after network failure or stale save', async ({ page }) => {
    test.setTimeout(120000);
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, /Sam/);
    await step(page, /Profile and role/);
    await page.getByLabel('Current role', { exact: true }).fill('Operations draft saved from mobile');
    await saved(page);
    await page.reload();
    await expect(page.getByLabel('Current role', { exact: true })).toHaveValue('Operations draft saved from mobile');

    const endpoint = page.url().split('?')[0] + '/profile';
    await page.route(endpoint, route => route.abort());
    await page.getByLabel('Responsibilities', { exact: true }).fill('Keep this unsaved text after a network interruption');
    await expect(page.locator('.save-status')).toContainText('Your input has not been discarded.');
    await page.unroute(endpoint);
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await saved(page);
    await page.reload();
    await expect(page.getByLabel('Responsibilities', { exact: true })).toHaveValue('Keep this unsaved text after a network interruption');

    const version = await page.locator('[name=expected_version]').inputValue();
    const csrf = await page.locator('meta[name=csrf-token]').getAttribute('content');
    const response = await page.request.post(endpoint, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        data: { expected_version: Number(version), data: { current_role: 'Saved in another window' } },
    });
    expect(response.status()).toBe(200);
    await page.getByLabel('Current role', { exact: true }).fill('Keep my conflicting draft');
    await expect(page.locator('.save-status')).toContainText('changed in another window');
    await expect(page.getByLabel('Current role', { exact: true })).toHaveValue('Keep my conflicting draft');
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(244, 247, 245)');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    await page.screenshot({ path: 'test-results/onboarding-mobile.png', fullPage: true });
});

test('coordinator completes onboarding, writes every block and approves the exact curriculum version', async ({ page }) => {
    test.setTimeout(300000);
    await login(page, /Casey/);
    await step(page, /Profile and role/);
    await fields(page, { current_role: 'Operations specialist', responsibilities: 'Maintain team procedures', experience: 'One year', tools: 'Text editor', preferences: 'English, readable text' });
    await step(page, /Desired capability/);
    await fields(page, { target_role: 'Independent procedure author', purpose: 'Reduce missing steps', proficiency: 'Independent with peer review', success: 'A peer reproduces the task', prerequisites: 'Basic writing', competencies: 'Clear instructions\nEvidence-based review' });
    await step(page, /Assessment findings/);
    await fields(page, { source: 'Synthetic observation A', provider: 'Test observer', date: '2026-09-01', scale: 'Not scored', findings: 'Needs clearer prerequisites' });
    await page.getByLabel('Evidence type:').selectOption('observed evidence');
    await saved(page);
    await page.getByRole('button', { name: 'Confirm assessment findings' }).click();
    await saved(page);
    await expect(page.locator('#assessment-confirmation')).toContainText('Confirmed by coordinator');
    await step(page, /Capacity and calendar/);
    await page.getByLabel('Learning blocks', { exact: true }).selectOption('4');
    const start = new Date();
    start.setUTCDate(start.getUTCDate() + 14 + ((8 - start.getUTCDay()) % 7));
    await page.getByLabel('Start date', { exact: true }).fill(start.toISOString().slice(0, 10));
    await page.getByLabel('Minutes per day for this plan', { exact: true }).fill('30');
    await page.getByLabel('Shared daily capacity (all plans)', { exact: true }).fill('30');
    for (const day of ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']) {
        await page.getByLabel(day, { exact: true }).uncheck();
    }
    await page.getByLabel('Monday', { exact: true }).check();
    await saved(page);
    await step(page, /Measures of success/);
    for (let i = 0; i < 3; i++) {
        for (const [name, value] of Object.entries({ name: ['Clarity','Completeness','Accuracy'][i], baseline: 'unknown', target: '90', unit: 'percent', rationale: 'Reproducible work', evidence: 'Peer checklist', cadence: 'Weekly' })) {
            await page.locator('[name="data[kpis][' + i + '][' + name + ']"]').fill(value);
        }
    }
    await saved(page);
    await step(page, /Learning resources/);
    await fields(page, { title: 'Synthetic handbook', reference: 'Internal synthetic guide', licence: 'Organisation-owned test material', cost: 'No charge', access: 'Handbook and editor available', tasks: 'Write and peer review a procedure' });
    await step(page, /Review and create/);
    await page.getByLabel('Reason for confirming or changing the shared calendar').fill('Confirm the supplied synthetic availability.');
    await page.getByRole('button', { name: 'Confirm shared calendar' }).click();
    await expect(page.getByText('Shared capacity calendar confirmed.', { exact: true })).toBeVisible();
    await page.getByLabel('Draft creation or rescheduling reason').fill('Create the first complete synthetic curriculum.');
    await page.getByRole('button', { name: 'Create curriculum draft', exact: true }).click();
    await expect(page).toHaveURL(/curriculum/);

    for (let block = 0; block < 4; block++) {
        if (block) await page.getByRole('navigation', { name: 'Learning blocks' }).getByRole('link', { name: new RegExp('Block ' + (block + 1) + ' ·') }).click();
        await expect(page.locator('form[data-preserve-input]').first()).toHaveAttribute('data-ready', 'true');
        const versionBeforeEdit = Number(await page.locator('form[data-preserve-input]').first().locator('[name=expected_version]').inputValue());
        await page.getByLabel('Learning objective', { exact: true }).fill('Write a procedure with testable outcomes');
        await page.getByLabel('Prerequisites and sequence', { exact: true }).fill('Basic writing; review the preceding block before proceeding');
        await page.getByLabel('Criterion one and evidence expectations').fill('Complete ordered steps with inputs and outcomes');
        await page.getByLabel('Criterion two and evidence expectations').fill('A peer follows the steps and provides a completed checklist');
        for (const [name, value] of Object.entries({
            title: 'Procedure practice ' + (block + 1),
            explanation: 'A procedure names inputs, ordered actions and observable results. Remove assumptions that a new reader cannot verify.',
            example: 'To verify a backup, identify its timestamp, restore a test copy and compare the expected record count.',
            activity: 'Write a five-step procedure and ask a peer to follow it using synthetic data.',
            tools: 'Text editor and synthetic handbook',
            completion: 'Submit the procedure and a completed peer checklist.',
            reading: '5', practice: '15', assessment: '5', revision: '5',
        })) await page.locator('[name="data[lessons][0][' + name + ']"]').fill(value);
        await page.getByLabel('Reason for this version', { exact: true }).fill('Add reviewed lesson content for block ' + (block + 1));
        await page.getByRole('button', { name: 'Save new draft version' }).click();
        await expect(page.getByText('New draft version saved; review must be requested again.', { exact: true })).toBeVisible();
        await expect(page.locator('form[data-preserve-input]').first().locator('[name=expected_version]')).toHaveValue(String(versionBeforeEdit + 1));
    }
    await expect(page.locator('form[data-preserve-input]').last()).toHaveAttribute('data-ready', 'true');
    await page.getByRole('button', { name: 'Validate all blocks and request review' }).click();
    await expect(page.getByLabel('Approval rationale')).toBeVisible();
    await expect(page.locator('form[data-preserve-input]').last()).toHaveAttribute('data-ready', 'true');
    await page.getByLabel('Approval rationale').fill('All lessons, resource access, rubric weights and shared capacity reviewed.');
    await page.getByRole('button', { name: /Approve exact version/ }).click();
    await expect(page.getByText('Exact plan version approved. Enrolment is ready; training has not started.', { exact: true })).toBeVisible();
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(244, 247, 245)');
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    await page.screenshot({ path: 'test-results/curriculum-approved.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);

    await page.getByText('Generate or revise with AI', { exact: true }).click();
    await page.getByLabel('Revision notes (optional)').fill('Synthetic generation demonstration; keep the approved baseline available.');
    await page.getByRole('button', { name: 'Queue curriculum generation' }).click();
    await expect(page).toHaveURL(/generation\//);
    await expect(page.locator('#generation-progress')).toContainText('0 of 4 blocks complete');
    const worker = spawnSync('php', ['artisan', 'queue:work', '--queue=ai_generation', '--stop-when-empty', '--tries=1', '--timeout=120'], { env: browserEnv, encoding: 'utf8', timeout: 45000 });
    expect(worker.status, worker.stderr + worker.stdout).toBe(0);
    await expect(page.locator('#generation-progress')).toContainText('4 of 4 blocks complete', { timeout: 15000 });
    await expect(page.locator('#generation-cost')).toContainText('Reserved: 0');
    await expect(page.locator('#generation-attempts li')).toHaveCount(4);
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(244, 247, 245)');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    await page.screenshot({ path: 'test-results/generation-complete-mobile.png', fullPage: true });
    await page.getByRole('link', { name: 'Review generated draft' }).click();
    await expect(page.getByLabel('Learning objective', { exact: true })).toHaveValue(/Synthetic block 1/);
    await expect(page.locator('.badge').first()).toHaveText('Draft');
    await expect(page.getByRole('button', { name: 'Validate all blocks and request review' })).toBeVisible();
    await page.screenshot({ path: 'test-results/generated-draft.png', fullPage: true });

    await page.getByRole('button', { name: 'Validate all blocks and request review' }).click();
    await page.getByLabel('Approval rationale').fill('Review generated baseline before learner delivery.');
    await page.getByRole('button', { name: /Approve exact version/ }).click();
    await expect(page.getByText('Exact plan version approved. Enrolment is ready; training has not started.', { exact: true })).toBeVisible();
    const learnUrl = page.url().replace(/\/curriculum.*$/, '/learn');
    await page.goto(learnUrl);
    await page.getByLabel('Activation reason').fill('Activate complete synthetic baseline for browser rehearsal.');
    await page.getByRole('button', { name: 'Activate programme' }).click();
    const firstBlock = page.locator('section.panel').filter({ has: page.getByRole('heading', { name: /^Block 1/ }) });
    await firstBlock.getByText('Authorise early start', { exact: true }).click();
    await firstBlock.getByLabel('Early-start reason').fill('Authorise early synthetic practice for this learner.');
    await firstBlock.getByRole('button', { name: 'Authorise and lock block' }).click();
    const lessonUrl = await page.getByRole('link', { name: /Synthetic practice 1.*block 1/ }).getAttribute('href');
    await page.getByRole('button', { name: 'Sign out' }).click();
    await page.getByRole('button', { name: /Sam/ }).click();
    await page.goto(lessonUrl);
    await expect(page.locator('form[data-autosave]')).toHaveAttribute('data-ready', 'true');
    await page.getByLabel('Your response', { exact: true }).fill('Synthetic work: I checked the inputs, recorded each ordered step and observable result, then asked a peer to verify the completed checklist.');
    await expect(page.locator('.save-status')).toHaveText('Draft saved. Your work has not been submitted.');
    await page.reload();
    await expect(page.getByLabel('Your response', { exact: true })).toHaveValue(/Synthetic work/);
    await page.getByRole('button', { name: 'Submit saved work for review' }).click();
    await expect(page.getByRole('heading', { name: 'Submission receipt' })).toBeVisible();
    const receiptUrl = page.url();
    await expect(page.getByText('Awaiting review.', { exact: false })).toBeVisible();
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(244, 247, 245)');
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    await page.getByRole('button', { name: 'Sign out' }).click();
    await page.getByRole('button', { name: /Casey/ }).click();
    await page.goto(receiptUrl);
    await page.getByLabel('Grading or regrading notes').fill('Review the evidence against the pinned rubric.');
    await page.getByRole('button', { name: 'Request provisional grading' }).click();
    const grader = spawnSync('php', ['artisan', 'queue:work', '--queue=ai_grading', '--stop-when-empty', '--tries=1', '--timeout=120'], { env: browserEnv, encoding: 'utf8', timeout: 45000 });
    expect(grader.status, grader.stderr + grader.stdout).toBe(0);
    await page.reload();
    await page.getByLabel('Review rationale', { exact: true }).fill('Synthetic independent review supports the provisional evidence.');
    await page.getByRole('button', { name: 'Approve grade', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Approved feedback' })).toBeVisible();
    await page.goto(learnUrl.replace(/\/learn$/, '/progress'));
    await expect(page.getByRole('heading', { name: 'Progress and evidence' })).toBeVisible();
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(244, 247, 245)');
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/progress-mobile.png', fullPage: true });
    await page.getByRole('button', { name: 'Sign out' }).click();
    await page.getByRole('button', { name: /Sam/ }).click();
    await page.goto(receiptUrl);
    await expect(page.getByRole('heading', { name: 'Approved feedback' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Grading attempts' })).toHaveCount(0);
});

test('administrator versions task routing without exposing credentials', async ({ page }) => {
    await page.goto('/login');
    await page.getByRole('button', { name: /Alex/ }).click();
    await page.getByRole('link', { name: 'AI settings' }).click();
    await expect(page.getByRole('heading', { name: 'AI provider settings' })).toBeVisible();
    await page.getByRole('button', { name: 'Save generate programme route', exact: true }).click();
    await expect(page.getByText('New routing version saved. Existing runs retain their pinned configuration.')).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(244, 247, 245)');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
});
