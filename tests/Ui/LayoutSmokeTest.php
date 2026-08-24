<?php

declare(strict_types=1);

$template=file_get_contents(__DIR__.'/../../templates/index.php'); $admin=file_get_contents(__DIR__.'/../../templates/admin.php'); $css=file_get_contents(__DIR__.'/../../css/style.css'); $info=file_get_contents(__DIR__.'/../../appinfo/info.xml');
if($template===false||$admin===false||$css===false||$info===false) throw new RuntimeException('UI-Vertragsdatei fehlt.');
foreach (['data-orgsuite data-suite="ad" data-current-app="adroom"','id="adr-calendar-view"','<caption>Raumbuchungen','id="adr-booking-dialog"','id="adr-booking-error"','role="alert"','name="title"','value="SV"','value="HB"','value="LG"','step="300"','aria-live="polite"',"\\OCP\\Util::addScript('localbase','repositories/repository')","\\OCP\\Util::addScript('adroom','modules/booking-timeline')","\\OCP\\Util::addScript('adroom','modules/booking-workflow')"] as $contract) if(!str_contains($template,$contract)) throw new RuntimeException("UI-Vertrag fehlt: {$contract}");
foreach (['min="06:00"','max="21:00"','step="900"'] as $obsolete) if(str_contains($template,$obsolete)) throw new RuntimeException("Alte Zeitgrenze ist noch im Dialog aktiv: {$obsolete}");
foreach(['adr-tab-settings','adr-settings-view','>Einstellungen</button>'] as $removed) if(str_contains($template,$removed)) throw new RuntimeException("Administrative Raumverwaltung liegt noch in der Fachansicht: {$removed}");
foreach(['id="adroom-admin"','id="adr-admin-room-body"','id="adr-admin-room-form"','<h2 id="adr-admin-heading">AD Raumplaner</h2>','data-widget-id="rooms"','data-widget-id="retention"',"\\OCP\\Util::addScript('localbase', 'repositories/repository')","\\OCP\\Util::addScript('adroom', 'modules/room-workflow')"] as $contract) if(!str_contains($admin,$contract)) throw new RuntimeException("Raum-Adminvertrag fehlt: {$contract}");
foreach ([$template, $admin] as $assetTemplate) if (preg_match('/^\\s*(?:script|style)\\s*\\(/m', $assetTemplate) === 1) throw new RuntimeException('Veralteter globaler Templatehelfer gefunden.');
foreach (['height: 100%','min-height: 0','overflow:hidden','overflow-x: hidden','background: var(--color-main-background)','overflow:auto','width:max-content','position:sticky','focus','.adr-day-schedule { display:grid','.adr-room-lane','isolation:isolate','repeating-linear-gradient'] as $contract) if(!str_contains($css,$contract)) throw new RuntimeException("Layoutvertrag fehlt: {$contract}");
if(str_contains($info,'<app>')||!str_contains($info,'<admin>OCA\AdRoom\Settings\Admin</admin>')||str_contains($info,'<navigations>')) throw new RuntimeException('Standalone-Appvertrag fehlt.');
if(str_contains($template,"addScript('orgsuite'")||str_contains($template,"addStyle('orgsuite'")) throw new RuntimeException('Direkte OrgSuite-Assetkopplung vorhanden.');
echo "AD Raumplaner layout smoke test passed\n";
