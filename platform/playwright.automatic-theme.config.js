import base, {themeEnv} from './playwright.client-theme.config.js';
import path from 'node:path';
process.env.TRAINING_AUTOMATIC_THEME_REHEARSAL = '1';
export const automaticEnv = {...themeEnv, DB_DATABASE:path.resolve('.runtime/automatic-theme/browser.sqlite'), APP_CONFIG_CACHE:path.resolve('.runtime/automatic-theme/config.php'), APP_URL:'http://127.0.0.1:8127'};
export default {...base, outputDir:'.runtime/automatic-theme/results', reporter:[['list'],['json',{outputFile:'.runtime/automatic-theme/browser.json'}]], use:{...base.use,baseURL:'http://127.0.0.1:8127'}, webServer:{...base.webServer, command:'php -d extension=gd -S 127.0.0.1:8127 -t public scripts/browser-router.php',url:'http://127.0.0.1:8127/login',env:automaticEnv}};
