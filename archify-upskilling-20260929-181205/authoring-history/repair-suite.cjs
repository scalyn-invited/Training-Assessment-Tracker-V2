const fs=require('fs'); const root=fs.readFileSync('.archify-current-run','utf8').replace(/^\uFEFF/,'').trim();
const round=process.argv[2]||'1';
for(let i=1;i<=11;i++){let id='D'+String(i).padStart(2,'0'),p=root+'/'+id+'/candidate.json',s=JSON.parse(fs.readFileSync(p));fs.copyFileSync(p,root+'/'+id+'/candidate-before-repair-'+round+'.json');
if(round==='2'){
 if(i<=3){s.components.forEach(n=>{n.pos[0]*=.73;n.size[0]=155;n.label=n.label.replace('Worker / scheduler','Worker + cron').replace('Public document root','Public root').replace('External integrations','Integrations').replace('Identity service VM','Identity VM').replace('Whole-app Proxmox','Proxmox move');n.sublabel=n.sublabel.replace('Member · coordinator · admin','Staff roles').replace('Authorised downloads only','Private downloads').replace('Directory + permitted answers','Directory / answers').replace('Task policy + provider adapters','Task adapters').replace('Same application codebase','Same codebase').replace('Application + queue + locks','State / queue / locks').replace('Outside public root','Private storage').replace('Shared authentik · assumed','authentik assumed').replace('Physical location unknown','Location unknown').replace('Separate service · OIDC + MFA','OIDC + MFA').replace('AI / primary API / SMTP','AI / primary / SMTP').replace('Finite runtime · same release','Finite runtime');});s.meta.viewBox=[1100,840];}
 if([4,5,10].includes(i)){
  s.nodes.forEach(n=>{n.width=130;n.label=n.label.replace('Coordinator review','Human review').replace('Review notification','Notify reviewer').replace('Approved feedback','Feedback').replace('Regrade / resubmit','New attempt').replace('Technical failure','Job failure').replace('Activate baseline','Activate').replace('Confirm evidence','Confirm input'); if(n.sublabel){n.sublabel=n.sublabel.replace('Coordinator extraction review','Confirm extraction').replace('Queued AI · whole programme','Whole programme').replace('Resources · rubric · minutes','Content policy').replace('Invalid input or AI failure','Correct input').replace('Edit · revise · approve version','Exact version').replace('Lessons · tasks · resources','Usable lessons').replace('Immutable receipt + hashes','Receipt + hashes').replace('Quiz key or provisional AI','Quiz / AI draft').replace('Rubric / evidence / arithmetic','Evidence checks').replace('Assigned coordinator','Group reviewer').replace('KPI + adaptation + outbox','KPI / outbox').replace('Separate attempts','New attempt IDs').replace('Preserve attempt · bounded retry','Bounded retry').replace('SMTP after provisional save','Queued SMTP').replace('Snapshot grade / KPI versions','Approved versions').replace('Within programme duration','Within duration').replace('Before cutoff · no activity','No activity').replace('Within capacity / target','Within budget').replace('Calendar / schema / prerequisites','Policy checks').replace('Diff + reason + evidence','Review diff').replace('Atomic version / access check','Atomic recheck').replace('Keep old version / event','Revision notice').replace('Select later eligible target','Later target').replace('No target → follow-on proposal','Follow-on proposal').replace('Version future calendar','Future calendar');}
 });
 }
 if(i===10){let e=s.edges.find(e=>e.from==='publish'&&e.to==='later');delete e.route;e.bias=.8;}
 if([7,11].includes(i))s.flows.forEach(e=>{if(e.labelAt && e.from!=='mapping')e.labelAt[1]-=18});
 if(i===8){let e=s.transitions.find(e=>e.from==='paused');e.via=[[322,310],[322,158]];e.labelAt=[306,240];}
 if(i===9){
  Object.assign(s.transitions.find(e=>e.to==='changes-requested'),{fromSide:'top',toSide:'top',via:[[710,75],[470,75],[470,235],[402,235]],labelAt:[500,63]});
  Object.assign(s.transitions.find(e=>e.from==='technical-failure'),{via:[[632,410],[632,158]],labelAt:[628,360]});
  Object.assign(s.transitions.find(e=>e.to==='withdrawn'),{fromSide:'left',toSide:'left',via:[[320,158],[320,482]],labelAt:[302,380]});
 }
}
if(round==='1'){
 if(i<=3){s.connections.find(e=>e.from==='database'&&e.to==='worker').labelDy=70; if(i===1)s.connections.find(e=>e.label==='SQL / enqueue').labelDy=35;}
 if(i===4)s.nodes.forEach(n=>{n.width=145;n.height=64});
 if(i===6){s.meta.viewBox=[1050,1020];s.participants.forEach(n=>{n.sublabel={'user':'Own / assigned','primary-platform':'Scoped tools','identity':'OIDC / MFA','training-api':'Server policy','policy-store':'Approved facts'}[n.id]});s.participants.find(n=>n.id==='policy-store').label='Data / policy';}
 if([7,11].includes(i)){
  s.meta.viewBox=[1080,780];s.nodes.forEach(n=>{n.width=142;n.sublabel=n.sublabel.replace(/ \+ /g,' / ')});
  s.flows.forEach(e=>{e.classification=i===7?'Scoped PII':'Evidence';const a=s.nodes.find(n=>n.id===e.from),b=s.nodes.find(n=>n.id===e.to);if(a.row===b.row)e.labelAt=[100+(a.stage+b.stage)*107.5,117+a.row*114];});
  if(i===7){s.flows.find(e=>e.from==='mapping').labelAt=[530,280];let e=s.flows.find(e=>e.from==='local-person'&&e.to==='member-directory');Object.assign(e,{fromSide:'top',toSide:'left',via:[[100,250],[400,250],[400,162]]});}
  if(i===11)s.flows.find(e=>e.to==='suggestions').labelDy=75;
 }
 if([8,9].includes(i)){
  s.meta.viewBox=[880,700];s.states.forEach(n=>{n.width=116;n.height=64;});
  s.transitions.filter(e=>s.states.find(n=>n.id===e.from).lane==='main'&&s.states.find(n=>n.id===e.to).lane==='main').forEach(e=>{let a=s.states.find(n=>n.id===e.from),b=s.states.find(n=>n.id===e.to);e.labelAt=[94+(a.col+b.col)*77,112]});
  if(i===8){s.states.find(n=>n.id==='cancelled').col=1;Object.assign(s.transitions.find(e=>e.from==='active'&&e.to==='paused'),{route:'straight',labelAt:[385,230]});Object.assign(s.transitions.find(e=>e.from==='paused'),{fromSide:'left',toSide:'left',via:[[300,310],[300,158]],labelAt:[285,232]});}
  if(i===9){s.states.find(n=>n.id==='changes-requested').col=0;s.states.find(n=>n.id==='technical-failure').yOffset=100;s.transitions.filter(e=>e.to==='approved'||e.to==='withdrawn').forEach(e=>e.labelDy=40);s.transitions.find(e=>e.to==='technical-failure').labelDy=65;Object.assign(s.transitions.find(e=>e.from==='technical-failure'),{fromSide:'right',toSide:'right',via:[[655,410],[655,158]],labelAt:[650,340]});}
 }
 if(i===10){let e=s.edges.find(e=>e.from==='publish'&&e.to==='later');e.route='bottom-channel';}
}
fs.writeFileSync(p,JSON.stringify(s,null,2)+'\n');}
