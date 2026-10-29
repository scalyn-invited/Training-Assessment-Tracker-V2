import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import { browserEnv } from '../playwright.config.js';
const out='.runtime/m3-browser';fs.mkdirSync(out,{recursive:true});
const base='http://127.0.0.1:8127';const title='Browser synthetic assessment '+Date.now();
const server=spawn('php',['-S','127.0.0.1:8127','-t','public','scripts/browser-router.php'],{env:{...browserEnv,TRAINING_AUTH_MODE:'mock',APP_URL:base},windowsHide:true,stdio:'ignore'});
let browser;let pages=[];
const wait=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn){let last;for(let n=0;n<240;n++){try{if(await fn())return;}catch(e){last=e;}await wait(250);}throw last||new Error('Timed out');}
try{
 await until(async()=> (await fetch(base+'/login')).ok);
 browser=await chromium.launch({channel:'chrome',headless:true});
 const coordinatorContext=await browser.newContext({viewport:{width:1440,height:1000}}), memberContext=await browser.newContext({viewport:{width:390,height:844}});
 const coordinator=await coordinatorContext.newPage(), member=await memberContext.newPage();
 coordinator.setDefaultTimeout(60000);member.setDefaultTimeout(60000);coordinator.setDefaultNavigationTimeout(60000);member.setDefaultNavigationTimeout(60000);
 pages=[coordinator,member];const errors=[];for(const p of [coordinator,member])p.on('pageerror',e=>errors.push(e.message));
 async function login(p,name){const refresh=spawnSync('php',['scripts/m3-browser-fixture.php'],{env:{...browserEnv,TRAINING_AUTH_MODE:'mock',APP_URL:base},windowsHide:true,encoding:'utf8'});if(refresh.status!==0)throw new Error(refresh.stderr+refresh.stdout);console.log('Sign in', String(name));await p.goto(base+'/login');await p.getByRole('button',{name}).click();await p.waitForURL(/dashboard/, {timeout:60000});console.log('Signed in',String(name));}
 await login(coordinator,/Casey/);await coordinator.goto(base+'/assessments/definitions/new');
 await coordinator.getByLabel('Assessment title',{exact:true}).fill(title);
 await coordinator.locator('#prompt-0').fill('Explain your evidence checks.');
 for(let i=0;i<2;i++){await coordinator.locator('#category-'+i).fill(i?'Evidence':'Clarity');await coordinator.locator('#label-'+i).fill(i?'Verified checks':'Clear steps');await coordinator.locator('#guidance-'+i).fill('Use actual evidence to assess the work.');await coordinator.locator('#weight-'+i).fill(i?'0.6':'0.4');}
 await coordinator.getByRole('button',{name:'Save draft',exact:true}).click();await coordinator.getByRole('button',{name:'Publish assessment',exact:true}).waitFor();
 await coordinator.screenshot({path:out+'/definition-desktop.png'});
 await coordinator.getByRole('button',{name:'Publish assessment',exact:true}).click();console.log('Published');
 const row=coordinator.getByRole('row').filter({has:coordinator.getByRole('cell',{name:title,exact:true})});
 await row.getByRole('button',{name:'Assign to member',exact:true}).click();
 const assignmentDialog=coordinator.getByRole('dialog',{name:'Assign assessment',exact:true});
 await assignmentDialog.getByLabel('Assign to member',{exact:true}).selectOption({label:await assignmentDialog.locator('option').filter({hasText:'Sam'}).textContent()});
 await assignmentDialog.getByRole('button',{name:'Assign assessment',exact:true}).click();
 await coordinator.waitForURL(/assessments\/[0-9a-f-]+$/,{timeout:60000});const assignmentUrl=coordinator.url();console.log('Assigned',assignmentUrl);assert.match(assignmentUrl,/assessments\/[\w-]+$/);
 await login(member,/Sam/);await member.goto(assignmentUrl);
 await member.getByLabel('Explain your evidence checks.',{exact:true}).fill('I listed the inputs, checked each measurement and recorded the evidence for every step.');
 await until(async()=> (await member.locator('[data-assessment-draft] [role=status]').textContent()).includes('Response saved.'));
 await member.reload();assert.match(await member.getByLabel('Explain your evidence checks.',{exact:true}).inputValue(),/listed the inputs/);
 await member.route(assignmentUrl,route=>route.request().method()==='POST'?route.abort():route.continue());
 await member.getByLabel('Explain your evidence checks.',{exact:true}).fill('Retained offline response: I checked and recorded every measurement against the source.');
 await until(async()=> (await member.locator('[data-assessment-draft] [role=status]').textContent()).includes('Your text remains'));
 assert.match(await member.getByLabel('Explain your evidence checks.',{exact:true}).inputValue(),/Retained offline/);
 await member.unroute(assignmentUrl);await member.getByRole('button',{name:'Save response',exact:true}).click();
 await until(async()=> (await member.locator('[data-assessment-draft] [role=status]').textContent()).includes('Response saved.'));
 await member.evaluate(()=>window.scrollTo(0,0));await member.screenshot({path:out+'/response-mobile.png',fullPage:true});
 await member.getByRole('button',{name:'Submit assessment',exact:true}).click();await member.getByRole('heading',{name:'Submission history'}).waitFor();
 assert.equal(await member.getByRole('heading',{name:'Human review',exact:true}).count(),0);
 await coordinator.goto(assignmentUrl);await coordinator.getByLabel('Private decision rationale').fill('Please revise the evidence and resubmit.');
 await coordinator.getByRole('button',{name:'Reject and allow resubmission',exact:true}).click();
 await member.reload();await member.getByLabel('Explain your evidence checks.',{exact:true}).fill('Revised evidence: independently checked each measured result, recorded differences and repeated the procedure.');
 await until(async()=> (await member.locator('[data-assessment-draft] [role=status]').textContent()).includes('Response saved.'));
 await member.getByRole('button',{name:'Submit assessment',exact:true}).click();await member.getByText(/Attempt 2/).waitFor();
 await coordinator.goto(assignmentUrl);
 for(const [id,score] of [['c1','80'],['c2','90']]){await coordinator.locator('#score-'+id).fill(score);await coordinator.locator('#feedback-'+id).fill('The recorded evidence supports this score; continue improving repeatability.');await coordinator.locator('[name="scores['+id+'][evidence_ids][]"]').first().check();}
 await coordinator.getByLabel('Private decision rationale').fill('PRIVATE-BROWSER-REVIEW verified each source.');
 await coordinator.screenshot({path:out+'/review-desktop.png',fullPage:true});
 await coordinator.getByRole('button',{name:'Approve reviewed result',exact:true}).click();
 await member.reload();await member.getByRole('heading',{name:'Approved result',exact:true}).waitFor();
 assert.match(await member.locator('main').innerText(),/86/);assert.equal((await member.locator('main').innerText()).includes('PRIVATE-BROWSER-REVIEW'),false);
 assert.equal(await member.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 await member.keyboard.press('Tab');assert.equal(await member.evaluate(()=>document.activeElement===document.body),false);
 const axe=await new AxeBuilder({page:member}).analyze();const serious=axe.violations.filter(v=>['critical','serious'].includes(v.impact));
 fs.writeFileSync(out+'/accessibility.json',JSON.stringify(axe.violations,null,2));assert.equal(serious.length,0,JSON.stringify(serious.map(v=>v.id)));
 await member.evaluate(()=>{document.activeElement?.blur();window.scrollTo(0,0);});await member.screenshot({path:out+'/approved-mobile.png',fullPage:true});
 assert.deepEqual(errors,[]);
 fs.writeFileSync(out+'/result.json',JSON.stringify({passed:true,assignmentUrl,checks:['definition publication','member assignment','autosave and reload','network recovery','immutable receipt','rejection and resubmission','manual approved weighted total','private review hidden','mobile overflow','keyboard focus','axe serious/critical'],screenshots:4},null,2));
 console.log('M3 browser journey passed; screenshots and accessibility evidence saved.');
}catch(error){
 for(let i=0;i<pages.length;i++){try{await pages[i].screenshot({path:out+'/failure-'+i+'.png',fullPage:true});fs.writeFileSync(out+'/failure-'+i+'.html',await pages[i].content());console.log('Page',i,pages[i].url(),(await pages[i].locator('body').innerText()).slice(-1800));}catch{}}
 throw error;
}finally{if(browser)await browser.close();server.kill();}
