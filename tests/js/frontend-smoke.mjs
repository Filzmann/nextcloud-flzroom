import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {runInNewContext} from 'node:vm';

const calendarSource=readFileSync(new URL('../../js/components/month-calendar.js',import.meta.url),'utf8');
const dialogSource=readFileSync(new URL('../../js/components/booking-dialog.js',import.meta.url),'utf8');
const wallTimeSource=readFileSync(new URL('../../js/modules/booking-wall-time.js',import.meta.url),'utf8');
const timelineSource=readFileSync(new URL('../../js/modules/booking-timeline.js',import.meta.url),'utf8');
const workflowSource=readFileSync(new URL('../../js/modules/booking-workflow.js',import.meta.url),'utf8');
const roomWorkflowSource=readFileSync(new URL('../../js/modules/room-workflow.js',import.meta.url),'utf8');
const adminTemplate=readFileSync(new URL('../../templates/admin.php',import.meta.url),'utf8');
const sources=['models/room.js','models/booking.js','repositories/room-repository.js','components/booking-dialog.js','components/room-settings.js','main.js','admin.js'].map((file)=>readFileSync(new URL(`../../js/${file}`,import.meta.url),'utf8')).join('\n')+calendarSource+wallTimeSource+timelineSource+workflowSource+roomWorkflowSource;
for(const contract of ['class Room extends BaseModel','class Booking extends BaseModel','class RoomRepository extends BaseRepository','this.post(\'/api/bookings\'','class MonthCalendar','class BookingDialog','class BookingWorkflow','class RoomSettings','class RoomWorkflow','adroom:add-booking','adr-admin-room-body','canManageRooms','window.confirm','this.title = String','title: String(values.get']) if(!sources.includes(contract)) throw new Error(`Frontendvertrag fehlt: ${contract}`);
for(const contract of ['const sequence = ++loadSequence','if (sequence !== loadSequence) return;','if (sequence === loadSequence) notice.error','let month = formatMonth(new Date())']) if(!sources.includes(contract)) throw new Error(`Monatsladevertrag fehlt: ${contract}`);
for(const contract of ['class BookingTimeline','adr-day-schedule','gridTemplateRows = this.timeline.rows(points)','gridRow = `${this.timeline.line','points(bookings)','rows(points)']) if(!sources.includes(contract)) throw new Error(`Gemeinsamer Zeitachsenvertrag fehlt: ${contract}`);
for(const contract of ['this.opener = document.activeElement','this.errorNode','showError(error, fallback)','this.opener?.focus()']) if(!sources.includes(contract)) throw new Error(`Dialogvertrag fehlt: ${contract}`);
const context={window:{},Date,String,Set,Math,Number,TypeError}; runInNewContext(wallTimeSource,context,{filename:fileURLToPath(new URL('../../js/modules/booking-wall-time.js',import.meta.url))}); runInNewContext(timelineSource,context,{filename:fileURLToPath(new URL('../../js/modules/booking-timeline.js',import.meta.url))}); runInNewContext(calendarSource,context,{filename:fileURLToPath(new URL('../../js/components/month-calendar.js',import.meta.url))}); runInNewContext(dialogSource,context,{filename:fileURLToPath(new URL('../../js/components/booking-dialog.js',import.meta.url))}); const calculator=new context.window.AdRoom.BookingTimeline();
const timeline=calculator.points([
    {startsAt:'2026-07-13T08:00:00',endsAt:'2026-07-13T09:00:00'},
    {startsAt:'2026-07-13T10:00:00',endsAt:'2026-07-13T11:00:00'},
]);
if(timeline.join(',')!=='0,480,540,600,660,1440') throw new Error(`Gemeinsame Zeitachse ist falsch: ${timeline.join(',')}`);
const rows=calculator.rows(timeline);
if(calculator.line(timeline,600)!==4||(rows.match(/minmax\(/g)||[]).length!==timeline.length-1||!rows.includes(', auto)')) throw new Error('Buchungspositionen werden nicht auf flexible gemeinsame Zeitzeilen abgebildet.');
const offsetTimestamp='2026-08-02T03:05:00+09:00';
const calendar=Object.create(context.window.AdRoom.MonthCalendar.prototype);
const dialog=Object.create(context.window.AdRoom.BookingDialog.prototype);
if(calculator.minute(offsetTimestamp)!==185) throw new Error('Die Zeitachse verschiebt fachliche Wandzeiten in die Browserzeitzone.');
if(calendar.dateKey(offsetTimestamp)!=='2026-08-02'||calendar.time(offsetTimestamp)!=='03:05') throw new Error('Der Kalender verschiebt fachliche Buchungszeiten in die Browserzeitzone.');
const localParts=dialog.localParts(offsetTimestamp);
if(localParts.date!=='2026-08-02'||localParts.time!=='03:05') throw new Error('Der Bearbeitungsdialog verschiebt fachliche Buchungszeiten in die Browserzeitzone.');
const workflowContext={window:{confirm:()=>true}}; runInNewContext(workflowSource,workflowContext,{filename:fileURLToPath(new URL('../../js/modules/booking-workflow.js',import.meta.url))}); const calls=[];
const workflow=new workflowContext.window.AdRoom.BookingWorkflow({
    repository:{createBooking:async(payload)=>calls.push(['create',payload]),updateBooking:async(id,payload)=>calls.push(['update',id,payload]),deleteBooking:async(id)=>calls.push(['delete',id])},
    notice:{success:(message)=>calls.push(['success',message]),error:(error,message)=>calls.push(['error',message])},dialog:{close:()=>calls.push(['close']),showError:(error,message)=>calls.push(['dialog-error',message])},reload:async()=>calls.push(['reload']),
});
await workflow.save({id:0,payload:{title:'Team'}}); await workflow.save({id:7,payload:{title:'Sitzung'}}); await workflow.remove({id:7});
if(calls.filter(call=>call[0]==='create').length!==1||calls.filter(call=>call[0]==='update').length!==1||calls.filter(call=>call[0]==='delete').length!==1) throw new Error('Buchungsworkflow unterscheidet Anlegen, Bearbeiten und Löschen nicht korrekt.');
workflow.repository.createBooking=async()=>{throw new Error('belegt');};
await workflow.save({id:0,payload:{title:'Konflikt'}});
if(calls.filter(call=>call[0]==='dialog-error').length!==1||calls.some(call=>call[0]==='error')) throw new Error('Fehler beim Speichern wird nicht im aktiven Buchungsdialog angezeigt.');
const roomWorkflowContext={window:{confirm:()=>true}}; runInNewContext(roomWorkflowSource,roomWorkflowContext,{filename:fileURLToPath(new URL('../../js/modules/room-workflow.js',import.meta.url))}); const roomCalls=[];
const roomWorkflow=new roomWorkflowContext.window.AdRoom.RoomWorkflow({
    repository:{createRoom:async(payload)=>roomCalls.push(['create',payload]),updateRoom:async(id,payload)=>roomCalls.push(['update',id,payload]),deleteRoom:async(id)=>roomCalls.push(['delete',id])},
    notice:{success:(message)=>roomCalls.push(['success',message]),error:(error,message)=>roomCalls.push(['error',message])},reload:async()=>roomCalls.push(['reload']),
});
await roomWorkflow.create({name:'Nord'}); await roomWorkflow.update(3,{name:'Süd'}); await roomWorkflow.remove({id:3,name:'Süd'});
if(roomCalls.filter(call=>call[0]==='create').length!==1||roomCalls.filter(call=>call[0]==='update').length!==1||roomCalls.filter(call=>call[0]==='delete').length!==1) throw new Error('Raumworkflow unterscheidet Anlegen, Bearbeiten und Löschen nicht korrekt.');
for(const removed of ['adr-tab-settings',"showView('settings')"]) if(sources.includes(removed)) throw new Error(`Administrative Raumverwaltung liegt noch in der Fachansicht: ${removed}`);
const adminBookingModel=adminTemplate.indexOf("\\OCP\\Util::addScript('adroom', 'models/booking');");
const adminRepository=adminTemplate.indexOf("\\OCP\\Util::addScript('adroom', 'repositories/room-repository');");
if(adminBookingModel<0||adminRepository<0||adminBookingModel>adminRepository) throw new Error('Der Raum-Adminbereich lädt das Buchungsmodell nicht vor dem gemeinsamen Monats-Repository.');
console.log('AD Raumplaner frontend smoke test passed');
