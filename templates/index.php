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
\OCP\Util::addScript('flzroom','organization-groups');
if ($_['canViewRoomPlan'] ?? false) \OCP\Util::addScript('flzroom','main');
\OCP\Util::addStyle('flzroom','style');
?>
<main id="flzroom-app" class="flz-room-app">
    <div class="orgsuite-host" data-orgsuite data-suite="flz" data-current-app="flzroom"></div>
    <header class="flz-room-header">
        <div class="flz-room-title-row"><h1>Filzmann Raumplaner</h1><?php if ($_['showMissingAdminGrant'] ?? false): ?><details class="flz-room-admin-grant-warning"><summary aria-label="Informationen zum fehlenden fachlichen Admin-Vollzugriff"><span aria-hidden="true">⚠</span></summary><div class="flz-room-admin-grant-warning__details"><p><strong>Kein fachlicher Admin-Vollzugriff.</strong></p><p>Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff. Es fehlt eine aktive app-lokale Freigabe.</p><p>Freigaben können ausschließlich Mitglieder von Datenschutzbeauftragte erteilen oder widerrufen, höchstens für 24 Stunden.</p><?php if ($_['showAdminAccessLink'] ?? false): ?><p><a href="#flz-room-full-access" target="_blank" rel="noopener">Freigabesteuerung in neuem Tab öffnen</a></p><?php endif; ?></div></details><?php endif; ?><p>Räume und Buchungen im Monatsüberblick</p></div>
        <?php if ($_['canViewRoomPlan'] ?? false): ?>
        <nav class="flz-room-month-navigation" aria-label="Monat auswählen">
            <button type="button" id="flz-room-previous">Vorheriger Monat</button>
            <label>Monat <input id="flz-room-month" type="month"></label>
            <button type="button" id="flz-room-next">Nächster Monat</button>
        </nav>
        <?php endif; ?>
    </header>
    <div id="flz-room-notice" class="flz-room-notice" role="status" aria-live="polite" hidden></div>
    <?php if ($_['showMissingAdminGrant'] ?? false): ?>
        <section hidden class="flz-room-admin-access flz-room-notice is-warning" aria-labelledby="flz-room-admin-access-required-heading">
            <h2 id="flz-room-admin-access-required-heading">Kein fachlicher Admin-Vollzugriff</h2>
            <p>Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff. Für geschützte Raumverwaltung und Fremdbuchungen fehlt eine aktive app-lokale Freigabe.</p>
            <?php if ($_['showAdminAccessLink'] ?? false): ?>
                <p><a href="#flz-room-full-access">Zur app-lokalen Freigabesteuerung</a></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if ($_['canConfigureOrganizationGroups'] ?? false): ?>
        <section id="flz-room-organization-groups" class="flz-room-admin-access" aria-labelledby="flz-room-organization-groups-heading">
            <h2 id="flz-room-organization-groups-heading">Organisationsgruppen</h2>
            <p>Nur Mitglieder der hier eingetragenen Nextcloud-Gruppen erhalten Zugriff auf Raumplan und eigene Buchungen. Eine Gruppen-ID je Zeile; eine leere Liste sperrt den regulären Zugriff.</p>
            <form id="flz-room-organization-groups-form">
                <label>Nextcloud-Gruppen-IDs
                    <textarea name="organizationGroupIds" rows="5" maxlength="25600" aria-describedby="flz-room-organization-groups-hint"></textarea>
                </label>
                <small id="flz-room-organization-groups-hint">Es können ausschließlich bereits vorhandene Nextcloud-Gruppen gespeichert werden.</small>
                <button type="submit" class="primary">Organisationsgruppen speichern</button>
            </form>
            <p id="flz-room-organization-groups-status" role="status" aria-live="polite"></p>
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
    <?php if ($_['canViewRoomPlan'] ?? false): ?>
    <section id="flz-room-calendar-view" aria-label="Raumkalender">
        <div class="flz-room-table-wrap">
            <table class="flz-room-calendar">
                <caption>Raumbuchungen des ausgewählten Monats</caption>
                <thead id="flz-room-calendar-head"></thead>
                <tbody id="flz-room-calendar-body"><tr><td>Daten werden geladen.</td></tr></tbody>
            </table>
        </div>
    </section>
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
                <label>Begründung des Sekretariatseingriffs
                    <textarea name="reason" minlength="10" maxlength="500" aria-describedby="flz-room-intervention-reason-hint"></textarea>
                </label>
                <small id="flz-room-intervention-reason-hint">10–500 Zeichen. Nur den organisatorischen Grund angeben. Keine Namen, Gesundheits-, Fall- oder anderen unnötigen Drittpersonenangaben.</small>
            </div>
            <div id="flz-room-booking-error" class="flz-room-notice is-error" role="alert" aria-live="assertive" tabindex="-1" hidden></div>
            <footer><button type="button" data-dialog-close>Abbrechen</button><button type="submit" class="primary">Speichern</button></footer>
        </form>
    </dialog>
    <?php endif; ?>
</main>
