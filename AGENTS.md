# AGENTS.md - AD Raumplaner

## Projekt

Nextcloud-App `adroom` fuer die gemeinsame Buchung und Verwaltung von Besprechungsraeumen.

Lokale App-URL:

    https://nextcloud-dev.ddev.site/apps/adroom/

Nextcloud-App-ID:

    adroom

Die priorisierte Produktplanung und offene Entscheidungen stehen in `ROADMAP.md`; verbindliche Fach-, Sicherheits- und Architekturregeln bleiben in dieser Datei.

## Fachvertrag

- Der Monatsplan zeigt Tage als Zeilen und aktive Räume als Spalten. Innerhalb jedes Tages teilen sich alle Raumspalten eine vertikale Zeitachse: aufeinanderfolgende Buchungen stehen untereinander, zeitliche Lücken erzeugen Abstand und Buchungen verschiedener Räume bleiben zeitlich vergleichbar ausgerichtet.
- Buchungen bestehen aus Raum, Beginn, Ende, standardisiertem Zweck, frei benennbarem Titel und der Nextcloud-UID der buchenden Person. Der Titel bezeichnet zum Beispiel ASN, Gremium oder Fortbildungsthema.
- Als häufige Zwecke werden AT, Sitzung, BQ, Fortbildung, SV, HB und LG angeboten; die Liste bleibt durch die freie Texteingabe erweiterbar.
- Buchungen liegen innerhalb eines Kalendertags und verwenden 5-Minuten-Schritte ohne pauschale Einschränkung auf bestimmte Tageszeiten.
- Buchungen desselben Raums duerfen sich nicht ueberschneiden. Angrenzende Buchungen sind erlaubt.
- Alle angemeldeten Nutzer*innen duerfen Raeume und Buchungen lesen sowie eigene Buchungen anlegen, bearbeiten, in andere Raeume verschieben und loeschen.
- Nextcloud-Admins duerfen alle Buchungen und die Raumliste verwalten.
- Raumloeschungen loeschen die zugehoerigen Buchungen. Die UI muss diese Auswirkung vor der Aktion deutlich bestaetigen.
- Samstage, Sonntage und die gesetzlichen Feiertage der zentral in LocalBase konfigurierten Organisationsregion werden in der Monatsansicht textlich und optisch gekennzeichnet. Ohne abweichende Administration gilt Berlin.
- Buchungszeiten und Monatsgrenzen verwenden die zentral konfigurierte fachliche Organisationszeitzone. Persönliche Nextcloud-Zeitzonen verändern nur die individuelle Anzeige, nicht den fachlichen Buchungskontext.
- Der WordPress-Raumplaner ist nur fachliche Referenz. WordPress-IDs, Capabilities, Nonces, Shortcodes und Tabellen werden nicht uebernommen.
- WordPress-Bestandsdaten werden nicht importiert. Der app-eigene Adminabschnitt installiert neutrale Räume und Buchungen ausschließlich als manuell bestätigten synthetischen Demo-Pack.
- Beispielbuchungen gehören einem explizit registrierten lokalen Demokonto; ein vorhandenes fremdes oder LDAP-verwaltetes Konto wird niemals dafür wiederverwendet.

## Architektur und Sicherheit

- AD Raumplaner registriert seinen app-lokalen `PersonalDataProvider` lazy
  über den öffentlichen V1-Registry-Event der optionalen App
  `filzmann_data_protection`. Fehlt oder ist diese App deaktiviert, bleibt der
  Raumplaner einschließlich seines LocalBase-basierten
  `RetentionProvider`-Dry-Runs funktionsfähig und instanziiert den
  PersonalDataProvider nicht. Auskünfte fragen Buchungen ausschließlich nach
  der typisierten Subject-UID ab; die Datenschutz-App greift nie direkt auf
  Raumtabellen oder Repositorys zu. Retention liefert im Pilot nur
  `REVIEW`-Kandidaten und verändert keine Buchung. Aktivierung und Frist nach
  Buchungsende sind im eigenen Adminbereich bearbeitbar; andere Maßnahmen
  werden serverseitig abgelehnt.
