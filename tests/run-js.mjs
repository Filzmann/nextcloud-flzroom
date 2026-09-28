import {execFileSync} from 'node:child_process';
import {readdirSync} from 'node:fs';

for(const directory of ['models','repositories','components']) for(const file of readdirSync(new URL(`../js/${directory}`,import.meta.url))) execFileSync('node',['--check',new URL(`../js/${directory}/${file}`,import.meta.url).pathname],{stdio:'inherit'});
execFileSync('node',['--check',new URL('../js/main.js',import.meta.url).pathname],{stdio:'inherit'});
execFileSync('node',['--check',new URL('../js/admin.js',import.meta.url).pathname],{stdio:'inherit'});
execFileSync('node',['--check',new URL('../js/admin-access.js',import.meta.url).pathname],{stdio:'inherit'});
execFileSync('node',['--check',new URL('../js/organization-groups.js',import.meta.url).pathname],{stdio:'inherit'});
execFileSync('node',['--check',new URL('../js/retention-policy.js',import.meta.url).pathname],{stdio:'inherit'});
execFileSync('node',[new URL('./js/frontend-smoke.mjs',import.meta.url).pathname],{stdio:'inherit'});
console.log('AD Raumplaner JavaScript tests passed');
