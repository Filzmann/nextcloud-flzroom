# Roadmap – Filzmann Raumplaner

## Offene Fachberechtigung für Organisationskräfte und Sekretariat

Der Zugriff ist serverseitig auf die von `Datenschutzbeauftragte`
konfigurierten Nextcloud-Gruppen begrenzt; Organisationskräfte verwalten eigene
Buchungen. Freie Titel sehen nur die buchende Person und `flz-Sekretariat`. Die
kanonische Gruppe `flz-Sekretariat` erhält davon getrennte
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

## Zeitnah: Rebranding zu Filzmann Rooms und App-Store-Vorbereitung

Ziel ist die spätere erste öffentliche Veröffentlichung der bestehenden
Raumbuchungs-/Ressourcen-App als **Filzmann Rooms** im offiziellen Nextcloud
App Store. Dieser Plan gibt weder eine Veröffentlichung noch eine
Store-Registrierung, einen Zertifikatsantrag, eine Signierung oder einen Upload
frei. Die Umsetzung erfolgt in getrennten, jeweils ausdrücklich beauftragten
Arbeitsschritten.

| Reihenfolge | Arbeitspaket | `execution_class` | Ziel und Abschlussgate |
| --- | --- | --- | --- |
| ROOM-STORE-01 | Aktuelle Store-Anforderungen und Produktfreigabe prüfen | `COMPLEX` | Zu Beginn die dann aktuellen offiziellen Nextcloud-Vorgaben für Store-Zulassung, `info.xml`, erlaubte APIs und Abhängigkeiten, Lizenz/Urheberrecht/Marken, Paketaufbau, Kompatibilität, Code Signing und Releaseprozess neu erheben. App-ID und öffentliche Namen erst nach Verfügbarkeits-, Konflikt- und Eignungsprüfung festschreiben; die zeitabhängigen Vorgaben werden vor dem Release Candidate erneut verifiziert. |
| ROOM-STORE-02 | Zielidentität und Umbenennungsmatrix festlegen | `COMPLEX` | Den sichtbaren Namen **Filzmann Rooms** sowie die technischen Zielwerte für App-ID, PHP-Namespace, interne Identifier, Routen-/Asset-/Konfigurationsschlüssel, Übersetzungsdomäne, Paket-, Archiv- und Repository-Namen in einer einzigen Umbenennungsmatrix festhalten. Alle heutigen `flzroom`-/`FlzRoom`-/Filzmann-Raumplaner-Bezüge und ihre Consumer werden inventarisiert. Erhaltungsbedarf, Fresh-Install-/Reinstall-Weg und Rückbau werden vor jeder technischen Umbenennung entschieden; andere Repositories werden nur in jeweils separat freigegebenen Aufträgen angepasst. |
| ROOM-STORE-03 | Standalone-Migrationsgrenze festlegen | `COMPLEX` | Eine Abhängigkeitsmanifestation aller PHP-Klassen, Browser-Assets, Templates, Events, Konfigurations-/Datenquellen und Testhilfen erstellen und nach ADR 0001 einordnen. Filzmann Rooms muss mit allein seinem Store-Paket auf einer sauberen Nextcloud-Installation vollständig nutzbar sein. Für heute kernfunktionsrelevante Kategorie-B-Bezüge wird eine Nextcloud-native oder app-eigene führende Standalone-Quelle entschieden; gebündelte Kategorie-A-Bestandteile liegen reproduzierbar und isoliert im eigenen Paket. Andere Filzmann-Apps einschließlich LocalBase, OrgSuite, Kalender, Assistenzplanung und Datenschutz-Center bleiben ausschließlich optionale, aktivierungs- und versionsgeprüfte Integrationen. Direkte heutige LocalBase-Klassen- und Assetbezüge sind bis zum positiven Standalone-Nachweis Releaseblocker. |
| ROOM-STORE-04 | Rebranding und Standalone-Umstellung umsetzen | `COMPLEX` | Nach Freigabe von Umbenennungs- und Migrationsmatrix die App-ID-/Namespace-/Identifier-Umstellung, App-/Admin-/Kommando- und UI-Namen, Repository-/Buildprojektionen sowie die Entkopplung der Kernfunktion in einem kontrollierten Breaking-Change-Lauf konsistent umsetzen. Provider, Processing-Metadaten, Berechtigungsprojektionen, Tests, Fixtures und Consumer-Verträge werden auf semantische Gleichheit geprüft; technische Identifier werden nicht allein wegen ihres alten Namens ungeprüft geändert. Das Ergebnis besitzt keine gemischte alte und neue Produktidentität und ist per sauberer Installation beziehungsweise dem zuvor belegten Erhaltungsweg nachgewiesen. |
| ROOM-STORE-05 | Zweisprachige Produkt- und Store-Dokumentation erstellen | `STANDARD` | `info.xml`, README, Dokumentationsindex und fachliche Dokumentation, About-/Info-Bereich sowie die erforderlichen Store-Metadaten auf Filzmann Rooms ausrichten. Die Store-Beschreibung wird vollständig auf Deutsch und Englisch erstellt; Deutsch ist die primäre Fassung für den deutschen Markt, Englisch die vollständige internationale Fassung. Die bereits zurückgestellte vollständige UI-Lokalisierung `ROOM-L10N` wird dadurch nicht vorgezogen. |
| ROOM-STORE-06 | Qualitätsdarstellung und Release Evidence vervollständigen | `STANDARD` | Einen datensparsamen Nachweisindex aus den bestehenden Tests, Harness-Regeln und Release-Evidence-Quellen ableiten, statt parallele Nachweisstrukturen aufzubauen. Belegbar darzustellen sind insbesondere serverseitige Berechtigungen, Least Privilege, Privacy/Security by Design, der vollständige Standalone-Betrieb sowie versionsbezogene Tests. Übergreifende Qualitätsinformationen verweisen auf [Filzmann](https://simonbeyer.de/filzmann/) und [Qualität, Sicherheit und Nachweise](https://simonbeyer.de/filzmann/qualitaet-sicherheit-nachweise/). |
| ROOM-STORE-07 | Paket- und Release-Candidate-Gate ausführen | `COMPLEX` | Den bestehenden Packaging-, Kompatibilitäts- und Release-Evidence-Weg für genau eine korrekte App-Wurzel wiederverwenden und nur um noch nicht abgedeckte Store-Fehlerklassen erweitern. Zu belegen sind unter anderem reproduzierbarer Build, vollständige Produktionsartefakte, Lizenzinventar, Ausschluss von Entwicklungsdateien/Secrets/Schlüsseln, saubere Installation ohne andere Filzmann-App, Deaktivierung optionaler Provider sowie die dann freigegebene Nextcloud-Versionsmatrix. Signing wird nur ohne Zertifikatsantrag oder Zugriff auf private Schlüssel vorbereitet. |
| ROOM-STORE-08 | Externe Freigaben getrennt einholen | `COMPLEX` | Erst nach grünem internem Release Candidate werden Store-Registrierung, Zertifikatsantrag, tatsächliche Signierung, externe Veröffentlichung und Upload jeweils als eigene spätere Freigabeschritte beauftragt. Keiner dieser Schritte ist durch die Aufnahme in diese Roadmap autorisiert. |

Andere Filzmann-Produkte werden nur dezent in Store-Beschreibung, README,
Dokumentation und gegebenenfalls im About-/Info-Bereich erwähnt. Der normale
Buchungs-, Administrations- und Einstellungsablauf bleibt frei von
produktübergreifender Werbung.

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
