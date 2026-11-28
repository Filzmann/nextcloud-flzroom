# Changelog

## 0.13.0 (2026-11-28)

- Release für den offiziellen Nextcloud App Store: stabile Version 0.13.0
  (vorher 0.13.0-rc.2); Store-Artefakt rc3/rc4 über `scripts/build-app-store-candidate.sh`
  mit reproduzierbarem sha256 und Evidence geprüft.

## Unreleased

- Lifecycle des persönlichen Admin-Layouts (`admin_dashboard_layout`):
  Self-Reset nur durch den Kontoinhaber (`DELETE /api/admin/layout`,
  CSRF-geschützt), Löschung bei Kontolöschung (`UserDeletedEvent`) und bei
  App-Deinstallation (Uninstall-Repair-Step `UninstallLayoutCleanup`).

- L10N-Migration (ZM-06): sichtbare Texte über Nextcloud-l10n (deutsche Quelltexte, Kataloge `l10n/en.*` + `l10n/en_GB.*`), inkl. `tests/Ui/L10nCatalogContractTest.php` und `docs/l10n-inventory.md`.

- Die Datenschutzrolle kann die Retention für Fremdeingriffe ändern: Audit
  standardmäßig ein Jahr (30 bis 1095 Tage oder ein bis drei Kalenderjahre),
  dauerhaft fehlgeschlagene Benachrichtigungen standardmäßig 30 Tage (ein bis
  365 Tage). Neue Revisionen gelten auch für bestehende Datensätze; verkürzte
  Fristen können beim nächsten Joblauf früher löschen. Erfolgreiche Queuezeilen
  werden sofort entfernt. DPO-Zugriff, ungültige und veraltete Revisionen,
  Verlängerung und Verkürzung wurden isoliert mit synthetischen Daten geprüft.
- `InterventionNotificationJob` zusätzlich dauerhaft in `appinfo/info.xml`
  registriert. Damit wird er auch bei App-Upgrades erkannt, wenn die bereits
  gelaufene Migration nicht erneut ausgeführt wird; die Migration bleibt
  idempotent. Ein isolierter SQLite-Lauf prüft Fresh Install, den früheren
  Upgradefehler ohne Metadatendeklaration (Red), die behobene Deklaration
  (Green) und wiederholte Upgrades mit genau einer Registrierung. Der Test
  behauptet keinen zeitlichen TDD-Nachweis für die ursprüngliche Änderung.
- Den absoluten Nextcloud-Link in der nativen Eingriffsbenachrichtigung über
  `IURLGenerator` erzeugt. Der echte Nextcloud-34-Notifier hatte den zuvor
  relativen Link abgewiesen; der Integrationstest reproduzierte den Fehler
  rot und ist mit dem nativen URL-Generator grün.
- Eine isolierte Nextcloud-34-Laufzeit mit ausschließlich synthetischen
  Testkonten prüfte Fremdeingriff und verweigerte Fälle, Auditbeschränkung auf
  Datenschutzbeauftragte, native Benachrichtigung samt Rendering, Joblauf,
  idempotente Wiederholung sowie konfigurierbare Löschung nach Audit- und
  Fehlerfristen. Die synthetische Scope-Fixture war ausdrücklich keine echte
  Datenschutzbestätigung; Klon und Testdaten wurden anschließend entfernt.
- Die lokale DDEV-Prüfung bestätigt jetzt eine gültige Organisationsdefinition
  aus `flz_organization` sowie gültigen Raumrollen- und Berechtigungskatalog.
  Eine echte DPO-Entscheidung für die Risikoskop-Freigabe liegt nicht vor und
  wurde durch die isolierte Testfixture nicht ersetzt.

- Neue Charakterisierungstests unter `tests/js/runtime-behavior-tests.mjs`
  prüfen das bestehende Verhalten von `js/admin-access.js`, `js/admin.js`,
  `js/main.js`, `js/retention-policy.js` und `js/room-management-settings.js`
  mit positiven und negativen Assertions (gerendertes DOM, Request-Payloads,
  Fehler- und Leerzustände); das Parent-Gate `scripts/check-js-runtime-coverage`
  für flzroom ist damit grün.
- Die Kategorie-A- und Einstellungstests ordnen ausgeführte JavaScript-Dateien
  über ihre absoluten Quellpfade zuverlässig der Coverage zu.
