import base, {automaticEnv} from './playwright.automatic-theme.config.js';
import path from 'node:path';
process.env.TRAINING_PREFERENCES_REHEARSAL='1';
export const preferenceEnv={...automaticEnv,DB_DATABASE:path.resolve('.runtime/preferences/browser.sqlite'),APP_CONFIG_CACHE:path.resolve('.runtime/preferences/config.php'),APP_URL:'http://127.0.0.1:8128'};
export default {...base,outputDir:'.runtime/preferences/results',reporter:[['list'],['json',{outputFile:'.runtime/preferences/browser.json'}]],use:{...base.use,baseURL:'http://127.0.0.1:8128'},webServer:{...base.webServer,command:'php -d extension=gd -S 127.0.0.1:8128 -t public scripts/browser-router.php',url:'http://127.0.0.1:8128/login',env:preferenceEnv}};
