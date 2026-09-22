# AD Raumplaner

Monatliche, zeitlich ausgerichtete Raumbelegung mit kollisionsfreien Buchungen, Buchungstiteln und standardisierten Zwecken.

## Staging-Kompatibilität

- Nextcloud 33 bis 34
- PHP 8.3 oder neuer innerhalb des von Nextcloud 33 bis 34 unterstützten Bereichs
- Laufzeitbasis: `localbase`; `orgsuite` ist ab zwei AD-Fachprodukten optional aktiv
- App-ID und Installationsordner: `adroom`

## Installation

Für Staging und Auslieferung das Produktbundle `ad-product-adroom-<release>.tar.gz` und dessen enthaltenes `install.sh` verwenden. Es prüft und installiert LocalBase automatisch; ab dem zweiten AD-Fachprodukt aktiviert es OrgSuite.

AD Raumplaner funktioniert einzeln; Buchungen bleiben ohne Kalender oder Assistenzplanung manuell nutzbar.

Räume werden nach der Aktivierung im eigenen Nextcloud-Adminabschnitt `AD Raumplaner` eingerichtet. `adroom:demo:seed` ist ausschließlich für synthetische Testdaten bestimmt.

Native Nextcloud-Administration besitzt keinen automatischen Vollzugriff auf
Raumdaten. Ausschließlich Mitglieder der kanonischen Nextcloud-Gruppe
`Datenschutzbeauftragte` können in der Hauptoberfläche ein aktuelles
Administrationskonto für höchstens 24 Stunden freischalten oder die Freigabe
vorzeitig widerrufen. Nativer Adminstatus allein genügt dafür nicht. Beginn,
geplantes Ende und tatsächliches Ende bleiben app-lokal protokolliert; ohne
aktive Freigabe sind Raumverwaltung, Fremdbuchungen und Demo-Installation
gesperrt. Der sichere Eintrittshinweis ist nur für betroffene native
Administrationskonten sichtbar und verlinkt die Freigabesteuerung nur bei
gleichzeitiger Datenschutzrolle.

Feiertage, Buchungszeiten und Monatsgrenzen richten sich nach dem gemeinsamen Kalenderkontext der AD-Suite. Land, Region und fachliche Zeitzone werden zentral durch die Administration gepflegt; ohne Änderung gilt Deutschland/Berlin.

## Datenschutz

AD Raumplaner registriert bei aktivem, kompatiblem Datenschutz-Center seinen
subjectgebundenen `PersonalDataProvider` und zusätzlich einen versionierten
`ProcessingMetadataProvider`. Der app-eigene Katalog beschreibt
Raumbuchungen, temporäre Adminfreigaben und die persönliche Adminanordnung,
ohne personenbezogene Laufzeitdaten zu enthalten. Offene fachliche
Entscheidungen bleiben ausdrücklich `PRIVACY-DECISION-REQUIRED`; insbesondere
werden daraus keine automatische Löschung oder Anonymisierung abgeleitet.

## Roadmap

Geplante Erweiterungen und offene Produktentscheidungen stehen in der [Roadmap](ROADMAP.md).

Für die fachliche, visuelle und sicherheitsbezogene Staging-Prüfung steht ein
ausfüllbares [manuelles Abnahmeformular](docs/manual-acceptance.md) bereit.
Vertrauliche Besprechungstitel und personenbezogene Echtdaten werden darin
nicht dokumentiert.

Installations-, Betriebs- und Abnahmeunterlagen stehen im öffentlichen [AD-Suite-Projekt](https://github.com/Filzmann/ad-suite).

## Dokumentation

- [Architektur](docs/architecture.md)
- [Manuelle Abnahme](docs/manual-acceptance.md)
- [Roadmap](ROADMAP.md)
- [Changelog](CHANGELOG.md)
- [Arbeitsregeln](AGENTS.md)
