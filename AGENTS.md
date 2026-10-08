# AGENTS.md - Filzmann Raumplaner

## Projekt

Nextcloud-App `flzroom` fuer die gemeinsame Buchung und Verwaltung von Besprechungsraeumen.

Lokale App-URL:

    https://nextcloud-dev.ddev.site/apps/flzroom/

Nextcloud-App-ID:

    flzroom

Die priorisierte Produktplanung und offene Entscheidungen stehen in `ROADMAP.md`; verbindliche Fach-, Sicherheits- und Architekturregeln bleiben in dieser Datei.

## Fachvertrag

- Der Monatsplan zeigt Tage als Zeilen und aktive Räume als Spalten. Innerhalb jedes Tages teilen sich alle Raumspalten eine vertikale Zeitachse: aufeinanderfolgende Buchungen stehen untereinander, zeitliche Lücken erzeugen Abstand und Buchungen verschiedener Räume bleiben zeitlich vergleichbar ausgerichtet.
- Buchungen bestehen aus Raum, Beginn, Ende, frei eingebbarem Zweck, frei benennbarem Titel und der Nextcloud-UID der buchenden Person. Häufige Zwecke dürfen als unverbindliche Eingabehilfe angeboten werden, bilden aber keinen geschlossenen Katalog.
- Zweck und Titel sind auf die für die Raumkoordination erforderlichen Angaben zu begrenzen. Die Oberfläche warnt sichtbar davor, Namen, Gesundheits-, Fall- oder andere unnötige Drittpersonenangaben in die Freitextfelder einzutragen.
- Buchungen liegen innerhalb eines Kalendertags und verwenden 5-Minuten-Schritte ohne pauschale Einschränkung auf bestimmte Tageszeiten.
- Buchungen desselben Raums duerfen sich nicht ueberschneiden. Angrenzende Buchungen sind erlaubt.
- Nur Mitglieder der von `Datenschutzbeauftragte` app-lokal konfigurierten, bestehenden Nextcloud-Gruppen erhalten Zugriff auf den Raumplaner; reine Assistenzkräfte erhalten keinen Zugriff. Nextcloud bleibt Quelle der Gruppen und Mitgliedschaften. Organisationskräfte dürfen Räume und Buchungen lesen sowie eigene Buchungen anlegen, bearbeiten, in andere Räume verschieben und löschen. Eine leere oder fehlerhafte Konfiguration wirkt deny by default; der zeitlich freigegebene Adminpfad bleibt die dokumentierte Ausnahme.
- Die kanonische LocalBase-Gruppe `flz-Sekretariat` bildet die app-lokale Fachrolle Sekretariat. Sie ist von temporärem technischem Admin-Vollzugriff getrennt und darf Räume verwalten sowie bei organisatorischen Konflikten fremde Buchungen ändern oder löschen.
- Jeder Eingriff des Sekretariats in eine fremde Buchung verlangt vor der Änderung eine Begründung und einen app-lokal auditierbaren Nachweis. Die betroffene buchende Person erhält eine datensparsame Benachrichtigung mit altem und, soweit anwendbar, neuem Raum und Zeitraum sowie der Begründung; Angaben zu anderen Personen oder Buchungen bleiben ausgeschlossen.
- Für eine direkte Abstimmung dürfen berechtigte Organisationskräfte die buchende Person und den frei eingegebenen Zweck einer Buchung sehen. Der freie Titel wird serverseitig ausschließlich der buchenden Person und Mitgliedern von `flz-Sekretariat` ausgegeben. Diese Sichtbarkeit erteilt kein Änderungsrecht und erweitert die Datenanzeige nicht über den erforderlichen Koordinationskontext hinaus.
- Native Nextcloud-Administration erteilt keinen automatischen fachlichen
  Vollzugriff. Ein konkretes Administrationskonto darf alle Buchungen und die
  Raumliste ausschließlich mit einer app-lokalen, serverseitig geprüften und
  höchstens 24 Stunden gültigen Freigabe verwalten. Beginn, geplantes Ende,
  Widerruf und tatsächliches Ende bleiben in der app-eigenen Auditquelle
  erhalten. Der technische Zugriff auf den Nextcloud-Adminabschnitt bleibt
  davon getrennt.
