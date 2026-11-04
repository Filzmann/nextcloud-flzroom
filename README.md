# Filzmann Raumplaner

Monatliche, zeitlich ausgerichtete Raumbelegung mit kollisionsfreien Buchungen, Buchungstiteln und standardisierten Zwecken.

## Staging-Kompatibilität

- Nextcloud 33 bis 35
- PHP 8.3 oder neuer innerhalb des von Nextcloud 33 bis 35 unterstützten Bereichs
- Laufzeitbasis: `localbase`; `orgsuite` ist ab zwei FLZ-Fachprodukten optional aktiv
- App-ID und Installationsordner: `flzroom`

## Installation

Für Staging und Auslieferung das Produktbundle `flz-product-flzroom-<release>.tar.gz` und dessen enthaltenes `install.sh` verwenden. Es prüft und installiert LocalBase automatisch; ab dem zweiten FLZ-Fachprodukt aktiviert es OrgSuite.

Filzmann Raumplaner funktioniert einzeln; Buchungen bleiben ohne Kalender oder Assistenzplanung manuell nutzbar.

Räume werden nach der Aktivierung im eigenen Nextcloud-Adminabschnitt `Filzmann Raumplaner` eingerichtet. `flzroom:demo:seed` ist ausschließlich für synthetische Testdaten bestimmt.

Der Raumplan verwendet getrennte app-lokale Einzelrechte und semantische
Organigrammrollen aus dem öffentlichen LocalBase-V1-Snapshot. Standardmäßig
dürfen alle Organigrammrollen außer `flz-AS` und `flz-ASN-*` den Raumplan lesen,
eigene Buchungen verwalten und freie Titel lesen. `Datenschutzbeauftragte`
können die Rollen je Einzelrecht im App-Tab `Einstellungen` zuordnen, ohne
Gruppenmitgliedschaften zu verändern. Ein fehlender oder ungültiger Snapshot
oder eine ungültige Zuordnung sperrt den regulären Zugriff fail-closed.

Fremde Buchungen dürfen standardmäßig Sekretariat, (stellvertretende)
Büroleitungen, (stellvertretende) PDL und Geschäftsführung ausschließlich mit
Begründung und zusätzlicher Risikoskop-Freigabe bearbeiten. Räume verwalten
native Nextcloud-Administrationskonten und standardmäßig das Sekretariat; das
erteilt keinen Zugriff auf fremde Buchungen. Alle Buchungen bleiben nur für
native Admins mit aktiver, höchstens 24 Stunden gültiger app-lokaler Freigabe
verwaltbar.

Native Nextcloud-Administration besitzt keinen automatischen Vollzugriff auf
Buchungsdaten; die ausdrücklich getrennte Raumstammdatenverwaltung bleibt
davon unberührt. Ausschließlich Mitglieder der kanonischen Nextcloud-Gruppe
`Datenschutzbeauftragte` können in der Hauptoberfläche ein aktuelles
Administrationskonto für höchstens 24 Stunden freischalten oder die Freigabe
vorzeitig widerrufen. Nativer Adminstatus allein genügt dafür nicht. Beginn,
geplantes Ende und tatsächliches Ende bleiben app-lokal protokolliert; ohne
aktive Freigabe sind die Verwaltung aller Buchungen und die Demo-Installation
gesperrt. Der sichere Eintrittshinweis ist nur für betroffene native
Administrationskonten sichtbar und verlinkt die Freigabesteuerung nur bei
gleichzeitiger Datenschutzrolle.

Der technische Guard für den begründeten organisatorischen Fremdeingriff in
fremde Buchungen fragt optional den neutralen V1-Risikoscope des
Datenschutz-Centers ab. Fehlende, deaktivierte, abgelaufene oder inkompatible
Freigaben sperren fail-closed; der Raumplaner erhält keine kundenlokalen
Policy-, Vereinbarungs- oder DPO-Daten. Zugewiesene Rollen – standardmäßig
Sekretariat, (stellvertretende) Büroleitungen, (stellvertretende) PDL und
Geschäftsführung – können bei aktiver Risikoskop-Freigabe fremde Buchungen mit
einer Begründung von 10 bis 500 Zeichen ändern oder löschen. Mutation,
minimiertes Audit und persistente
Benachrichtigungsqueue werden atomar gespeichert. Das Audit wird nach zwölf
Monaten gelöscht; Zustellungen werden nach fünf Minuten, einer Stunde und 24
Stunden wiederholt, erfolgreiche Queuezeilen sofort und dauerhaft
fehlgeschlagene Einträge nach 30 Tagen gelöscht. Eigene Buchungen, normale
Raumfunktionen und der getrennte temporäre Admin-Vollzugriff bleiben davon
unabhängig.

