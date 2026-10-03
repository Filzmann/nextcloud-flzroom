# Roadmap – AD Raumplaner

## Offene Fachberechtigung für Organisationskräfte und Sekretariat

Der Zugriff ist serverseitig auf die von `Datenschutzbeauftragte`
konfigurierten Nextcloud-Gruppen begrenzt; Organisationskräfte verwalten eigene
Buchungen. Freie Titel sehen nur die buchende Person und `ad-Sekretariat`. Die
kanonische Gruppe `ad-Sekretariat` erhält davon getrennte
app-lokale Fachrechte zur Raumverwaltung und zur Konfliktlösung durch
begründete Änderung oder Löschung fremder Buchungen; diese Rolle ist kein
temporärer technischer Admin-Vollzugriff.

Der begründete Fremdeingriff einschließlich neutralem Risikoscope, Audit,
Benachrichtigungsqueue, festen Fristen sowie Providerprojektionen ist
umgesetzt. Offen bleibt ausschließlich die Runtime-/Staging-Abnahme mit
echter Nextcloud-Benachrichtigung und installierter Datenbankmigration.

Diese Datei enthält ausschließlich offene Arbeit, zurückgestellte Vorhaben
und Freigabegates. Der aktuelle Funktionsumfang steht in `README.md`,
erledigte Änderungen in `CHANGELOG.md` und geltende Architektur in
`docs/architecture.md`.

## Aktueller Fokus

- App-lokalen temporären Admin-Vollzugriff und seine rollenabhängige
  Freigabesteuerung in DDEV und auf Staging abnehmen.
- Fremdeingriffe, Audit und Benachrichtigungswiederholung in DDEV und auf
  Staging mit realen Rollen und nativen Nextcloud-Benachrichtigungen abnehmen.
- Die manuellen Prüfungen werden im ausfüllbaren
  [`docs/manual-acceptance.md`](docs/manual-acceptance.md) dokumentiert.
- Monatsansicht, Kollisionsschutz, eigene Buchungsrechte und administrative Raumverwaltung auf einem realitätsnahen Staging fachlich abnehmen.
- Löschbestätigung, Zeitraster, Wochenenden und die Feiertage der administrativ gewählten Organisationsregion sichtbar und barrierefrei prüfen.

## Offene Datenschutz- und Runtime-Nachweise

- Den implementierten V2-DELETE-Provider für Buchungen und
  Adminfreigabehistorien gemeinsam mit der technischen Aktivierung des
  Datenschutz-Centers auf der unterstützten realen Datenbank- und
  Nextcloud-Runtime abnehmen. Nachzuweisen sind frische Installation
  beziehungsweise Reinstall, Migration, Job-Wiederanlauf, echte
  Transaktionsgrenze, Nebenläufigkeit, Hold-Rennen, Rollback vor Commit,
  Provider-/Consumer-Versionen und Restore-Quarantäne. Bis dahin besteht kein
  Releaseurteil für automatische Löschung.
- In der Runtime-Abnahme positiv und negativ belegen, dass der technische
  Operatorpfad standardmäßig deaktiviert ist, nur native
  Nextcloud-Administration zulässt und bei fehlender, zukünftiger, fälliger,
  beschädigter oder veralteter Konfiguration sowie ungültiger
  Backup-/Restore-Prüfung fail-closed bleibt. Rechtsgrundlage,
  Betriebsvereinbarung, DPO-/Betriebsratsbestätigung und Kundenevidenz bleiben
  außerhalb des Produktpakets und sind keine Aktivierungsfelder oder
  technischen DELETE-Gates.
- Den entschiedenen Lifecycle für den bereits subjectgebunden ausgegebenen
  Nextcloud-`IUserConfig`-Wert `admin_dashboard_layout` umsetzen: nur der
  jeweilige Kontoinhaber darf den eigenen Wert zurücksetzen; der Wert ist beim
  Self-Reset, bei Nextcloud-Kontolöschung und bei App-Deinstallation zu
  löschen. Native Administration und `Datenschutzbeauftragte` dürfen keine
  fremden Layouts zurücksetzen. Reset-Endpunkt, Konto-/App-Lifecycle-Anbindung
  und Tests fehlen; Backup- und Restore-Verhalten bleiben offen.

## Geplante Erweiterungen

- Persönliche Einstellungen erhalten erst bei einem konkreten dauerhaften Nutzerwert einen eigenen App-Tab.
- Optionale Direktbuchungen aus Kalender oder Assistenzplanung können nach einem konkreten Anwendungsfall ergänzt werden; der manuelle Standalone-Betrieb bleibt erhalten.
- Änderungswünsche zu fremden Buchungen werden vorerst weiterhin direkt
  außerhalb der App zwischen den Beteiligten abgestimmt. Das Sekretariat darf
  den abgestimmten Eingriff über den implementierten begründeten und
  auditierten Pfad in der App ausführen; dies ist kein In-App-Anfrageworkflow.
  Ein späterer
  Anfrageworkflow wird erst bei einem konkreten Bedarf und nach einem eigenen
  Vertrag für Akteur*innen, Empfänger*innen, erforderliche Daten und
  Datenschutz, Zustände, Wiederholungen, Ablauf, Auditierung sowie
  serverseitige Autorisierung geprüft; eine Anfrage darf niemals automatisch
  die Buchung ändern.

## Vor der Umsetzung zu klären

- Fachlicher Auslöser, Zielraum, Zeitraum und Besitzer*in einer Direktbuchung.
- Serverseitige Rechte, Konfliktverhalten und Rückmeldung an die aufrufende App.
- Kleiner optionaler Integrationsvertrag ohne direkten Zugriff auf fremde Tabellen oder Assets.

## Bewusst zurückgestellt – niedrigste Priorität

### ROOM-L10N – Oberfläche und Kalenderdarstellung lokalisieren

Status seit 17. September 2026: Die Umsetzung beginnt erst nach allen höher
priorisierten Roadmap-Aufgaben und einer erneuten ausdrücklichen Freigabe des
Root-Vorhabens `ZM-06`. Neue Funktionen und Codeänderungen berücksichtigen
die spätere Lokalisierbarkeit an den jeweils berührten Stellen, lösen aber
keine flächige Umstellung oder Übersetzungsimplementierung aus.

Bei der späteren Umsetzung werden sichtbare Texte sowie Monats- und
Wochentagsnamen app-lokal auf Nextcloud-l10n umgestellt; ISO-Zeiträume,
5-Minuten-Raster, Zweck-Schlüssel, Raum-IDs und API-Werte bleiben
sprachneutral. Konfigurierte Titel und Raumnamen werden nicht automatisch
übersetzt.
