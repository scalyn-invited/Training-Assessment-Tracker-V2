import fs from 'node:fs';
import path from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
export function demoEnvironment(name='realistic-demo') {
 if(!/^realistic-demo(?:-[a-z0-9]+)?$/.test(name)) throw Error('Use a dedicated realistic-demo name');
 const root=path.resolve('.runtime',name);
 return {...process.env,APP_ENV:'local',APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:path.join(root,'demo.sqlite'),APP_CONFIG_CACHE:path.join(root,'config.php'),APP_URL:'http://127.0.0.1:8129',TRAINING_DEMO_ENABLED:'true',TRAINING_DEMO_DATABASE:path.join(root,'demo.sqlite'),TRAINING_DEMO_ROOT:root,TRAINING_PRIVATE_STORAGE:path.join(root,'private'),TRAINING_ENVIRONMENT:'test',MOCK_IDENTITY_ENABLED:'true',AI_LIVE_ENABLED:'false',TRAINING_SMTP_ENABLED:'false',MAIL_MAILER:'array',QUEUE_CONNECTION:'database',CACHE_STORE:'database',SESSION_DRIVER:'database',SESSION_COOKIE:'training_realistic_demo'};
}
if(process.argv[1] && path.resolve(process.argv[1])===path.resolve('scripts/realistic-demo.mjs')) {
 const name=process.argv.find(a=>a.startsWith('--name='))?.slice(7)||'realistic-demo'; const env=demoEnvironment(name);
 const run=args=>{const r=spawnSync('php',args,{env,stdio:'inherit',windowsHide:true});if(r.status!==0)process.exit(r.status||1);};
 if(process.argv.includes('--prepare')){
  if(fs.existsSync(env.DB_DATABASE))throw Error('Preserve existing database. Use a fresh demo name.');
  fs.mkdirSync(env.TRAINING_PRIVATE_STORAGE,{recursive:true});fs.writeFileSync(env.DB_DATABASE,'');
  run(['artisan','migrate','--force','--no-interaction']);run(['artisan','db:seed','--class=RealisticDemoSeeder','--force','--no-interaction']);
 }else if(process.argv.includes('--seed')) run(['artisan','db:seed','--class=RealisticDemoSeeder','--force','--no-interaction']);
 else {
  const manifest=JSON.parse(fs.readFileSync(path.join(env.TRAINING_DEMO_ROOT,'manifest.json'),'utf8'));
  if(manifest.status!=='complete')throw Error('Demo is not complete');
  console.log('Fictional training demo: '+env.APP_URL+'/login');
  const server=spawn('php',['-d','extension=gd','-S','127.0.0.1:8129','-t','public','scripts/browser-router.php'],{env,stdio:'inherit',windowsHide:true});
  server.on('exit',code=>process.exitCode=code??1);process.on('SIGINT',()=>server.kill());process.on('SIGTERM',()=>server.kill());
 }
}
