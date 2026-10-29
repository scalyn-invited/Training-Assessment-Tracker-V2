import {test,expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import {DatabaseSync} from 'node:sqlite';
import {spawnSync} from 'node:child_process';
import {demoEnv} from '../../playwright.demo.config.js';
const manifest=JSON.parse(fs.readFileSync('.runtime/realistic-demo-browser/manifest.json','utf8'));
const db=new DatabaseSync(demoEnv.DB_DATABASE,{readOnly:true});
const person=(c,id)=>c.people.find(p=>p.id===id);
async function login(page,name){await page.goto('/login');await page.getByRole('button',{name,exact:false}).click();await page.waitForURL(/dashboard|clients/);}
async function logout(page){await page.getByRole('button',{name:'Sign out',exact:true}).click();}

test('Global Admin workspace is accessible without client selection and denied to Client Admins',async({page})=>{
 await page.goto('/login');
 await page.getByRole('button',{name:'Alex Rowan — Fictional Global Admin',exact:true}).click();
 await expect(page).toHaveURL(/\/platform$/);
 for(const width of [1440,390]) {
  await page.setViewportSize({width,height:900});
  for(const section of ['','/clients','/admins','/appearance','/audit']) {
   await page.goto('/platform'+section);
   await expect(page.getByRole('heading',{name:'Platform administration',exact:true})).toBeVisible();
   expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
   expect(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)).toBe(false);
  }
 }
 await page.getByRole('button',{name:'Open navigation',exact:true}).click();
 await page.keyboard.press('Escape');
 await expect(page.getByRole('button',{name:'Open navigation',exact:true})).toBeFocused();
 await page.screenshot({path:'test-results/global-admin-mobile.png',fullPage:true});
 await logout(page);
 await login(page,manifest.clients[0].people[0].name);
 expect((await page.request.get('/platform')).status()).toBe(403);
 await expect(page.getByRole('link',{name:'Platform administration',exact:true})).toHaveCount(0);
});
test.beforeEach(()=>{const r=spawnSync('php',['artisan','cache:clear'],{env:demoEnv,encoding:'utf8'});if(r.status!==0)throw Error(r.stderr+r.stdout);});
test('three client administrators see their own directory and branding',async({page})=>{
 for(const c of manifest.clients){
  await login(page,c.people[0].name);
  await page.goto('/people');await expect(page.locator('body')).toContainText(c.people[1].name);
  await expect(page.locator('body')).not.toContainText(manifest.clients[(manifest.clients.indexOf(c)+1)%3].people[0].name);
  await page.goto('/settings/appearance');await expect(page.getByLabel('Client name',{exact:true})).toHaveValue(c.name);
  expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await logout(page);
 }
});
test('coordinator summary, sensitive restrictions and case-specific appeal access',async({page})=>{
 const c=manifest.clients[0],scenario=c.scenarios.submitted;
 await login(page,person(c,scenario.primary).name);
 await page.goto('/enrolments/'+scenario.enrolment);await expect(page.locator('body')).toContainText('Customer communication');
 const file=db.prepare('select id from evidence_files where enrolment_id=? and sensitive=1').get(scenario.enrolment);
 expect((await page.request.get('/files/'+file.id)).status()).toBe(404);
 const ap=db.prepare('select reviewer_id from appeals where id=?').get(c.scenarios.appeal.appeal);
 if(ap.reviewer_id!==scenario.primary){await logout(page);await login(page,person(c,ap.reviewer_id).name);}
 await page.goto('/submissions/'+c.scenarios.appeal.submission);await expect(page.locator('body')).toContainText('Synthetic');
 const privateNote=db.prepare('select id from evidence_files where enrolment_id=? and sensitive=1').get(c.scenarios.appeal.enrolment);
 expect((await page.request.get('/files/'+privateNote.id)).status()).toBe(404);
 await page.goto('/reviews');await page.screenshot({path:'test-results/demo-review-desktop.png',fullPage:true});
});
test('member learning, completed progress, evidence, multi-client and departure',async({page})=>{
 const c=manifest.clients[0],done=c.scenarios.completed;
 await login(page,person(c,done.member).name);
 await page.goto('/enrolments/'+done.enrolment+'/progress');await expect(page.locator('body')).toContainText('92');
 await page.screenshot({path:'test-results/demo-progress-desktop.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();
 expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
 await page.screenshot({path:'test-results/demo-progress-mobile.png',fullPage:true});
 const own=db.prepare('select id from evidence_files where enrolment_id=? and sensitive=0').get(done.enrolment);
 expect((await page.request.get('/files/'+own.id)).status()).toBe(200);
 const privateNote=db.prepare('select id from evidence_files where enrolment_id=? and sensitive=1').get(done.enrolment);
 expect((await page.request.get('/files/'+privateNote.id)).status()).toBe(404);
 await logout(page);await login(page,c.people[3].name);await expect(page).toHaveURL(/clients/);await expect(page.locator('body')).toContainText(manifest.clients[1].name);
 await logout(page);const departed=manifest.clients[2];await login(page,person(departed,departed.departed_member).name);
 const denied=await page.request.get('/enrolments/'+departed.scenarios.onboarding.enrolment,{maxRedirects:0});expect([302,403,404]).toContain(denied.status());
});

test('platform palettes persist, reset and remain isolated from client branding',async({page,browser})=>{
 await page.goto('/login');
 await page.getByRole('button',{name:'Alex Rowan — Fictional Global Admin',exact:true}).click();
 await page.goto('/platform/appearance');
 const form=page.locator('#platform-appearance-form');
 const original={accent:await form.getByLabel('Brand colour',{exact:true}).inputValue(),theme:await form.getByRole('combobox',{name:'Default theme',exact:true}).inputValue()};
 const clientBrandings=db.prepare('select * from client_brandings order by organisation_id').all();
 const palette=()=>page.evaluate(()=>{
  const root=document.documentElement,css=getComputedStyle(root);
  return {theme:root.dataset.theme,key:root.dataset.themeKey,primary:css.getPropertyValue('--primary').trim(),
   background:getComputedStyle(document.body).backgroundColor,
   button:getComputedStyle(document.querySelector('#platform-appearance-form button')).backgroundColor,
   nav:getComputedStyle(document.querySelector('.sidebar-nav a[aria-current]')).backgroundColor};
 });
 const save=async(accent,theme)=>{
  await form.getByLabel('Brand colour',{exact:true}).fill(accent);
  await form.getByRole('combobox',{name:'Default theme',exact:true}).selectOption(theme);
  await form.getByLabel('Appearance change reason',{exact:true}).fill('Synthetic CI platform palette verification');
  await form.getByRole('button',{name:'Save platform appearance',exact:true}).click();
  await expect(page.locator('body')).toContainText('Platform appearance saved.');
  await page.mouse.move(0,0);
 };
 const choose=async(name)=>{
  await page.locator('.theme-picker summary').click();
  await page.getByRole('button',{name,exact:true}).click();
 };
 try {
  await page.setViewportSize({width:1440,height:1000});
  const before=await palette();
  await form.getByLabel('Brand colour',{exact:true}).fill('#cc3300');
  await expect.poll(()=>page.locator('[data-platform-preview="light"]').evaluate(el=>el.style.getPropertyValue('--preview-primary'))).toBe('#cc3300');
  expect((await palette()).primary).toBe(before.primary);
  await page.reload();
  await expect(form.getByLabel('Brand colour',{exact:true})).toHaveValue(original.accent);
  await save('#cc3300','platform-light');
  await expect(page.locator('html')).toHaveAttribute('data-theme','platform-light');
  const orange=await palette();expect(orange.primary).toBe('#cc3300');expect(orange.button).toBe(orange.nav);
  await page.reload();expect((await palette()).primary).toBe(orange.primary);
  await save('#0066cc','platform-light');
  const blue=await palette();expect(blue.primary).toBe('#0066cc');expect(blue.background).not.toBe(orange.background);
  expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await choose('Platform Dark');await page.reload();await expect(page.locator('html')).toHaveAttribute('data-theme','platform-dark');
  expect((await palette()).background).not.toBe(blue.background);
  await choose('Use platform default');await page.reload();await expect(page.locator('html')).toHaveAttribute('data-theme','platform-light');
  for(const [name,primary] of [['Light','#1d4668'],['Dark','#e5e5e5'],['Neon Cyan','#67e8f9']]) {
   await choose(name);expect((await palette()).primary).toBe(primary);
  }
  await choose('Use platform default');await save('#0066cc','platform-dark');
  await page.screenshot({path:'test-results/platform-theme-dark-desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await page.screenshot({path:'test-results/platform-theme-dark-mobile.png',fullPage:true});
  const guest=await browser.newContext();
  try {const login=await guest.newPage();await login.goto('http://127.0.0.1:8130/login');await expect(login.locator('html')).toHaveAttribute('data-theme','platform-dark');} finally {await guest.close();}
  expect(db.prepare('select * from client_brandings order by organisation_id').all()).toEqual(clientBrandings);
  await page.setViewportSize({width:1440,height:1000});
  await page.evaluate(()=>{const r=document.documentElement;localStorage.setItem(r.dataset.legacyThemeKey,'light');localStorage.removeItem(r.dataset.themeKey);localStorage.removeItem(r.dataset.themeKey+'.migrated');});
  await page.reload();await expect(page.locator('html')).toHaveAttribute('data-theme','light');
  await choose('Use platform default');await page.reload();await expect(page.locator('html')).toHaveAttribute('data-theme','platform-dark');
 } finally {
  await page.setViewportSize({width:1440,height:1000});
  await page.goto('/platform/appearance');
  await save(original.accent,original.theme);
 }
});