- Raumloeschungen loeschen die zugehoerigen Buchungen. Die UI muss diese Auswirkung vor der Aktion deutlich bestaetigen.
- Samstage, Sonntage und die gesetzlichen Feiertage der zentral in LocalBase konfigurierten Organisationsregion werden in der Monatsansicht textlich und optisch gekennzeichnet. Ohne abweichende Administration gilt Berlin.
- Buchungszeiten und Monatsgrenzen verwenden die zentral konfigurierte fachliche Organisationszeitzone. Persönliche Nextcloud-Zeitzonen verändern nur die individuelle Anzeige, nicht den fachlichen Buchungskontext.
- Der WordPress-Raumplaner ist nur fachliche Referenz. WordPress-IDs, Capabilities, Nonces, Shortcodes und Tabellen werden nicht uebernommen.
- WordPress-Bestandsdaten werden nicht importiert. Der app-eigene Adminabschnitt installiert neutrale Räume und Buchungen ausschließlich als manuell bestätigten synthetischen Demo-Pack.
- Beispielbuchungen gehören einem explizit registrierten lokalen Demokonto; ein vorhandenes fremdes oder LDAP-verwaltetes Konto wird niemals dafür wiederverwendet.

## Architektur und Sicherheit

- Filzmann Raumplaner registriert seinen app-lokalen `PersonalDataProvider` lazy
  über den öffentlichen V1-Registry-Event der optionalen App
  `flz_data_protection`. Fehlt oder ist diese App deaktiviert, bleibt der
  Raumplaner funktionsfähig und instanziiert die Datenschutzprovider nicht.
  Der app-lokale `RetentionProvider` registriert sich ebenfalls lazy über den
  öffentlichen V1-Vertrag des Datenschutz-Centers; seine globale,
  fortsetzbare Vorschau liefert ausschließlich datenminimierte
  `REVIEW`-Kandidaten und verändert keine Buchungen. Auskünfte fragen
  Buchungen ausschließlich nach
  der typisierten Subject-UID ab; die Datenschutz-App greift nie direkt auf
  Raumtabellen oder Repositorys zu. Aktivierung und Frist nach
  Buchungsende sind im eigenen Adminbereich bearbeitbar; andere Maßnahmen
  werden serverseitig abgelehnt.
- Der app-eigene Processing-Katalog liegt ausschließlich unter
  `resources/privacy-processing.json` und wird über den zusätzlichen
  öffentlichen V1-`ProcessingMetadataProvider` des Datenschutz-Centers lazy
  registriert. Er enthält nur Policy-Metadaten, niemals personenbezogene
  Laufzeitdaten. Buchungs-, Adminfreigabe- und persönliche Layoutverarbeitung
  bleiben dort vollständig aufgeführt; ungeklärte Rechtsgrundlagen,
  Zuständigkeiten, Retention-, Backup- und Betroffenenrechtsfragen bleiben
  `PRIVACY-DECISION-REQUIRED` und aktivieren keine technische Maßnahme.
- Jede Buchung erscheint in der persönlichen Auskunft menschenlesbar mit
  Datum, Uhrzeit und Raum, konkretem Zweck sowie einer aus der aktuellen
  Retention-Regel abgeleiteten Aussage. Ein REVIEW-Stichtag wird nicht als
  automatische Löschfrist dargestellt. Der freie Buchungstitel wird wegen
  möglicher Drittpersonenangaben durch einen neutralen, nicht rückauflösbaren
  Platzhalter ersetzt; Raum, Zweck und Zeit erhalten den Buchungskontext.
- Adminblöcke sind zugänglich klappbar und per Tastatur oder Drag-and-drop
  verschiebbar. Ihre persönliche Anordnung verändert keine Fachwerte.
- Controller bleiben duenn. Validierung und Kollisionspruefung liegen im `BookingService`, Rechte im `RoomAccessService`, Datenzugriff in Repositories.
- `HolidayService` ist nur ein app-spezifischer Projektionsadapter auf den gemeinsamen, zwischengespeicherten LocalBase-Feiertagskalender; Filzmann Raumplaner pflegt keine eigene Feiertagsquelle oder Regionstabelle.
- Deny by default: Jede schreibende API prueft die angemeldete Person und die Zielbuchung serverseitig.
- Temporärer Admin-Vollzugriff bleibt bewusst lokaler Raumplanervertrag ohne
  zentrale Runtime-Abhängigkeit. Fehlende, abgelaufene, widerrufene oder
  fehlerhaft gelesene Freigaben sowie entzogener nativer Adminstatus ergeben
  deny by default. Privacy- und PermissionProvider werden mit dieser
  Sicherheitsdatenklasse gemeinsam gepflegt.
- `PersonalDataProvider`, `ProcessingMetadataProvider`, Retention-Preview und
  `PermissionProvider` werden bei Änderungen ihrer Datenklassen und Scopes
  gemeinsam geprüft. Ein tatsächlich gespeichertes persönliches Adminlayout
  wird subjectgebunden ausgegeben; fremde und bloße Standardwerte bleiben aus
  der Art.-15-Projektion ausgeschlossen.
