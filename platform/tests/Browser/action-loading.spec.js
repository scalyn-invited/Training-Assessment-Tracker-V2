import {test,expect} from '@playwright/test';
import fs from 'node:fs';

test.beforeEach(async({page})=>{
 await page.route('**/__loading_verify.html',route=>route.fulfill({
  contentType:'text/html',body:fs.readFileSync('tests/Browser/fixtures/action-loading.html','utf8')
 }));
 await page.goto('/__loading_verify.html');
});
test('loading lifecycle restores controls and accessible content after fast completion',async({page})=>{
 await page.getByRole('button',{name:'Run lifecycle checks',exact:true}).click();
 await expect(page.getByRole('status')).toContainText('PASS: 11 lifecycle assertions');
 await expect(page.getByRole('button',{name:'Run lifecycle checks',exact:true})).toBeVisible();
 await expect(page.locator('main')).not.toHaveAttribute('aria-hidden','true');
 await page.getByRole('button',{name:'Sign in',exact:true}).click();
 await expect(page.locator('.loading-host')).not.toBeVisible();
 await page.getByLabel('Required value').fill('preserved fixture');
 await page.getByRole('button',{name:'Sign in',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Logging in...',exact:true})).toBeVisible();
 await expect(page.locator('#login button')).toHaveText('Sign in');
 await expect(page.getByRole('status')).toContainText('Simulated network failure',{timeout:15000});
 await expect(page.locator('.loading-host')).not.toBeVisible();
 await expect(page.getByLabel('Required value')).toHaveValue('preserved fixture');
 await expect(page.locator('#login button')).not.toHaveAttribute('aria-disabled');
});
test('nested mobile popup handles delayed response without cancelling or duplicating submission',async({page})=>{
 await page.setViewportSize({width:375,height:812});
 await page.getByRole('button',{name:'Open management fixture',exact:true}).click();
 await page.getByRole('button',{name:'Suspend client',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Suspending client...',exact:true})).toBeVisible();
 await page.keyboard.press('Escape');
 await expect(page.locator('.loading-host')).toBeVisible();
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
 await expect(page.getByRole('button',{name:'Hide progress',exact:true})).toBeVisible({timeout:35000});
 await page.getByRole('button',{name:'Hide progress',exact:true}).click();
 await expect(page.locator('.loading-host')).not.toBeVisible();
 await expect(page.locator('#management')).toBeVisible();
 await expect(page.locator('#management button[name=action]')).toHaveAttribute('aria-disabled','true');
 await expect(page.getByRole('status')).toContainText('Slow request completed.',{timeout:20000});
 await expect(page.locator('#management button[name=action]')).not.toHaveAttribute('aria-disabled');
 await expect(page.locator('#management button[name=action]')).toBeFocused();
});
