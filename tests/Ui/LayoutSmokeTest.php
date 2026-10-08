<?php

declare(strict_types=1);

$template=file_get_contents(__DIR__.'/../../templates/index.php'); $admin=file_get_contents(__DIR__.'/../../templates/admin.php'); $css=file_get_contents(__DIR__.'/../../css/style.css'); $info=file_get_contents(__DIR__.'/../../appinfo/info.xml');
if($template===false||$admin===false||$css===false||$info===false) throw new RuntimeException('UI-Vertragsdatei fehlt.');
foreach (['data-orgsuite data-suite="flz" data-current-app="flzroom"','id="flz-room-calendar-view"','<caption>Raumbuchungen','id="flz-room-booking-dialog"','id="flz-room-booking-error"','role="alert"','name="title"','value="SV"','value="HB"','value="LG"','step="300"','aria-live="polite"',"\\OCP\\Util::addScript('localbase','repositories/repository')","\\OCP\\Util::addScript('flzroom','modules/booking-timeline')","\\OCP\\Util::addScript('flzroom','modules/booking-workflow')"] as $contract) if(!str_contains($template,$contract)) throw new RuntimeException("UI-Vertrag fehlt: {$contract}");
foreach (['min="06:00"','max="21:00"','step="900"'] as $obsolete) if(str_contains($template,$obsolete)) throw new RuntimeException("Alte Zeitgrenze ist noch im Dialog aktiv: {$obsolete}");
foreach(['flz-room-tab-settings','flz-room-settings-view','>Einstellungen</button>'] as $removed) if(str_contains($template,$removed)) throw new RuntimeException("Administrative Raumverwaltung liegt noch in der Fachansicht: {$removed}");
foreach(['id="flzroom-admin"','id="flz-room-admin-room-body"','id="flz-room-admin-room-form"','<h2 id="flz-room-admin-heading">Filzmann Raumplaner</h2>','data-widget-id="rooms"','data-widget-id="demo"',"\\OCP\\Util::addScript('localbase', 'repositories/repository')","\\OCP\\Util::addScript('flzroom', 'modules/room-workflow')"] as $contract) if(!str_contains($admin,$contract)) throw new RuntimeException("Raum-Adminvertrag fehlt: {$contract}");
if(str_contains($admin,'data-widget-id="retention"')) throw new RuntimeException('DPO-Retention ist noch Teil des technischen Adminbereichs.');
foreach ([$template, $admin] as $assetTemplate) if (preg_match('/^\\s*(?:script|style)\\s*\\(/m', $assetTemplate) === 1) throw new RuntimeException('Veralteter globaler Templatehelfer gefunden.');
foreach (['height: 100%','min-height: 0','overflow:hidden','overflow-x: hidden','background: var(--color-main-background)','overflow:auto','width:max-content','position:sticky','focus','.flz-room-day-schedule { display:grid','.flz-room-room-lane','isolation:isolate','repeating-linear-gradient'] as $contract) if(!str_contains($css,$contract)) throw new RuntimeException("Layoutvertrag fehlt: {$contract}");
if(str_contains($info,'<app>')||!str_contains($info,'<admin>OCA\FlzRoom\Settings\Admin</admin>')||str_contains($info,'<navigations>')) throw new RuntimeException('Standalone-Appvertrag fehlt.');
if(str_contains($template,"addScript('orgsuite'")||str_contains($template,"addStyle('orgsuite'")) throw new RuntimeException('Direkte OrgSuite-Assetkopplung vorhanden.');
echo "Filzmann Raumplaner layout smoke test passed\n";