- Der Browser uebermittelt bei eigenen Buchungen keine vertrauenswuerdige Besitzer-UID; der Server setzt die UID aus der Session.
- GET-Routen sind CSRF-frei, schreibende Routen behalten den Nextcloud-CSRF-Schutz.
- Persistente Modelle bieten `get(...)`, `get_all([...])` und `toArray()`; direkte Modellpersistenz ist nicht erlaubt.
- QueryBuilder-Parameter werden gebunden. Keine SQL-Fragmente aus Requests.
- Der App-Root erfuellt den Nextcloud-Scrollvertrag; nur die Monatsmatrix scrollt horizontal.
- Die Raumverwaltung betrifft ausschließlich den Filzmann Raumplaner und liegt deshalb in dessen eigenem Nextcloud-Adminabschnitt `Filzmann Raumplaner`. Der Raumkalender enthält nur fachliche Buchungsfunktionen; künftige persönliche Einstellungen gehören in einen eigenen App-Tab.

## Gemeinsame Suite-Navigation

- Ohne aktive OrgSuite registriert Filzmann Raumplaner einen eigenen Nextcloud-Hauptnavigationseintrag. Ab zwei FLZ-Produkten ersetzt `orgsuite` diesen durch den gemeinsamen Einstieg `FLZ`.
- Das Template stellt den optionalen Menühost mit `data-suite="flz"` und `data-current-app="flzroom"` bereit, lädt aber keine OrgSuite-Assets direkt.
- Ohne Kalender oder Assistenzplanung bleiben Raumbuchungen vollständig manuell nutzbar; optionale Direktbuchungen dürfen nicht als harte Abhängigkeit modelliert werden.
- Menuesichtbarkeit ist keine Berechtigung.

## Git, DDEV und Tests

- Eigenstaendiges Git-Repository. Diese Datei und lokal referenzierte Skills bilden bei einem direkten Start die vollständige Repository-Steuerung.
- Fuer Git-, Sandbox-, DDEV-/`occ`-Sicherheit, Verifikation und Learning Candidates gilt der lokal mitgefuehrte Skill `work-in-nextcloud-app`; die folgenden Raumplaner-Regeln und Pruefungen ergaenzen ihn.
- DDEV-Mount: `/var/www/html/html/custom_apps/flzroom`.
- Schnelle Tests: `php tests/run.php` und `node tests/run-js.mjs`.
- Controller-, DI- und Migrationsaenderungen zusaetzlich in DDEV pruefen.

## Dokumentenverantwortung

- `README.md` beschreibt ausschließlich den aktuellen nutzbaren Stand,
  Installation, Betrieb, Tests und den Dokumentationsindex.
- `ROADMAP.md` enthält ausschließlich offene, zurückgestellte oder
  freigabepflichtige Arbeit und Entscheidungen.
- `CHANGELOG.md` dokumentiert erledigte Änderungen releasebezogen; erledigte
  Checklisten verbleiben nicht in der Roadmap.
- `docs/architecture.md` ist die ausführliche Quelle für geltende fachliche
  und technische Architekturverträge.
- `docs/manual-acceptance.md` enthält wiederholbare manuelle Prüfungen und
  keine Produktplanung.
- `AGENTS.md` enthält ausschließlich verbindliche Arbeits-, Sicherheits-,
  Architektur- und Prüfregeln. Zusätzliche Dokumente werden in `README.md`
  mit eindeutiger Zuständigkeit eingeordnet.

## Parent-Governance-Vertrag: 2

- Die für dieses Subrepository anwendbaren Regeln des Parent-Workspaces sind
  verbindlich. Dazu gehören insbesondere app-übergreifende ADRs und
  öffentliche Verträge, Repositorygrenzen sowie Workspace-, Delivery- und
  Release-Gates.
- Diese lokale `AGENTS.md` und die lokalen Skills bleiben die vollständige,
  ohne Parent-Checkout arbeitsfähige Repository-Steuerung. Die anwendbaren
  Parent-Regeln werden dafür hier oder in den lokalen Skills mitgeführt.
- Repository-lokale Regeln dürfen Parent-Verträge konkretisieren und verschärfen,
  aber nicht abschwächen oder umgehen.
- Bei einem Widerspruch gilt bis zur Klärung die strengere Regel. Die Arbeit
  stoppt, bis die kanonische Quelle bestimmt, die Regelprojektionen
  synchronisiert und eine erforderliche Entscheidung dokumentiert ist.
- Ist der Parent-Workspace nicht verfügbar, bleibt die lokale Steuerung
  wirksam. Vor Cross-App-, Release- oder Delivery-Arbeit muss ein vermuteter
  neuerer Parent-Stand oder eine Regelungslücke zuerst gegen den Parent
  geprüft werden.

### Entwicklungsphase und Kompatibilitätsbedarf

