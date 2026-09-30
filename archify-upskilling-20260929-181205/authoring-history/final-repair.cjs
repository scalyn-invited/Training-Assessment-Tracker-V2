const fs=require('fs');const r=fs.readFileSync('.archify-current-run','utf8').replace(/^\uFEFF/,'').trim();
for(const id of ['D04','D05','D08','D09','D10']){let p=r+'/'+id+'/candidate.json',s=JSON.parse(fs.readFileSync(p));fs.copyFileSync(p,r+'/'+id+'/candidate-before-evidence-retry.json');
if(id==='D08')s.transitions.find(e=>e.from==='paused').labelAt=[280,260];
if(id==='D09'){let e=s.transitions.find(e=>e.to==='changes-requested');Object.assign(e,{fromSide:'right',toSide:'bottom',via:[[812,158],[812,365],[402,365]],labelAt:[770,360]});}
if(id==='D04'){s.nodes.find(n=>n.id==='learn').col=4;s.nodes.find(n=>n.id==='learn').lane='intake';s.edges.find(e=>e.to==='activate').label='Approve version';s.edges.find(e=>e.to==='learn').label='Deliver';s.nodes.forEach(n=>{n.width=120;n.label=n.label.replace('Resumable wizard','Wizard').replace('Member delivery','Lessons')});}
if(id==='D05'){s.nodes.find(n=>n.id==='publish').lane='app';s.nodes.find(n=>n.id==='publish').col=3;s.nodes.find(n=>n.id==='feedback').col=3;s.nodes.find(n=>n.id==='notify').col=4;s.edges.find(e=>e.from==='validate'&&e.to==='review').label='Save';s.edges.find(e=>e.to==='publish').label='Commit';s.edges.find(e=>e.to==='feedback').label='Feedback';}
if(id==='D10'){let e=s.edges.find(e=>e.from==='publish'&&e.to==='later');delete e.bias;Object.assign(e,{fromSide:'bottom',toSide:'right'});}
fs.writeFileSync(p,JSON.stringify(s,null,2)+'\n');}