- Gemeinsame Browser-Primitives als reproduzierbares, app-lokal isoliertes
  Kategorie-A-Bundle unter `window.OCA.FlzRoom.Shared` eingebunden. Modelle,
  Repository und API-/UI-Basis laden keine privaten LocalBase-Kategorie-A-
  Assets oder Dateien aus einem benachbarten Quellrepository mehr; der
  getrennte Kategorie-B-Dashboardvertrag bleibt unverändert.
- V2-DELETE-Provider gemeinsam mit dem Datenschutz-Center isoliert auf
  Nextcloud 34.0.2 und PostgreSQL 16 abgenommen: Fresh Install, Reinstall,
  Provider-Discovery, technische Fail-closed-Aktivierung, echte Löschung,
  Holds, Job-Wiederanlauf und Idempotenz, Nebenläufigkeit, Rollback vor Commit
  und Restore-Quarantäne sind grün. Ein historischer Upgradepfad wird mangels
  autoritativem früheren V2-Runtime-Snapshot nicht behauptet; der kanonische
  Entwicklungs-Reinstall ist nachgewiesen.
- Organigrammrollen und Raumplaner-Berechtigungen getrennt: DPO-geschützte,
  zugängliche Rechtematrix mit fünf Einzelrechten statt freier Gruppen-IDs,
  serverseitig gegen den öffentlichen Organisations-Snapshot von flz_organization
  validiert. Die Standardrechte bilden alle bestätigten Rollen einschließlich
  AS-/ASN-Ausschluss, begründeten Fremdeingriff, DPO-Steuerung und getrennten
  Admin-Vollzugriff ab; Provider- und Konfigurationsfehler wirken fail-closed.
- Feiertage werden ausschließlich aus dem öffentlichen V1-Snapshot von
  `flz_organization` projiziert. Fehlende, deaktivierte, inkompatible,
  unbrauchbare oder ausgefallene Provider erzeugen keine behaupteten
  Feiertage und keinen LocalBase-Fallback; Providerfehler bleiben mit
  datensparsamem Kontext diagnostizierbar.
- Die lazy V1-Registrierung des read-only Retentionproviders verwendet wieder
  den vollständigen Namespace des öffentlichen Datenschutzvertrags.
- Rollenübergreifende Fremdeingriffe werden in Oberfläche, Benachrichtigung
  und Datenschutz-Auskunft neutral bezeichnet; Verzeichnisfehler der
  serverseitigen Rechteprüfung bleiben fail-closed und datensparsam
  diagnostizierbar.
- Raumstammdaten sind über eine buchungsdatenfreie API für native Admins und
  standardmäßig das Sekretariat verwaltbar. Nichtadministrative Berechtigte
  nutzen dafür den App-Tab `Einstellungen`; fachlicher Vollzugriff auf fremde
  Buchungen bleibt an die zeitlich begrenzte Adminfreigabe gebunden.
- Nextcloud 35.0.1 durch Fresh Install, Upgrade 34→35 sowie Provider-,
  Berechtigungs-, Runtime-, UI-/API- und Asset-Smokes nachgewiesen und den
  unterstützten Bereich auf die lückenlosen Hauptversionen 33 bis 35
  erweitert. Der Permission-Provider-Listener erfüllt nun Nextclouds
  öffentlichen `IEventListener`-Vertrag. Die Vorschau dauerhaft
  fehlgeschlagener Zustellungen verwendet den gültigen Retention-Trigger
  `COMPLETED_AT`; Berechnung ab `failedAt`, 30-Tage-Frist und reine
  `REVIEW`-Semantik bleiben unverändert. Nextcloud 36 bleibt ungeprüft.
- Fail-closed Consumer-Guard für den neutralen öffentlichen V1-Risikoscope
  des Datenschutz-Centers ergänzt. Er verlangt für den künftigen
  organisatorischen Fremdeingriff zugleich Fachrolle, fremde Zielbuchung und eine
  kompatible aktive Scope-Freigabe, erhält keine kundenlokalen Policydetails
  und hält kundenlokale Policydetails aus der Fachapp heraus.
- Begründete Fremdeingriffe durch zugewiesene Organigrammrollen vollständig angebunden:
  10–500 Zeichen Pflichtbegründung, atomare Buchungsänderung mit append-only
  Audit und persistenter Nextcloud-Benachrichtigungsqueue, Wiederholungen nach
  5 Minuten, 1 Stunde und 24 Stunden sowie feste Löschung des Audits nach 12
  Monaten und dauerhaft fehlgeschlagener Queueeinträge nach 30 Tagen.
