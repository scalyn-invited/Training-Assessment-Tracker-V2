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
