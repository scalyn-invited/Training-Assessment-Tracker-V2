const fs=require('fs'),path=require('path'),{spawn,spawnSync}=require('child_process');
const root=path.resolve('.runtime/platform-selection');
const env={...process.env,APP_ENV:'local',APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:(root+'/test.sqlite').replaceAll('\\','/'),TRAINING_ENVIRONMENT:'test',TRAINING_AUTH_MODE:'local_password',TRAINING_LOCALHOST_PRODUCTION:'false',MOCK_IDENTITY_ENABLED:'true',TRAINING_SMTP_ENABLED:'false',LOCAL_ACCOUNT_MAIL_ENABLED:'false',AI_LIVE_ENABLED:'false',MAIL_MAILER:'array',QUEUE_CONNECTION:'database',SESSION_DRIVER:'database',CACHE_STORE:'database',APP_CONFIG_CACHE:root+'/config.php',APP_URL:'http://127.0.0.1:8136',VIEW_COMPILED_PATH:root+'/views'};
fs.mkdirSync(root+'/views',{recursive:true});fs.writeFileSync(root+'/test.sqlite','');
let fixture=spawnSync('php',['-d','extension=gd',root+'/fixture.php'],{env,stdio:'inherit'});if(fixture.status)process.exit(fixture.status);
const server=spawn('php',['-d','extension=gd','-S','127.0.0.1:8136','-t','public','scripts/browser-router.php'],{env,windowsHide:true,stdio:['ignore',fs.openSync(root+'/server.log','a'),fs.openSync(root+'/server.log','a')]});
console.log('Selection browser server PID '+server.pid);process.on('SIGINT',()=>{server.kill();process.exit();});
