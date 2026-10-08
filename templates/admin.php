<?php
\OCP\Util::addScript('localbase', 'api/api-client');
\OCP\Util::addScript('localbase', 'models/model');
\OCP\Util::addScript('localbase', 'repositories/repository');
\OCP\Util::addScript('localbase', 'ui/ui');
\OCP\Util::addScript('localbase', 'components/organization-dashboard');
\OCP\Util::addScript('flzroom', 'models/room');
\OCP\Util::addScript('flzroom', 'models/booking');
\OCP\Util::addScript('flzroom', 'repositories/room-repository');
\OCP\Util::addScript('flzroom', 'modules/room-workflow');
\OCP\Util::addScript('flzroom', 'components/room-settings');
\OCP\Util::addScript('flzroom', 'admin');
\OCP\Util::addStyle('localbase', 'organization-admin');
\OCP\Util::addStyle('flzroom', 'style');
?>
<section id="flzroom-admin" class="section flz-room-admin" aria-labelledby="flz-room-admin-heading">
    <h2 id="flz-room-admin-heading">Filzmann Raumplaner</h2>
    <p>Die Karten können persönlich ein- und ausgeklappt sowie per Tastatur oder Drag-and-drop verschoben werden. Fachwerte werden dadurch nicht verändert.</p>
    <div id="flz-room-admin-notice" class="flz-room-notice" role="status" aria-live="polite" aria-atomic="true" hidden></div>
    <p class="orgs-feedback" data-dashboard-feedback role="status" aria-live="polite"></p>
    <div class="orgs-dashboard-grid" data-dashboard-scope="main">
        <section class="orgs-panel orgs-dashboard-widget" data-dashboard-widget data-widget-id="rooms" aria-labelledby="flz-room-rooms-heading">
            <header class="orgs-dashboard-header"><h3 id="flz-room-rooms-heading" data-dashboard-title>Räume</h3><div class="orgs-dashboard-actions"><button type="button" data-dashboard-move="-1" aria-label="Räume eine Position zurück verschieben">↑</button><button type="button" data-dashboard-handle draggable="true" aria-label="Räume per Drag-and-drop verschieben">⠿</button><button type="button" data-dashboard-move="1" aria-label="Räume eine Position weiter verschieben">↓</button><button type="button" data-dashboard-toggle aria-expanded="true" aria-controls="flz-room-rooms-content" aria-label="Räume ein- oder ausklappen"><span aria-hidden="true">▾</span></button></div></header>
            <div id="flz-room-rooms-content" data-dashboard-content>
                <p>Beim Löschen eines Raums werden auch alle zugehörigen Buchungen gelöscht.</p>
                <div class="flz-room-table-wrap"><table class="flz-room-room-table"><caption>Vorhandene Räume</caption><thead><tr><th>Name</th><th>Beschreibung</th><th>Reihenfolge</th><th>Aktionen</th></tr></thead><tbody id="flz-room-admin-room-body"><tr><td colspan="4">Räume werden geladen.</td></tr></tbody></table></div>
                <form id="flz-room-admin-room-form" class="flz-room-room-form"><label>Name <input name="name" required maxlength="255"></label><label>Beschreibung <input name="description" maxlength="500"></label><label>Reihenfolge <input name="sortOrder" type="number" min="0" value="0"></label><button type="submit" class="primary">Raum anlegen</button></form>
            </div>
        </section>
        <section class="orgs-panel orgs-dashboard-widget" data-dashboard-widget data-widget-id="demo" aria-labelledby="flz-room-demo-heading">
            <header class="orgs-dashboard-header"><h3 id="flz-room-demo-heading" data-dashboard-title>Demo-Pack</h3><div class="orgs-dashboard-actions"><button type="button" data-dashboard-move="-1" aria-label="Demo-Pack eine Position zurück verschieben">↑</button><button type="button" data-dashboard-handle draggable="true" aria-label="Demo-Pack per Drag-and-drop verschieben">⠿</button><button type="button" data-dashboard-move="1" aria-label="Demo-Pack eine Position weiter verschieben">↓</button><button type="button" data-dashboard-toggle aria-expanded="true" aria-controls="flz-room-demo-content" aria-label="Demo-Pack ein- oder ausklappen"><span aria-hidden="true">▾</span></button></div></header>
            <div id="flz-room-demo-content" data-dashboard-content>
                <p>Das Pack legt neutrale Räume und Beispielbuchungen unter einem synthetischen lokalen Demokonto an. Es wird nicht automatisch installiert und importiert keine Bestandsdaten.</p>
                <p id="flz-room-demo-notice" class="flz-room-notice" role="status" aria-live="polite" hidden></p>
                <label class="flz-room-demo-confirm"><input id="flz-room-demo-confirm" type="checkbox"> Ich bestätige die Installation synthetischer Demodaten.</label>
                <button id="flz-room-demo-install" type="button" class="primary" disabled>Raum-Demo-Pack installieren</button>
            </div>
        </section>
    </div>
</section>