Entscheidung vom 5. September 2026: Das Gesamtprojekt befindet sich vollständig
in der Entwicklung. Es gibt kein PROD, keinen produktiven Datenbestand und
keinen bereits betriebenen Bestand mit zu erhaltendem Upgradepfad. STAGING
ist eine wegwerfbare Entwicklungs- und Integrationsumgebung und darf im
konkret beauftragten Reinstall vollständig neu aufgebaut werden. Wenige
externe Testnutzer ändern diese Einordnung nicht.

Vor einer Datenmigration, Legacy-Unterstützung, Compatibility Layer,
Deprecated API, Dual-Read/Dual-Write, einem Altschema-Fallback, Übergangsformat
oder der Unterstützung historischer Entwicklungsstände wird geprüft:

1. Wurde der betroffene Zustand jemals produktiv eingesetzt?
2. Benötigen reale Daten oder Nutzer seine Erhaltung?
3. Gibt es einen anderen konkreten technischen Erhaltungsgrund, insbesondere
   einen geltenden Plattform- oder externen API-Vertrag?

Sind alle relevanten Antworten nein, ist die saubere Breaking-Change-/
Reinstall-Lösung der Standard. Frühere rein interne Entwicklungsstände
begründen weder Abwärtskompatibilität noch eine Deprecationfrist.
Entwicklungsschemata dürfen durch ein kanonisches Installationsschema ersetzt,
alte interne APIs und Konfigurationsformate samt ausschließlich dafür
benötigten Adaptern und Tests entfernt werden. Architekturqualität und der
saubere Zielzustand haben Vorrang. Nextclouds nötige Installationsmigrationen
bleiben erhalten; ein Verzeichnisname `Migration` beweist keine Altlast.

Breaking Changes werden im selben Änderungskontext vollständig durchgezogen:
betroffene Provider, Consumer, standardisierte APIs, Vertragsversionen,
Metadaten, Tests und Dokumentation müssen zusammenpassen. Unterstützte
Nextcloud-/openDesk-Plattformverträge, externe Standards, Autorisierung und
Datenschutz gelten unverändert. Fehlende oder inkompatible optionale Provider
bleiben kontrolliert sichtbar. Ein Reinstall erlaubt keine privaten
Fremdtabellenzugriffe oder parallel erfundenen Plattformmechanismen.

Vor destruktiver Arbeit werden die tatsächlich benötigten externen
Testidentitäten, Gruppen, Rollen und nicht reproduzierbaren Testdaten gezielt
gesichert oder über bestehende native Setup-Strukturen reproduzierbar gemacht.
Echte Personen- und Zugangsdaten bleiben außerhalb von Git. Diese begrenzte
Sicherung begründet keine allgemeine Legacy-Unterstützung. Ein Reinstall
bleibt ein normaler unterstützter Entwicklungsweg; der vorhandene
Compatibility-Workflow besitzt den Fresh-Install-Nachweis, dessen aktueller
Belegstatus in `docs/workspace.md` beschrieben ist.

Diese Phase endet ausschließlich durch einen ausdrücklich dokumentierten,
von Simon freigegebenen **Production-Readiness-/Production-Freeze-Entscheid**.
Ein Release Candidate, eine Versionsnummer, ein Staging-Deployment oder ein
externer Testzugang lösen den Wechsel nicht aus. Der Entscheid wird in dieser
kanonischen Lifecycle-Quelle mit Datum, Geltungsbereich und betroffenem
Versions-/Datenstand festgehalten und in die lokale Steuerung projiziert.
Dann werden Upgradepfade, Datenbankmigrationen, Persistenz, Backup/Restore,
Rollback, Release-/API-Kompatibilitätszusagen, Deployment-/Freigabeprozess und
PROD→STAGING/COPY-Strategie neu bewertet. Eine vollständige PROD-Governance
wird jetzt nicht vorweggenommen.

Diese Regel entscheidet den Kompatibilitätsbedarf, erweitert aber keinen
Repository-Schreibauftrag und ersetzt keine Freigabe für eine konkrete
destruktive Aktion. Lokale Regelprojektionen folgen dem bestehenden
`docs/parent-governance-contract.md`; ein unsynchronisierter Einzel-Checkout
darf keinen abweichenden Phasenstand stillschweigend annehmen.

### Prüfaufwand

- Vor einem neuen Test, Scan, Linter, Architektur- oder Systemcheck wird
  geprüft, welcher bestehende Check dieselbe Eigenschaft bereits nachweist.
  Diesen erweitern oder sein nachweislich passendes Ergebnis wiederverwenden;
  ein zusätzlicher Check braucht eine benannte zusätzliche Fehlerklasse oder
  Vertrauensgrenze. Gleicher Input, gleiche Prüfung, gleiche Fehlerklasse und
  gleiche Phase begründen keinen zweiten Lauf. Gestaffelte Unit-, Contract-
  und Runtime-Nachweise bleiben erhalten. Die dokumentierten lokalen
  Prüfeinstiege bestimmen Umfang und Ergebnisgültigkeit.
