import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {runInNewContext} from 'node:vm';

const tabsPath=fileURLToPath(new URL('../../js/settings-tabs.js',import.meta.url));
const permissionsPath=fileURLToPath(new URL('../../js/permission-settings.js',import.meta.url));
const tabsSource=readFileSync(tabsPath,'utf8');
const permissionsSource=readFileSync(permissionsPath,'utf8');

const element=(attributes={})=>({
    attributes:new Map(Object.entries(attributes)),listeners:{},hidden:false,focused:false,disabled:false,
    setAttribute(name,value){this.attributes.set(name,String(value));},getAttribute(name){return this.attributes.get(name)??null;},
    addEventListener(name,listener){this.listeners[name]=listener;},focus(){this.focused=true;},
});
const calendarTab=element({'aria-controls':'calendar','aria-selected':'true'});
const settingsTab=element({'aria-controls':'settings','aria-selected':'false'});
const calendarPanel=element();
const settingsPanel=element();
const settingsTarget=element();
calendarPanel.contains=()=>false;
settingsPanel.contains=node=>node===settingsTarget;
const tablist={querySelectorAll:()=>[calendarTab,settingsTab]};
const tabDocument={querySelector:selector=>selector==='[role="tablist"]'?tablist:null,getElementById:id=>({calendar:calendarPanel,settings:settingsPanel,'flz-room-full-access':settingsTarget}[id]??null)};
const tabsContext={window:{location:{hash:'#flz-room-full-access'}},document:tabDocument};
runInNewContext(tabsSource,tabsContext,{filename:tabsPath});
if(settingsTab.getAttribute('aria-selected')!=='true'||settingsPanel.hidden||!calendarPanel.hidden)throw new Error('Ein direkter Einstellungslink aktiviert den zugehörigen Tab nicht.');
let prevented=false;
settingsTab.listeners.keydown({key:'ArrowLeft',preventDefault:()=>{prevented=true;}});
if(!prevented||calendarTab.getAttribute('aria-selected')!=='true'||!calendarTab.focused)throw new Error('Einstellungstab ist nicht mit der Tastatur umschaltbar.');

const submit={disabled:false};
const matrixBody={children:[],replaceChildren(){this.children=[];},append(node){this.children.push(node);}};
const form={listeners:{},addEventListener(name,listener){this.listeners[name]=listener;},querySelector:selector=>selector==='[type="submit"]'?submit:matrixBody};
const status={attributes:new Map(),textContent:'',setAttribute(name,value){this.attributes.set(name,value);}};
const created=[];
const permissionDocument={
    getElementById:()=>null,
    createElement(tag){const node={tag,value:'',textContent:'',checked:false,disabled:false,dataset:{},children:[],attributes:new Map(),append(...items){this.children.push(...items);},setAttribute(name,value){this.attributes.set(name,String(value));}};created.push(node);return node;},
};
const calls=[];
const client={request:async(path,options)=>{calls.push([path,options]);return {assignments:{'room.read':['pfk'],'room.manage':[]}};}};
const permissionsContext={window:{OCA:{FlzRoom:{Shared:{api:{ApiClient:class{}}}}}},document:permissionDocument,Array,JSON,Set};
runInNewContext(permissionsSource,permissionsContext,{filename:permissionsPath});
const settings=new permissionsContext.window.FlzRoom.PermissionSettings({form,status,client,document:permissionDocument});
if(!submit.disabled)throw new Error('Die Rechtezuordnung ist vor dem vollständigen Laden des Organigrammkatalogs schreibbar.');
settings.render({
    status:'VALID',message:null,
    roles:[{roleKey:'pfk',label:'Pflegefachkraft'},{roleKey:'secretariat',label:'Sekretariat'}],
    permissions:[{permissionKey:'room.read',label:'Raumplan lesen',description:'Lesen'},{permissionKey:'room.manage',label:'Räume verwalten',description:'Verwalten'}],
    assignments:{'room.read':['pfk','secretariat'],'room.manage':['secretariat']},
});
const checkboxes=created.filter(node=>node.tag==='input');
if(checkboxes.length!==4||!checkboxes[0].checked||checkboxes[1].checked||!checkboxes[3].checked||checkboxes[0].attributes.get('aria-label')!=='Raumplan lesen: Pflegefachkraft')throw new Error('Semantische Rollen und Einzelrechte werden nicht als zugängliche Matrix dargestellt.');
checkboxes.forEach(box=>{box.checked=box.dataset.permissionKey==='room.manage'&&box.value==='pfk';});
await settings.save();
const body=JSON.parse(calls.at(-1)[1].body);
if(body.assignments['room.manage'].join(',')!=='pfk'||body.assignments['room.read'].length!==0)throw new Error('Die Matrix wird nicht als geschlossene semantische Rechtezuordnung gespeichert.');
settings.render({status:'INVALID',message:'Ungültig',roles:[],permissions:[],assignments:{}});
if(!submit.disabled||status.attributes.get('role')!=='alert')throw new Error('Ein ungültiger Snapshot sperrt die Rechtezuordnung nicht fail-closed.');

console.log('Filzmann Raumplaner settings controls smoke passed');
