# Roadmap – AD Raumplaner

Diese Datei enthält ausschließlich offene Arbeit, zurückgestellte Vorhaben
und Freigabegates. Der aktuelle Funktionsumfang steht in `README.md`,
erledigte Änderungen in `CHANGELOG.md` und geltende Architektur in
`docs/architecture.md`.

## Nextcloud-Kompatibilitätsgate

### ROOM-NC-COMPAT – OpenDesk-Boden 33 und künftige Majors nachweisen

Status: `info.xml` bleibt bei 34/34; NC 33.0.7 ist nur statisch geprüft. Vor
`min-version="33"` müssen Fresh Install/Upgrade, DI, Migrationen,
Buchungs-/Kollisions- und Rechtepfade, temporärer Adminzugriff,
Privacy-/PermissionProvider, Kalenderprovider-Ausfall, Assets und sichtbare
Oberfläche grün sein. Jede weitere Major wird lückenlos über
`verify-nextcloud-future-compatibility` geprüft; unvollständige Provider
werden sichtbar und nicht als Kompatibilitätserfolg gewertet.

## Systemweit gegatete app-lokale Aufgabe

### ROOM-L10N – Oberfläche und Kalenderdarstellung lokalisieren

Aktivierung ausschließlich nach Freigabe des Root-Vorhabens `ZM-06`.
Sichtbare Texte sowie Monats- und Wochentagsnamen werden app-lokal auf
Nextcloud-l10n umgestellt; ISO-Zeiträume, 5-Minuten-Raster, Zweck-Schlüssel,
Raum-IDs und API-Werte bleiben sprachneutral. Konfigurierte Titel und
Raumnamen werden nicht automatisch übersetzt.

## Aktueller Fokus

- App-lokalen temporären Admin-Vollzugriff einschließlich 24-Stunden-Grenze,
  Auditmigration, Privacy-/PermissionProvider und Allow-/Deny-Vertrag in DDEV
  migrieren und auf Staging abnehmen.
- Die manuellen Prüfungen werden im ausfüllbaren
  [`docs/manual-acceptance.md`](docs/manual-acceptance.md) dokumentiert.
- Monatsansicht, Kollisionsschutz, eigene Buchungsrechte und administrative Raumverwaltung auf einem realitätsnahen Staging fachlich abnehmen.
- Löschbestätigung, Zeitraster, Wochenenden und die Feiertage der administrativ gewählten Organisationsregion sichtbar und barrierefrei prüfen.

## Geplante Erweiterungen

- Persönliche Einstellungen erhalten erst bei einem konkreten dauerhaften Nutzerwert einen eigenen App-Tab.
- Optionale Direktbuchungen aus Kalender oder Assistenzplanung können nach einem konkreten Anwendungsfall ergänzt werden; der manuelle Standalone-Betrieb bleibt erhalten.

## Vor der Umsetzung zu klären

- Ob und wie eine einzelne Buchung über eine Kalendertagsgrenze geführt wird;
  bis dahin bleiben Beginn und Ende auf denselben lokalen Kalendertag begrenzt.
- Fachlicher Auslöser, Zielraum, Zeitraum und Besitzer*in einer Direktbuchung.
- Serverseitige Rechte, Konfliktverhalten und Rückmeldung an die aufrufende App.
- Kleiner optionaler Integrationsvertrag ohne direkten Zugriff auf fremde Tabellen oder Assets.
