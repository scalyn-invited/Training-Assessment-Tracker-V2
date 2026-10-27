import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import {spawn} from 'node:child_process';
import {demoEnvironment} from './realistic-demo.mjs';
const ports=[8000,8123,8124,8125,8129];
const env=demoEnvironment();
const manifest=JSON.parse(fs.readFileSync(path.join(env.TRAINING_DEMO_ROOT,'manifest.json'),'utf8'));
if(manifest.status!=='complete'||!fs.existsSync(env.DB_DATABASE))throw Error('Prepare the realistic demo first. This launcher never seeds or migrates.');
for(const port of ports) await new Promise((resolve,reject)=>{const probe=net.createServer();probe.once('error',()=>reject(Error('Port '+port+' is occupied; preserve its service and inspect before restarting.')));probe.listen(port,'127.0.0.1',()=>probe.close(resolve));});
const children=[];
let stopping=false;
function stop(){if(stopping)return;stopping=true;for(const child of children)child.kill();}
for(const port of ports){
 const child=spawn('php',['-d','extension=gd','-S','127.0.0.1:'+port,'-t','public','scripts/browser-router.php'],{env:{...env,APP_URL:'http://127.0.0.1:'+port,APP_CONFIG_CACHE:path.join(env.TRAINING_DEMO_ROOT,'config-'+port+'.php')},stdio:'inherit',windowsHide:true});
 children.push(child);child.on('error',error=>{console.error(error.message);process.exitCode=1;stop();});child.on('exit',code=>{if(!stopping){process.exitCode=code||1;stop();}});
 console.log('Shared fictional demo: http://127.0.0.1:'+port+'/login');
}
fs.writeFileSync(path.join(env.TRAINING_DEMO_ROOT,'all-ports-processes.json'),JSON.stringify({launcher:process.pid,ports,children:children.map(c=>c.pid)},null,2));
process.on('SIGINT',stop);process.on('SIGTERM',stop);