Feiertage, Buchungszeiten und Monatsgrenzen richten sich nach dem gemeinsamen Kalenderkontext der Filzmann Nextcloud Plugins. Land, Region und fachliche Zeitzone werden zentral durch die Administration gepflegt; ohne Änderung gilt Deutschland/Berlin.

## Datenschutz

Filzmann Raumplaner registriert bei aktivem, kompatiblem Datenschutz-Center seinen
subjectgebundenen `PersonalDataProvider` und zusätzlich einen versionierten
`ProcessingMetadataProvider` sowie den ausschließlich lesenden
V1-`RetentionProvider`. Die globale Retention-Vorschau ist paginiert,
liefert nur datenminimierte `REVIEW`-Kandidaten und verändert keine
Buchungen oder Adminfreigaben. Die von `Datenschutzbeauftragte` versioniert
konfigurierten Standardfristen betragen ein Jahr ab Buchungsende und sechs
Monate ab tatsächlichem Ende einer Adminfreigabe; jede Änderung wird bei der
nächsten Vorschau anhand des ursprünglichen Endes neu berechnet. Fehlt das
Datenschutz-Center oder ist es inkompatibel, bleibt der
Raumplaner eigenständig nutzbar und der fehlende Provider wird nicht
verschleiert. Der app-eigene Katalog beschreibt
Raumbuchungen, temporäre Adminfreigaben und die persönliche Adminanordnung,
ohne personenbezogene Laufzeitdaten zu enthalten. Offene fachliche
Entscheidungen bleiben ausdrücklich `PRIVACY-DECISION-REQUIRED`; insbesondere
werden daraus keine automatische Löschung oder Anonymisierung abgeleitet.
Das Produktpaket enthält außerdem keine kunden- oder instanzspezifische
Rechtsgrundlage, verantwortliche Organisation, Vereinbarung oder Evidenz;
diese Zuordnung ist je Installation außerhalb des Pakets zu dokumentieren.
Der getrennte V2-Provider löscht nach gültiger technischer Aktivierung im
Datenschutz-Center fällige Buchungen und beendete Adminfreigabehistorien in
der jeweils app-eigenen Transaktion. Die isolierte Nextcloud-34-/PostgreSQL-16-
Runtime-Abnahme belegt Fresh Install, Reinstall, Provider-Discovery, echte
Löschung, aktive Holds, Job-Wiederanlauf und Idempotenz, Nebenläufigkeit,
Rollback vor Commit sowie Restore-Quarantäne. Mangels autoritativem früheren
V2-Runtime-Snapshot wird kein historischer Upgradepfad behauptet; der für die
Entwicklungsphase kanonische Reinstall ist nachgewiesen.

## Roadmap

Geplante Erweiterungen und offene Produktentscheidungen stehen in der [Roadmap](ROADMAP.md).

Für die fachliche, visuelle und sicherheitsbezogene Staging-Prüfung steht ein
ausfüllbares [manuelles Abnahmeformular](docs/manual-acceptance.md) bereit.
Vertrauliche Besprechungstitel und personenbezogene Echtdaten werden darin
nicht dokumentiert.

Installations-, Betriebs- und Abnahmeunterlagen stehen im öffentlichen [Filzmann Nextcloud Plugins-Projekt](https://github.com/Filzmann/flz-full-suite).

## Dokumentation

- [Architektur](docs/architecture.md)
- [Manuelle Abnahme](docs/manual-acceptance.md)
- [Roadmap](ROADMAP.md)
- [Changelog](CHANGELOG.md)
- [Arbeitsregeln](AGENTS.md)
