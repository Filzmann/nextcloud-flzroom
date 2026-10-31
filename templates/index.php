<?php
\OCP\Util::addScript('localbase','api/api-client');
\OCP\Util::addScript('localbase','models/model');
\OCP\Util::addScript('localbase','repositories/repository');
\OCP\Util::addScript('localbase','ui/ui');
\OCP\Util::addScript('flzroom','models/room');
\OCP\Util::addScript('flzroom','models/booking');
\OCP\Util::addScript('flzroom','repositories/room-repository');
\OCP\Util::addScript('flzroom','modules/booking-wall-time');
\OCP\Util::addScript('flzroom','modules/booking-timeline');
\OCP\Util::addScript('flzroom','modules/booking-workflow');
\OCP\Util::addScript('flzroom','components/month-calendar');
\OCP\Util::addScript('flzroom','components/booking-dialog');
\OCP\Util::addScript('flzroom','admin-access');
\OCP\Util::addScript('flzroom','retention-policy');
\OCP\Util::addScript('flzroom','permission-settings');
\OCP\Util::addScript('flzroom','settings-tabs');
$canManageRooms = $_['canManageRooms'] ?? false;
if ($canManageRooms) {
    \OCP\Util::addScript('flzroom','modules/room-workflow');
    \OCP\Util::addScript('flzroom','components/room-settings');
    \OCP\Util::addScript('flzroom','room-management-settings');
}
if ($_['canViewRoomPlan'] ?? false) \OCP\Util::addScript('flzroom','main');
\OCP\Util::addStyle('flzroom','style');
$canViewRoomPlan = $_['canViewRoomPlan'] ?? false;
$hasSettings = ($_['canConfigurePermissions'] ?? false)
    || ($_['canConfigureRetention'] ?? false)
    || ($_['canManageAdminAccess'] ?? false)
    || $canManageRooms;
