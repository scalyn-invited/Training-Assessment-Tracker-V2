import base from './playwright.config.js';
import {demoEnvironment} from './scripts/realistic-demo.mjs';
export const demoEnv={...demoEnvironment('realistic-demo-browser'),APP_URL:'http://127.0.0.1:8130'};
export default {...base,testIgnore:[],testMatch:/realistic-demo.spec.js/,timeout:120000,outputDir:'.runtime/realistic-demo-evidence/browser-results',reporter:[['list'],['json',{outputFile:'.runtime/realistic-demo-evidence/browser.json'}]],use:{...base.use,baseURL:'http://127.0.0.1:8130'},webServer:{...base.webServer,command:'php -d extension=gd -S 127.0.0.1:8130 -t public scripts/browser-router.php',url:'http://127.0.0.1:8130/login',env:demoEnv}};
