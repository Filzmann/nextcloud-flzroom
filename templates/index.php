<?php
\OCP\Util::addScript('localbase','api/api-client');
\OCP\Util::addScript('localbase','models/model');
\OCP\Util::addScript('localbase','repositories/repository');
\OCP\Util::addScript('localbase','ui/ui');
\OCP\Util::addScript('adroom','models/room');
\OCP\Util::addScript('adroom','models/booking');
\OCP\Util::addScript('adroom','repositories/room-repository');
\OCP\Util::addScript('adroom','modules/booking-wall-time');
\OCP\Util::addScript('adroom','modules/booking-timeline');
\OCP\Util::addScript('adroom','modules/booking-workflow');
\OCP\Util::addScript('adroom','components/month-calendar');
\OCP\Util::addScript('adroom','components/booking-dialog');
\OCP\Util::addScript('adroom','admin-access');
\OCP\Util::addScript('adroom','main');
\OCP\Util::addStyle('adroom','style');
?>
<main id="adroom-app" class="adr-app">
    <div class="orgsuite-host" data-orgsuite data-suite="ad" data-current-app="adroom"></div>
    <header class="adr-header">
        <div><h1>AD Raumplaner</h1><p>Räume und Buchungen im Monatsüberblick</p></div>
        <nav class="adr-month-navigation" aria-label="Monat auswählen">
            <button type="button" id="adr-previous">Vorheriger Monat</button>
            <label>Monat <input id="adr-month" type="month"></label>
            <button type="button" id="adr-next">Nächster Monat</button>
        </nav>
    </header>
    <div id="adr-notice" class="adr-notice" role="status" aria-live="polite" hidden></div>
    <?php if ($_['showMissingAdminGrant'] ?? false): ?>
        <section class="adr-admin-access adr-notice is-warning" aria-labelledby="adr-admin-access-required-heading">
            <h2 id="adr-admin-access-required-heading">Kein fachlicher Admin-Vollzugriff</h2>
            <p>Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff. Für geschützte Raumverwaltung und Fremdbuchungen fehlt eine aktive app-lokale Freigabe.</p>
            <?php if ($_['showAdminAccessLink'] ?? false): ?>
                <p><a href="#adr-full-access">Zur app-lokalen Freigabesteuerung</a></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if ($_['canManageAdminAccess'] ?? false): ?>
        <section id="adr-full-access" class="adr-admin-access" aria-labelledby="adr-full-access-heading">
            <h2 id="adr-full-access-heading">Zeitlich begrenzter Admin-Vollzugriff</h2>
            <p>Ausschließlich Mitglieder von Datenschutzbeauftragte dürfen einem aktuellen Nextcloud-Administrationskonto fachlichen Vollzugriff erteilen oder ihn widerrufen. Maximal 24 Stunden sind zulässig.</p>
            <form id="adr-full-access-form">
                <label>Admin-Benutzerkennung <input name="targetUid" required maxlength="64" autocomplete="off"></label>
                <label>Dauer
                    <select name="durationMinutes" required>
                        <option value="60">1 Stunde</option>
                        <option value="240">4 Stunden</option>
                        <option value="480">8 Stunden</option>
                        <option value="1440">24 Stunden</option>
                    </select>
                </label>
                <label><input id="adr-full-access-enabled" name="enabled" type="checkbox" required> Vollzugriff für diesen Zeitraum aktivieren</label>
                <button type="submit" class="primary">Freigabe aktivieren</button>
            </form>
            <p id="adr-full-access-status" role="status" aria-live="polite"></p>
            <div class="adr-table-wrap"><table>
                <caption>Protokollierte Admin-Vollzugriffszeiträume</caption>
                <thead><tr><th>Ziel-Admin</th><th>Freigegeben von</th><th>Von</th><th>Geplant bis</th><th>Tatsächlich bis / Status</th><th>Aktion</th></tr></thead>
                <tbody id="adr-full-access-history"><tr><td colspan="6">Freigaben werden geladen.</td></tr></tbody>
            </table></div>
        </section>
    <?php endif; ?>
    <section id="adr-calendar-view" aria-label="Raumkalender">
        <div class="adr-table-wrap">
            <table class="adr-calendar">
                <caption>Raumbuchungen des ausgewählten Monats</caption>
                <thead id="adr-calendar-head"></thead>
                <tbody id="adr-calendar-body"><tr><td>Daten werden geladen.</td></tr></tbody>
            </table>
        </div>
    </section>
    <dialog id="adr-booking-dialog" class="adr-dialog" aria-labelledby="adr-booking-title">
        <form id="adr-booking-form">
            <header><h2 id="adr-booking-title">Raumbuchung</h2><button type="button" class="adr-icon-button" data-dialog-close aria-label="Dialog schließen" title="Schließen"><span aria-hidden="true">×</span></button></header>
            <input name="id" type="hidden">
            <label>Raum <select name="roomId" required></select></label>
            <label>Datum <input name="date" type="date" required></label>
            <div class="adr-time-row">
                <label>Beginn <input name="startTime" type="time" step="300" required></label>
                <label>Ende <input name="endTime" type="time" step="300" required></label>
            </div>
            <label>Zweck <input name="purpose" list="adr-purpose-options" maxlength="255" required></label>
            <datalist id="adr-purpose-options"><option value="AT"><option value="Sitzung"><option value="BQ"><option value="Fortbildung"><option value="SV"><option value="HB"><option value="LG"></datalist>
            <label>Titel <input name="title" maxlength="255" aria-describedby="adr-title-hint" required></label>
            <small id="adr-title-hint">Zum Beispiel ASN, Gremium oder Thema.</small>
            <div id="adr-booking-error" class="adr-notice is-error" role="alert" aria-live="assertive" tabindex="-1" hidden></div>
            <footer><button type="button" data-dialog-close>Abbrechen</button><button type="submit" class="primary">Speichern</button></footer>
        </form>
    </dialog>
</main>