?>
<main id="flzroom-app" class="flz-room-app">
    <div class="orgsuite-host" data-orgsuite data-suite="flz" data-current-app="flzroom"></div>
    <header class="flz-room-header">
        <div class="flz-room-title-row"><h1>Filzmann Raumplaner</h1><?php if ($_['showMissingAdminGrant'] ?? false): ?><details class="flz-room-admin-grant-warning"><summary aria-label="Informationen zum fehlenden fachlichen Admin-Vollzugriff"><span aria-hidden="true">⚠</span></summary><div class="flz-room-admin-grant-warning__details"><p><strong>Kein fachlicher Admin-Vollzugriff.</strong></p><p>Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff. Es fehlt eine aktive app-lokale Freigabe.</p><p>Freigaben können ausschließlich Mitglieder von Datenschutzbeauftragte erteilen oder widerrufen, höchstens für 24 Stunden.</p><?php if ($_['showAdminAccessLink'] ?? false): ?><p><a href="#flz-room-full-access" target="_blank" rel="noopener">Freigabesteuerung in neuem Tab öffnen</a></p><?php endif; ?></div></details><?php endif; ?><p>Räume und Buchungen im Monatsüberblick</p></div>
    </header>
    <div id="flz-room-notice" class="flz-room-notice" role="status" aria-live="polite" hidden></div>
    <?php if ($canViewRoomPlan || $hasSettings): ?>
    <nav class="flz-room-tabs" role="tablist" aria-label="Raumplaner-Bereiche">
        <?php if ($canViewRoomPlan): ?>
            <button type="button" id="flz-room-tab-calendar" role="tab" aria-controls="flz-room-calendar-view" aria-selected="true">Raumkalender</button>
        <?php endif; ?>
        <?php if ($hasSettings): ?>
            <button type="button" id="flz-room-tab-settings" role="tab" aria-controls="flz-room-settings-view" aria-selected="<?= $canViewRoomPlan ? 'false' : 'true' ?>">Einstellungen</button>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php if ($hasSettings): ?>
    <section id="flz-room-settings-view" class="flz-room-settings-view" role="tabpanel" aria-labelledby="flz-room-tab-settings"<?= $canViewRoomPlan ? ' hidden' : '' ?>>
    <?php if ($canManageRooms): ?>
        <section id="flz-room-settings-rooms" class="flz-room-admin-access" aria-labelledby="flz-room-settings-rooms-heading">
            <h2 id="flz-room-settings-rooms-heading">Räume verwalten</h2>
            <p>Native Nextcloud-Administrationskonten und zugewiesene Organigrammrollen dürfen Raumstammdaten verwalten. Dieses Recht gewährt keinen Zugriff auf fremde Buchungen.</p>
            <p>Beim Löschen eines Raums werden auch alle zugehörigen Buchungen gelöscht.</p>
            <div class="flz-room-table-wrap"><table class="flz-room-room-table"><caption>Vorhandene Räume</caption><thead><tr><th>Name</th><th>Beschreibung</th><th>Reihenfolge</th><th>Aktionen</th></tr></thead><tbody id="flz-room-settings-room-body"><tr><td colspan="4">Räume werden geladen.</td></tr></tbody></table></div>
            <form id="flz-room-settings-room-form" class="flz-room-room-form"><label>Name <input name="name" required maxlength="255"></label><label>Beschreibung <input name="description" maxlength="500"></label><label>Reihenfolge <input name="sortOrder" type="number" min="0" value="0"></label><button type="submit" class="primary">Raum anlegen</button></form>
        </section>
    <?php endif; ?>
    <?php if ($_['showMissingAdminGrant'] ?? false): ?>
        <section hidden class="flz-room-admin-access flz-room-notice is-warning" aria-labelledby="flz-room-admin-access-required-heading">
            <h2 id="flz-room-admin-access-required-heading">Kein fachlicher Admin-Vollzugriff</h2>
            <p>Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff. Für die Verwaltung aller Buchungen fehlt eine aktive app-lokale Freigabe.</p>
            <?php if ($_['showAdminAccessLink'] ?? false): ?>
                <p><a href="#flz-room-full-access">Zur app-lokalen Freigabesteuerung</a></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if ($_['canConfigurePermissions'] ?? false): ?>
        <section id="flz-room-permission-settings" class="flz-room-admin-access" aria-labelledby="flz-room-permission-settings-heading">
            <h2 id="flz-room-permission-settings-heading">Berechtigungen nach Organigrammrolle</h2>
            <p>Rollen stammen ausschließlich aus dem Organigramm. Die Auswahl erteilt einzelne Raumplaner-Rechte; sie verändert keine Gruppenmitgliedschaften. AS und ASN bleiben unabhängig von der Matrix ausgeschlossen.</p>
            <form id="flz-room-permission-settings-form">
                <div class="flz-room-table-wrap"><table>
                    <caption>App-lokale Rechtezuordnung</caption>
                    <thead><tr><th scope="col">Organigrammrolle</th><th scope="col">Raumplan lesen</th><th scope="col">Eigene Buchungen</th><th scope="col">Freie Titel lesen</th><th scope="col">Fremde Buchungen</th><th scope="col">Räume verwalten</th></tr></thead>
                    <tbody data-permission-matrix-body><tr><td colspan="6">Rechte werden geladen.</td></tr></tbody>
                </table></div>
                <button type="submit" class="primary">Rechte speichern</button>
            </form>
            <p>Fest zugeordnet: Eingriffsaudit, Rechteverwaltung, Retention und Adminfreigaben für Datenschutz; alle Buchungen nur für native Nextcloud-Admins mit aktiver app-lokaler Freigabe.</p>
            <p id="flz-room-permission-settings-status" role="status" aria-live="polite"></p>
        </section>
    <?php endif; ?>
    <?php if ($_['canConfigureRetention'] ?? false): ?>
        <section id="flz-room-retention-policy" class="flz-room-admin-access" aria-labelledby="flz-room-retention-policy-heading">
            <h2 id="flz-room-retention-policy-heading">Aufbewahrungsprüfung</h2>
            <p>Standard sind ein Jahr ab Buchungsende und sechs Monate ab tatsächlichem Ende einer Adminfreigabe. Die Versionierung ändert nur die REVIEW-Vorschau; automatische Löschung oder Anonymisierung bleibt deaktiviert.</p>
            <form id="flz-room-retention-policy-form">
                <label>Kalenderfrist <input name="durationPeriod" value="P1Y" pattern="P[1-9][0-9]*[YMD]" required aria-describedby="flz-room-retention-period-hint"></label>
                <small id="flz-room-retention-period-hint">ISO-8601, zum Beispiel P1Y, P6M oder P180D.</small>
                <label>Adminfreigabehistorie <input name="adminHistoryDurationPeriod" value="P6M" pattern="P[1-9][0-9]*[YMD]" required aria-describedby="flz-room-admin-history-retention-period-hint"></label>
                <small id="flz-room-admin-history-retention-period-hint">Standardmäßig sechs Monate ab dem tatsächlichen Ende der Freigabe.</small>
                <input name="expectedRevision" type="hidden" value="0">
                <button type="submit" class="primary">Frist als neue Version speichern</button>
                <button type="button" data-retention-review>Jährliche Prüfung protokollieren</button>
            </form>
            <p id="flz-room-retention-policy-status" role="status" aria-live="polite"></p>
        </section>
    <?php endif; ?>
    <?php if ($_['canManageAdminAccess'] ?? false): ?>
        <section id="flz-room-full-access" class="flz-room-admin-access" aria-labelledby="flz-room-full-access-heading">
            <h2 id="flz-room-full-access-heading">Zeitlich begrenzter Admin-Vollzugriff</h2>
            <p>Ausschließlich Mitglieder von Datenschutzbeauftragte dürfen einem aktuellen Nextcloud-Administrationskonto fachlichen Vollzugriff erteilen oder ihn widerrufen. Maximal 24 Stunden sind zulässig.</p>
            <form id="flz-room-full-access-form">
                <label>Admin-Benutzerkennung <input name="targetUid" required maxlength="64" autocomplete="off"></label>
                <label>Dauer
                    <select name="durationMinutes" required>
                        <option value="60">1 Stunde</option>
                        <option value="240">4 Stunden</option>
                        <option value="480">8 Stunden</option>
                        <option value="1440">24 Stunden</option>
                    </select>
                </label>
                <label><input id="flz-room-full-access-enabled" name="enabled" type="checkbox" required> Vollzugriff für diesen Zeitraum aktivieren</label>
                <button type="submit" class="primary">Freigabe aktivieren</button>
            </form>
            <p id="flz-room-full-access-status" role="status" aria-live="polite"></p>
            <div class="flz-room-table-wrap"><table>
                <caption>Protokollierte Admin-Vollzugriffszeiträume</caption>
                <thead><tr><th>Ziel-Admin</th><th>Freigegeben von</th><th>Von</th><th>Geplant bis</th><th>Tatsächlich bis / Status</th><th>Aktion</th></tr></thead>
                <tbody id="flz-room-full-access-history"><tr><td colspan="6">Freigaben werden geladen.</td></tr></tbody>
            </table></div>
        </section>
    <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if ($canViewRoomPlan): ?>
    <section id="flz-room-calendar-view" class="flz-room-calendar-view" role="tabpanel" aria-labelledby="flz-room-tab-calendar">
        <nav class="flz-room-month-navigation" aria-label="Monat auswählen">
            <button type="button" id="flz-room-previous">Vorheriger Monat</button>
            <label>Monat <input id="flz-room-month" type="month"></label>
            <button type="button" id="flz-room-next">Nächster Monat</button>
        </nav>
        <div class="flz-room-table-wrap">
            <table class="flz-room-calendar">
                <caption>Raumbuchungen des ausgewählten Monats</caption>
                <thead id="flz-room-calendar-head"></thead>
                <tbody id="flz-room-calendar-body"><tr><td>Daten werden geladen.</td></tr></tbody>
            </table>
        </div>
    <dialog id="flz-room-booking-dialog" class="flz-room-dialog" aria-labelledby="flz-room-booking-title">
        <form id="flz-room-booking-form">
            <header><h2 id="flz-room-booking-title">Raumbuchung</h2><button type="button" class="flz-room-icon-button" data-dialog-close aria-label="Dialog schließen" title="Schließen"><span aria-hidden="true">×</span></button></header>
            <input name="id" type="hidden">
            <label>Raum <select name="roomId" required></select></label>
            <label>Datum <input name="date" type="date" required></label>
            <div class="flz-room-time-row">
                <label>Beginn <input name="startTime" type="time" step="300" required></label>
                <label>Ende <input name="endTime" type="time" step="300" required></label>
            </div>
            <label>Zweck <input name="purpose" list="flz-room-purpose-options" maxlength="255" required></label>
            <datalist id="flz-room-purpose-options"><option value="AT"><option value="Sitzung"><option value="BQ"><option value="Fortbildung"><option value="SV"><option value="HB"><option value="LG"></datalist>
            <label>Titel <input name="title" maxlength="255" aria-describedby="flz-room-title-hint" required></label>
            <small id="flz-room-title-hint">Nur notwendige Sachangaben, zum Beispiel Gremium oder Thema. Keine Namen, Gesundheits-, Fall- oder anderen unnötigen Drittpersonenangaben.</small>
            <div id="flz-room-intervention-reason-group" hidden>
                <label>Begründung des organisatorischen Fremdeingriffs
                    <textarea name="reason" minlength="10" maxlength="500" aria-describedby="flz-room-intervention-reason-hint"></textarea>
                </label>
                <small id="flz-room-intervention-reason-hint">10–500 Zeichen. Nur den organisatorischen Grund angeben. Keine Namen, Gesundheits-, Fall- oder anderen unnötigen Drittpersonenangaben.</small>
            </div>
            <div id="flz-room-booking-error" class="flz-room-notice is-error" role="alert" aria-live="assertive" tabindex="-1" hidden></div>
            <footer><button type="button" data-dialog-close>Abbrechen</button><button type="submit" class="primary">Speichern</button></footer>
        </form>
    </dialog>
    </section>
    <?php endif; ?>
</main>