- Jede Buchung erscheint in der persönlichen Auskunft menschenlesbar mit
  Datum, Uhrzeit und Raum, konkretem Zweck sowie einer aus der aktuellen
  Retention-Regel abgeleiteten Aussage. Ein REVIEW-Stichtag wird nicht als
  automatische Löschfrist dargestellt. Der freie Buchungstitel wird wegen
  möglicher Drittpersonenangaben durch einen neutralen, nicht rückauflösbaren
  Platzhalter ersetzt; Raum, Zweck und Zeit erhalten den Buchungskontext.
- Adminblöcke sind zugänglich klappbar und per Tastatur oder Drag-and-drop
  verschiebbar. Ihre persönliche Anordnung verändert keine Fachwerte.
- Controller bleiben duenn. Validierung und Kollisionspruefung liegen im `BookingService`, Rechte im `RoomAccessService`, Datenzugriff in Repositories.
- `HolidayService` ist nur ein app-spezifischer Projektionsadapter auf den gemeinsamen, zwischengespeicherten LocalBase-Feiertagskalender; AD Raumplaner pflegt keine eigene Feiertagsquelle oder Regionstabelle.
- Deny by default: Jede schreibende API prueft die angemeldete Person und die Zielbuchung serverseitig.
- Der Browser uebermittelt bei eigenen Buchungen keine vertrauenswuerdige Besitzer-UID; der Server setzt die UID aus der Session.
- GET-Routen sind CSRF-frei, schreibende Routen behalten den Nextcloud-CSRF-Schutz.
- Persistente Modelle bieten `get(...)`, `get_all([...])` und `toArray()`; direkte Modellpersistenz ist nicht erlaubt.
- QueryBuilder-Parameter werden gebunden. Keine SQL-Fragmente aus Requests.
- Der App-Root erfuellt den Nextcloud-Scrollvertrag; nur die Monatsmatrix scrollt horizontal.
- Die Raumverwaltung betrifft ausschließlich den AD Raumplaner und liegt deshalb in dessen eigenem Nextcloud-Adminabschnitt `AD Raumplaner`. Der Raumkalender enthält nur fachliche Buchungsfunktionen; künftige persönliche Einstellungen gehören in einen eigenen App-Tab.

## Gemeinsame Suite-Navigation

- Ohne aktive OrgSuite registriert AD Raumplaner einen eigenen Nextcloud-Hauptnavigationseintrag. Ab zwei AD-Produkten ersetzt `orgsuite` diesen durch den gemeinsamen Einstieg `AD`.
- Das Template stellt den optionalen Menühost mit `data-suite="ad"` und `data-current-app="adroom"` bereit, lädt aber keine OrgSuite-Assets direkt.
- Ohne Kalender oder Assistenzplanung bleiben Raumbuchungen vollständig manuell nutzbar; optionale Direktbuchungen dürfen nicht als harte Abhängigkeit modelliert werden.
- Menuesichtbarkeit ist keine Berechtigung.

## Git, DDEV und Tests

- Eigenstaendiges Git-Repository. Diese Datei und lokal referenzierte Skills bilden bei einem direkten Start die vollständige Repository-Steuerung.
- Fuer Git-, Sandbox-, DDEV-/`occ`-Sicherheit, Verifikation und Learning Candidates gilt der lokal mitgefuehrte Skill `work-in-nextcloud-app`; die folgenden Raumplaner-Regeln und Pruefungen ergaenzen ihn.
- DDEV-Mount: `/var/www/html/html/custom_apps/adroom`.
- Schnelle Tests: `php tests/run.php` und `node tests/run-js.mjs`.
- Controller-, DI- und Migrationsaenderungen zusaetzlich in DDEV pruefen.

## Parent-Governance-Vertrag: 1

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
