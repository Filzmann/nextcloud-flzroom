# Roadmap – AD Raumplaner

## Offene suiteweite Admin-Freigabe

Nur Mitglieder von `Datenschutzbeauftragte` dürfen pro App und aktivem Nextcloud-Administrationskonto eine Freigabe erteilen oder widerrufen. Die Freigabe bleibt auf höchstens 24 Stunden begrenzt und app-lokal auditierbar; native Administration allein genügt nicht. Ohne Freigabe gilt eine aussagekräftige sichere Meldung, ein direkter Freigabelink erscheint nur bei gleichzeitiger Datenschutzbeauftragten- und Admin-Rolle. Runtime-, UI-, Controller- und Allow-/Deny-Tests bleiben offen.

## Offene Fachberechtigung für Organisationskräfte und Sekretariat

Der Zielvertrag ist entschieden, aber technisch noch nicht umgesetzt: Nur
Organisationskräfte erhalten Zugriff auf den Raumplaner, reine
Assistenzkräfte keinen. Organisationskräfte verwalten eigene Buchungen. Die
kanonische LocalBase-Gruppe `ad-Sekretariat` erhält davon getrennte
app-lokale Fachrechte zur Raumverwaltung und zur Konfliktlösung durch
begründete Änderung oder Löschung fremder Buchungen; diese Rolle ist kein
temporärer technischer Admin-Vollzugriff.

Offen sind die Ableitung der Organisationszuordnung, die zentralisierte
serverseitige Rechteprüfung, die verpflichtende Begründung und app-lokale
Auditierung, die datensparsame Nextcloud-Benachrichtigung an die betroffene
buchende Person sowie Allow-/Deny-, Fremdobjekt-, Manipulations- und
Nebenwirkungstests. Die Benachrichtigung darf nur alten und, soweit
anwendbar, neuen Raum und Zeitraum sowie die Begründung enthalten; andere
Personen, Buchungen und weitere Buchungsdaten bleiben ausgeschlossen.
Processing-Katalog, PersonalDataProvider, PermissionProvider, Retention und
Betroffenenrechte sind für die neu gespeicherten Audit- und
Benachrichtigungsdaten im selben Implementierungsauftrag nachzuführen. Bis
dahin darf die Dokumentation keine bereits wirksame Einschränkung behaupten.

Diese Datei enthält ausschließlich offene Arbeit, zurückgestellte Vorhaben
und Freigabegates. Der aktuelle Funktionsumfang steht in `README.md`,
erledigte Änderungen in `CHANGELOG.md` und geltende Architektur in
`docs/architecture.md`.

## Aktueller Fokus

- App-lokalen temporären Admin-Vollzugriff einschließlich 24-Stunden-Grenze,
  Auditmigration, Privacy-/PermissionProvider und Allow-/Deny-Vertrag in DDEV
  migrieren und auf Staging abnehmen.
- Zugriff für Organisationskräfte und die Fachrolle `ad-Sekretariat`
  einschließlich fremder Eingriffe, Audit und datensparsamer Benachrichtigung
  nach dem entschiedenen Zielvertrag testgetrieben umsetzen.
- Die manuellen Prüfungen werden im ausfüllbaren
  [`docs/manual-acceptance.md`](docs/manual-acceptance.md) dokumentiert.
- Monatsansicht, Kollisionsschutz, eigene Buchungsrechte und administrative Raumverwaltung auf einem realitätsnahen Staging fachlich abnehmen.
- Löschbestätigung, Zeitraster, Wochenenden und die Feiertage der administrativ gewählten Organisationsregion sichtbar und barrierefrei prüfen.

## Offene Datenschutzentscheidungen

- Fachliche Verantwortlichkeit und Rechtsgrundlage für Raumbuchungen,
  temporäre Adminfreigaben und die persönliche Adminanordnung entscheiden.
- Den konfigurierbaren Standardwert von einem Jahr ab Buchungsende
  ausschließlich für Mitglieder der Nextcloud-Gruppe
  `Datenschutzbeauftragte` administrierbar machen und Friständerungen anhand
  des ursprünglichen Buchungsendes rückwirkend auf vorhandene Buchungen
  anwenden. Policyänderungen 24 Monate auditierbar halten und mindestens
  jährlich durch `Datenschutzbeauftragte` prüfen lassen.
- Die entschiedene vollständige Löschung beendeter Buchungen nach Fristablauf
  sowie der Adminfreigabehistorie sechs Monate nach tatsächlichem Ende ohne
  Reststatistik umsetzen. Rechtliche oder datenschutzrechtliche Sperren
  blockieren die Löschung und dürfen nur durch `Datenschutzbeauftragte`
  begründet und auditiert aufgehoben werden. Nach Restore Fristen aus dem
  ursprünglichen Trigger neu bewerten, abgelaufene ungesperrte Daten erneut
  einplanen und keine Adminfreigabe reaktivieren.
- Retention-Ausführung bis zu einem freigegebenen und getesteten Versions-,
  Wirksamkeitszeitpunkt-, Reihenfolge-, Atomaritäts-, Nebenläufigkeits-,
  Idempotenz-, Backupgrenz-, Sperr-, Audit-, Retry-, Fehlerdiagnostik-,
  Fehlerrückbau- und Provider-/Consumer-Vertrag blockieren. Die spätere
  Ausführung läuft automatisch ohne manuelle Einzelfreigabe; nach Retries
  erhält `Datenschutzbeauftragte` nur App, Datenklasse, Zeitpunkt und
  technische Referenz, der inhaltsarme Fehlernachweis bleibt 30 Tage. Bis
  dahin bleibt es bei der lesenden `REVIEW`-Vorschau.
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
  außerhalb der App zwischen den Beteiligten abgestimmt. Nach Umsetzung der
  beschlossenen Fachrolle darf das Sekretariat den abgestimmten Eingriff in
  der App ausführen; dies ist kein In-App-Anfrageworkflow. Ein späterer
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
