# Roadmap – AD Raumplaner

## Offene suiteweite Admin-Freigabe

Nur Mitglieder von `Datenschutzbeauftragte` dürfen pro App und aktivem Nextcloud-Administrationskonto eine Freigabe erteilen oder widerrufen. Die Freigabe bleibt auf höchstens 24 Stunden begrenzt und app-lokal auditierbar; native Administration allein genügt nicht. Ohne Freigabe gilt eine aussagekräftige sichere Meldung, ein direkter Freigabelink erscheint nur bei gleichzeitiger Datenschutzbeauftragten- und Admin-Rolle. Runtime-, UI-, Controller- und Allow-/Deny-Tests bleiben offen.

Diese Datei enthält ausschließlich offene Arbeit, zurückgestellte Vorhaben
und Freigabegates. Der aktuelle Funktionsumfang steht in `README.md`,
erledigte Änderungen in `CHANGELOG.md` und geltende Architektur in
`docs/architecture.md`.

## Aktueller Fokus

- App-lokalen temporären Admin-Vollzugriff einschließlich 24-Stunden-Grenze,
  Auditmigration, Privacy-/PermissionProvider und Allow-/Deny-Vertrag in DDEV
  migrieren und auf Staging abnehmen.
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
  anwenden.
- Maßnahme nach Fristablauf, Sperren sowie Backup-/Restore- und
  Betroffenenrechtsregeln für Buchungen und Adminfreigabehistorie festlegen.
  Retention-Ausführung bleibt bis zu einem freigegebenen und getesteten
  Versions-, Wirksamkeitszeitpunkt-, Neuberechnungs-, Nebenläufigkeits-,
  Fehlerdiagnostik- und Roll-forward-Vertrag blockiert; bis dahin bleibt es
  bei der rein lesenden `REVIEW`-Vorschau ohne automatische Maßnahme.
- Für den bereits subjectgebunden ausgegebenen Nextcloud-`IUserConfig`-Wert
  `admin_dashboard_layout` einen autorisierten Reset- und Lifecycle-Vertrag
  festlegen.

## Geplante Erweiterungen

- Persönliche Einstellungen erhalten erst bei einem konkreten dauerhaften Nutzerwert einen eigenen App-Tab.
- Optionale Direktbuchungen aus Kalender oder Assistenzplanung können nach einem konkreten Anwendungsfall ergänzt werden; der manuelle Standalone-Betrieb bleibt erhalten.
- Änderungen an fremden Buchungen werden vorerst weiterhin direkt außerhalb der App zwischen den Beteiligten abgestimmt. Ein späterer In-App-Anfrageworkflow wird erst bei einem konkreten Bedarf und nach einem eigenen Vertrag für Akteur*innen, Empfänger*innen, erforderliche Daten und Datenschutz, Zustände, Wiederholungen, Ablauf, Auditierung sowie serverseitige Autorisierung geprüft; eine Anfrage darf niemals automatisch die Buchung ändern.

## Vor der Umsetzung zu klären

- Ob und wie eine einzelne Buchung über eine Kalendertagsgrenze geführt wird;
  bis dahin bleiben Beginn und Ende auf denselben lokalen Kalendertag begrenzt.
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