- Eingriffsaudit auf `Datenschutzbeauftragte` begrenzt und Processing-Katalog,
  PersonalDataProvider, PermissionProvider sowie Retention-Vorschau um die
  neuen Datenklassen ergänzt; kundenlokale Vereinbarungen oder BR-Gruppen
  werden nicht hardcodiert.
- Raumplan, eigene Buchungen und freie Buchungstitel serverseitig über
  getrennte App-Rechte an semantische Organigrammrollen gebunden; eine leere
  oder ungültige Zuordnung sperrt reguläre Zugriffe.
- Retention-Dry-Run vom LocalBase-Pilot auf den öffentlichen
  V1-Providervertrag des Datenschutz-Centers migriert; globale Vorschauen sind
  paginiert, datenminimiert und weiterhin strikt `REVIEW`-only.
- Fristen für Raumbuchungen und Adminfreigabehistorien ausschließlich für
  `Datenschutzbeauftragte` gemeinsam versioniert, mit Wirksamkeitszeitpunkt,
  optimistischer Revision und jährlichem Review geführt; die Vorschau wertet
  vorhandene Datensätze vom ursprünglichen Ende mit der aktuellen Version neu
  aus und bietet weiterhin keinen Ausführungspfad.
- Temporären fachlichen Admin-Vollzugriff auf höchstens 24 Stunden begrenzt:
  Nur `Datenschutzbeauftragte` dürfen aktuelle Nextcloud-Administrationskonten
  in der Hauptoberfläche freigeben oder widerrufen; native Administration
  allein bleibt ohne Freigabe- und Historienzugriff.
- App-eigenen Processing-Metadatenkatalog für Raumbuchungen,
  Adminfreigabehistorie und persönliche Adminanordnung sowie dessen optionalen
  öffentlichen V1-Provider ergänzt; offene Datenschutzentscheidungen bleiben
  sichtbar und lösen keine automatische Maßnahme aus.
- Tatsächlich gespeicherte persönliche Adminanordnungen subjectgebunden in die
  Art.-15-Auskunft aufgenommen; fremde und bloße Standardwerte bleiben außen vor.
- Nextcloud 33.0.7 bis 34.0.2 durch Fresh Install und Upgrade 33→34 mit
  App-Suiten, DI-/Registrierungs-, API-, Rechte-, HTTPS-, Asset- und UI-Smokes unterstützt.
- Dokumentations- und Steuerungsstruktur vereinheitlicht.

## 0.12.0-rc.1

- Subjectgebundene persönliche Datenauskunft für eigene Raumbuchungen mit Zweck, Zeitraum und Aufbewahrungshinweis ergänzt.
- Aufbewahrungsregel und ausschließlich lesende `REVIEW`-Vorschau im zugänglich klapp- und verschiebbaren Adminbereich ergänzt.
- Buchungszeiten im Frontend an der fachlichen Organisationszeitzone ausgerichtet.

## 0.11.0-rc.1

- Raumzeiten auf ein durchgängiges 5-Minuten-Raster ohne feste 06–21-Uhr-Grenze erweitert; Buchungen bleiben bis zur Produktentscheidung auf einen Kalendertag begrenzt.
- Buchungsfehler im aktiven Dialog sichtbar gemacht sowie Escape-Schließen und Fokusrückgabe abgesichert.
- Monatsnavigation und zweidimensional scrollbare Raummatrix für kleine Viewports stabilisiert.

## 0.9.0-rc.1

- Eigenständige Navigation ohne OrgSuite ergänzt.
- Raumverfügbarkeits- und Buchungsfähigkeiten über optionale LocalBase-Verträge veröffentlicht.
- Ungültige harte App-Abhängigkeiten aus den Nextcloud-Metadaten entfernt.

## 0.8.8-rc.1

- Eigener Nextcloud-Adminabschnitt für die ausschließlich appbezogene Raumverwaltung.
- Öffentliche Projekt-, Quellcode- und Fehlerkanäle ergänzt.

## 0.8.7-rc.1

- Erster reproduzierbarer Staging-Releasekandidat für Nextcloud 34 und PHP ab 8.3.
- Zeitachsenansicht für mehrere Räume und kollisionsfreie Buchungen.
- Titel und frei erweiterbare standardisierte Buchungszwecke.
- Authentifizierter DOM-, CSRF-, Anlege-, Kollisions- und Lösch-Smoke.
