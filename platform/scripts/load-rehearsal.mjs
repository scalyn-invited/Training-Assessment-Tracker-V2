import fs from 'node:fs';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import http from 'node:http';
// Explicit connection closure avoids pooling against PHP's development HTTP server.
function fetch(url, options={}) {
    return new Promise((resolve,reject)=>{
        const request=http.request(url,{method:options.method??'GET',headers:{...options.headers,Connection:'close'}},response=>{
            const chunks=[];response.on('data',chunk=>chunks.push(chunk));response.on('end',()=>resolve({status:response.statusCode,ok:response.statusCode===200,
                headers:{getSetCookie:()=>response.headers['set-cookie']??[]},text:async()=>Buffer.concat(chunks).toString('utf8')}));response.on('error',reject);
        });request.setTimeout(30000,()=>request.destroy(new Error('Request timeout')));request.on('error',reject);if(options.body)request.write(options.body.toString());request.end();
    });
}
const root = path.resolve('.');
const env = {...process.env, APP_ENV:'local', APP_DEBUG:'false', DB_CONNECTION:'sqlite', DB_DATABASE:path.join(root,'.runtime/load.sqlite'),
    TRAINING_ENVIRONMENT:'test', MOCK_IDENTITY_ENABLED:'true', TRAINING_SMTP_ENABLED:'false', AI_LIVE_ENABLED:'false',
    SESSION_DRIVER:'database', CACHE_STORE:'array', QUEUE_CONNECTION:'database', MAIL_MAILER:'array', APP_URL:'http://127.0.0.1:8124'};
const setup=spawnSync('php',['scripts/load-prepare.php'],{env,encoding:'utf8'});
if(setup.status!==0) throw new Error(setup.stderr+setup.stdout);
const server=spawn('php',['-S','127.0.0.1:8124','-t','public','scripts/browser-router.php'],{env,stdio:'ignore',windowsHide:true});
const base='http://127.0.0.1:8124';
try {
    let ready=false;
    for(let i=0;i<100;i++){try{if((await fetch(base+'/login')).ok){ready=true;break;}}catch{} await new Promise(resolve=>setTimeout(resolve,100));}
    if(!ready) throw new Error('Isolated load server did not start');
    const jar={};
    function cookies(response){for(const raw of response.headers.getSetCookie()){const [pair]=raw.split(';');const at=pair.indexOf('=');jar[pair.slice(0,at)]=pair.slice(at+1);}}
    const cookie=()=>Object.entries(jar).map(([key,value])=>key+'='+value).join('; ');
    const login=await fetch(base+'/login');cookies(login);const html=await login.text();
    const csrf=html.match(/name="_token" value="([^"]+)"/)[1];
    const identity=html.match(/name="identity_id" value="([^"]+)"/)[1];
    const signed=await fetch(base+'/login',{method:'POST',redirect:'manual',headers:{Cookie:cookie(),'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({_token:csrf,identity_id:identity})});cookies(signed);
    if(signed.status!==302)throw new Error('Synthetic sign-in failed');
    const timings=[];let cursor=0;const failures=[];
    await Promise.all(Array.from({length:20},async()=>{while(cursor++<200){const start=performance.now();const response=await fetch(base+'/dashboard',{headers:{Cookie:cookie()},redirect:'manual'});await response.text();timings.push(performance.now()-start);if(response.status!==200)failures.push(response.status);}}));
    timings.sort((a,b)=>a-b);
    const report={kind:'local HTTP rehearsal',members:100,concurrent_clients:20,requests:timings.length,failures:failures.length,p50_ms:Math.round(timings[Math.ceil(timings.length*.5)-1]),p95_ms:Math.round(timings[Math.ceil(timings.length*.95)-1]),
        limitations:['PHP development server and SQLite; not deployment qualification','One synthetic authenticated administrator session','Client response timing includes queueing, rendering and body transfer; not isolated server p95','No production network, device, IdP, SMTP or live AI'],measured_at:new Date().toISOString()};
    fs.writeFileSync('.runtime/load-report.json',JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));
    if(failures.length)process.exitCode=1;
} finally {server.kill();}
