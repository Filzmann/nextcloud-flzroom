# Manuelles Abnahmeprotokoll – AD Raumplaner

## Kopfdaten

| Feld | Wert |
|---|---|
| Prüfer | Simon |
| Datum | 08.08.2026 |
| Anwendung | AD Raumplaner |
| App-Version | `0.10.0-rc.1` |
| URL | `https://nextcloud-dev.ddev.site/index.php/apps/adroom/` |
| Nextcloud | Nextcloud Hub 26 Spring – `34.0.2` |
| Browser | Google Chrome `150.0.7871.186`, 64-Bit, Ubuntu |
| Fenster / Zoom | ca. 2/3 von 1920 px Breite, 100 % |
| Admin-Testkonto | `admin` |
| Nicht-Admin-Testkonto | `adc-demo-bl-now` |
| Kalenderregion | `de_DE` |
| Fachliche Zeitzone | `Europe/Berlin` |
| Testdaten | ausschließlich synthetische Räume, Buchungen und Demo-Konten |

### Relevante App-Versionen im wiederhergestellten Normalzustand

| App | Version |
|---|---|
| `adcalendar` | `0.13.0-rc.5` |
| `adplaner` | `0.3.0-rc.2` |
| `adroom` | `0.10.0-rc.1` |
| `adurlaub` | `0.6.0-rc.2` |
| `localbase` | `0.10.0-rc.1` |
| `orgsuite` | `0.4.0-rc.1` |

## Gesamtergebnis

| Status | Anzahl |
|---|---:|
| Erfolgreich | **24** |
| Nicht erfolgreich | **5** |
| Nicht geprüft | **2** |
| Offen | **0** |
| Gesamt | **31** |

**Abnahmeurteil:** Noch nicht vollständig abnahmefähig.

Die Kernfunktionalität des Raumplaners sowie die geprüften serverseitigen Berechtigungs- und CSRF-Schutzmechanismen funktionieren weitgehend korrekt. Die noch offenen Abnahmehindernisse konzentrieren sich auf kleine Viewports/Scrolling, die Darstellung von Fehlermeldungen, Tastaturbedienung sowie das fachlich geänderte Soll für Zeitgrenzen und Zeitraster.

## Automatisierte Nacharbeit für `0.11.0-rc.1`

Die fünf negativen Befunde wurden im Code bearbeitet und automatisiert abgesichert:

- A7: App-Root ohne eigenen Seiten-Scroll, sticky Kopfbereich und eine in beide
  Richtungen scrollbare Matrix innerhalb des verbleibenden Viewports.
- B5/E5: Speicher- und Kollisionsfehler erscheinen als fokussierbare
  `role="alert"`-Meldung im geöffneten Buchungsdialog.
- B7: UI und Server verwenden ein 5-Minuten-Raster ohne 06–21-Uhr-Grenze;
  der reale HTTP-Smoke legt selbstbereinigend eine Buchung um 00:05 Uhr an.
- E4: Der native `cancel`-Pfad schließt den Dialog mit Escape und gibt den
  Fokus an das auslösende Element zurück.

PHP-, JavaScript-, Layout- und HTTP-Prüfungen sind für diese Verträge grün.
Das historische manuelle Ergebnis unten bleibt unverändert; A7, B5, B7, E4
und E5 benötigen vor dem Releaseurteil eine erneute Sicht- und
Tastaturprüfung im Browser. Buchungen über Mitternacht bleiben bis zur
Produktentscheidung ausdrücklich außerhalb des freigegebenen Vertrags.

## Browser- und Runtime-Nachprüfung vom 12. August 2026

Die Nachprüfung erfolgte lokal gegen Nextcloud `34.0.2` mit AD Raumplaner
`0.11.0-rc.1`, LocalBase `0.10.0-rc.2` und OrgSuite `0.4.0-rc.1`. Nextcloud
meldete keinen ausstehenden Datenbank-Upgrade. PHP-, JavaScript- und der
selbstbereinigende authentifizierte HTTP-Smoke waren grün. CSS und JavaScript
des Raumplaners wurden über den konfigurierten `custom_apps`-Webpfad jeweils
mit HTTP 200 und passendem Content-Type ausgeliefert.

Aktueller Nachweis der fünf nachgearbeiteten Befunde:

- **A7 erfolgreich nachgeprüft:** Bei einem realen Chrome-Viewport von
  390 × 844 Pixeln besaßen Dokument, Body und App-Root keinen horizontalen
  Seitenüberlauf. Ausschließlich die Matrix war mit 355 Pixeln sichtbarer und
  834 Pixeln tatsächlicher Breite horizontal scrollbar; sie blieb zugleich
  der vertikale Scrollcontainer für den Monatsinhalt.
- **B5 und E5 erfolgreich nachgeprüft:** Eine synthetische Buchung wurde über
  die echte Oberfläche angelegt. Der zweite identische Speicherversuch wurde
  als Überschneidung abgewiesen; die verständliche Meldung erschien sichtbar
  im weiterhin geöffneten Dialog als `role="alert"` und erhielt den Fokus.
  Die Testbuchung wurde danach erfolgreich über die geschützte API gelöscht.
- **B7 erfolgreich nachgeprüft:** Der authentifizierte HTTP-Smoke legte eine
  Buchung von 00:05 bis 00:10 Uhr an, erhielt für die Überschneidung HTTP 409
  und entfernte die Testbuchung anschließend. UI und Server akzeptieren damit
  das freigegebene 5-Minuten-Raster ohne die alte Grenze von 06:00 bis
  21:00 Uhr.
- **E4 teilweise nachgeprüft:** Ein im echten Browser-DOM ausgelöstes
  `cancel`-Ereignis schloss den Dialog und gab den Fokus an den auslösenden
  Button zurück. Chrome Headless erzeugte jedoch auch bei sichtbarem und
  fokussiertem Target aus dem über DevTools gesendeten Escape-Tastendruck kein
  natives `cancel`-Ereignis. Der physische Escape-Pfad bleibt deshalb bis zu
  einer manuellen Tastaturprüfung **nicht vollständig verifiziert**.
- **A6 erfolgreich nachgeprüft:** Eine synthetische Buchung von 03:05 bis
  03:10 Uhr wurde im echten Browser zunächst mit `Europe/Berlin` und danach
  mit `America/New_York` dargestellt. Beide Sitzungen zeigten unverändert
  `03:05–03:10`. Die Monats-API projiziert UTC-Bestand dazu in die kanonische
  Organisationszeitzone; Timeline, Tageszuordnung, Anzeige und
  Bearbeitungsdialog verwenden deren fachliche Wandzeit. Die Testbuchung
  wurde anschließend erfolgreich gelöscht.

Unverändert nicht geprüft bleibt E3 ohne LocalBase. E3 wird erst nach der
verbindlichen Runtime-/Standalone-Entscheidung neu definiert. Damit sind vier
der fünf früher negativen Befunde und A6 erfolgreich nachgeprüft; E4 ist
teilweise geprüft. Ein vollständiges Releaseurteil folgt erst nach der
manuellen Escape-Prüfung und dem bewusst noch offenen E3-Vertrag.

## Datenschutz-Pilotabnahme vom 12. August 2026

Für den lokalen Pilot wurde in Nextcloud die dedizierte Gruppe
`privacy-officer` angelegt, der bereits vorhandene DDEV-Benutzer `admin`
zugeordnet und `localbase/privacy_admin_group` auf diese Gruppe gesetzt. Es
wurde kein Benutzer neu angelegt. Die anschließende Laufzeitprüfung ergab:

- Die autorisierte Admin-Auskunft antwortete mit HTTP 200, meldete den
  registrierten Provider `adroom` als `complete` und den Gesamtbericht als
  vollständig.
- Die autorisierte Retention-Vorschau antwortete mit HTTP 200, meldete
  `adroom` als `complete` und bestätigte ausdrücklich `dryRun: true`.
- Es wurde keine Buchung gelöscht, anonymisiert oder anderweitig verändert.
  Ohne freigegebene Frist und Maßnahme liefert der Provider weiterhin nur
  `REVIEW`-Kandidaten.

Die zuvor geprüfte Verweigerung ohne konfigurierte Datenschutzgruppe bleibt
durch den Controller-Test belegt. Die Gruppen- und AppConfig-Änderung ist auf
die lokale DDEV-Instanz begrenzt und keine Vorgabe für Produktion.

---

# A – Einstieg, Darstellung und Navigation

## A1 – Standalone-Einstieg ohne OrgSuite

**Ergebnis:** ✅ Erfolgreich

**Prüfung:** OrgSuite wurde deaktiviert. Der eigene Nextcloud-Einstieg des Raumplaners war weiterhin vorhanden und nutzbar.

**Feststellungen:**
- Monatsansicht vollständig geladen.
- Räume und vorhandene Buchungen sichtbar.
- Buchungsablauf ohne OrgSuite funktionsfähig.
- Keine Abhängigkeit vom Suite-Einstieg für die Kernfunktion.

---

## A2 – Einstieg über OrgSuite

**Ergebnis:** ✅ Erfolgreich

**Prüfung:** OrgSuite wurde aktiviert und der gemeinsame AD-Einstieg verwendet.

**Feststellungen:**
- Raumplaner über den gemeinsamen Einstieg erreichbar.
- Raumplaner eindeutig als solcher gekennzeichnet.
- Kein doppelter bzw. konkurrierender Einstieg.
- Wechsel zwischen den Apps funktioniert.

---

## A3 – Monatsnavigation

**Ergebnis:** ✅ Erfolgreich

**Feststellungen:**
- Vorheriger Monat funktioniert.
- Nächster Monat funktioniert.
- Direkte Monatsauswahl funktioniert.
- Tage und Buchungen werden nach dem Wechsel korrekt dargestellt.

**Hinweis:** Es gibt keine separate Monatsüberschrift; der Monatsname wird in der direkten Monatsauswahl angezeigt. Das wurde nicht als Abnahmefehler bewertet.

---

## A4 – Gemeinsame Zeitachse

**Ergebnis:** ✅ Erfolgreich

**Feststellungen:**
- Räume werden als getrennte Spalten dargestellt.
- Zeitachse ist vertikal zwischen den Räumen ausgerichtet.
- Freie Zeiträume und Belegungen sind vergleichbar.
- Buchungen verschiedener Räume können unmittelbar zeitlich gegenübergestellt werden.

---

## A5 – Wochenenden und Feiertage

**Ergebnis:** ✅ Erfolgreich

**Feststellungen:**
- Samstage und Sonntage optisch gekennzeichnet.
- Feiertage optisch und textlich erkennbar.
- Darstellung grundsätzlich verständlich und zugänglich.

**Verbesserungsvorschlag:** Feiertage könnten deutlicher hervorgehoben werden, z. B. durch eine stärkere Hintergrundmarkierung analog zum Sonntag.

**Möglicher Ticket-Titel:** `Feiertagsmarkierung im Raumplaner deutlicher hervorheben`

---

## A6 – Fachliche Zeitzone

**Ergebnis:** ⏸️ Nicht geprüft

**Begründung:** Es wurde kein Benutzerkonto mit einer von `Europe/Berlin` abweichenden persönlichen Zeitzone eingerichtet. Der Test wurde wegen geringer Priorität nicht durchgeführt.

---

## A7 – Scrolling und kleiner Viewport

**Ergebnis:** ❌ Nicht erfolgreich

**Feststellungen:**
- Horizontaler Scroll innerhalb der Raum-Matrix funktioniert grundsätzlich.
- Vertikaler Scroll funktioniert grundsätzlich.
- Monatsnavigation/Aktionen bleiben bei kleinem Viewport nicht ausreichend erreichbar.
- Es erscheint zusätzlich ein zweiter horizontaler Seiten-Scrollbar.
- Der horizontale Scrollbar der Matrix ist erst am Ende des Inhalts sichtbar und steht während des normalen Scrollens nicht dauerhaft am unteren Viewportrand zur Verfügung.

**Erwartete Korrekturen:**
- Monatsnavigation sticky ausführen.
- Zweiten horizontalen Seiten-Scrollbar entfernen.
- Horizontalen Scrollbar der Matrix am unteren Viewportrand sichtbar/sticky halten, analog zur gewünschten Bedienung im AD Kalender.

**Möglicher Ticket-Titel:** `Raumplaner: Scrollcontainer und sticky Navigation für kleine Viewports korrigieren`

---

# B – Buchungen

## B1 – Eigene Buchung anlegen

**Ergebnis:** ✅ Erfolgreich

**Feststellungen:**
- Eine eigene Buchung konnte angelegt werden.
- Die Buchung erschien korrekt und blieb persistent.
- Keine Abweichungen gemeldet.

---

## B2 – Freier Zweck / Freitext

**Ergebnis:** ✅ Erfolgreich

**Testkonto:** `admin`
**Testraum:** Besprechungsraum Nord

**Feststellungen:**
- Freier Zweck/Freitext wird akzeptiert.
- Wert bleibt nach dem Speichern erhalten.
- Wert ist lesbar.
- Wert bleibt auch beim späteren Bearbeiten erhalten.

---

## B3 – Buchung ändern und Raum wechseln

**Ergebnis:** ✅ Erfolgreich

**Testkonto:** `admin`

**Feststellungen:**
- Buchung von Besprechungsraum Nord nach Besprechungsraum Süd verschoben.
- Zeitraum geändert.
- Alte Darstellung verschwand.
- Neue Darstellung blieb nach dem Speichern bestehen.
- Keine doppelte Buchung erzeugt.

---

## B4 – Direkt angrenzende Buchungen

**Ergebnis:** ✅ Erfolgreich

**Testkonto:** `admin`

**Prüfszenario:**
- bestehende Buchung: 10:00–12:00
- angrenzende Buchung davor: 08:00–10:00
- angrenzende Buchung danach: 12:00–14:00

**Feststellungen:**
- Beide angrenzenden Buchungen wurden akzeptiert.
- Keine falsche Überschneidungswarnung.
- Alle Buchungen blieben persistent.

**Verbesserungsvorschlag:** Nach Auswahl der Startzeit sollte die Endzeit automatisch etwa eine Stunde später vorbelegt werden und anschließend frei änderbar bleiben.

**Möglicher Ticket-Titel:** `Buchungsdialog: Endzeit automatisch eine Stunde nach Beginn vorbelegen`

---

## B5 – Überschneidungen

**Ergebnis:** ❌ Nicht erfolgreich

**Feststellungen:**
- Teilweise Überschneidung wird serverseitig korrekt abgewiesen.
- Vollständige Überschneidung wird korrekt abgewiesen.
- Eine die bestehende Buchung vollständig umfassende Überschneidung wird korrekt abgewiesen.
- Bestehende Buchung bleibt unverändert.
- Kein unvollständiger bzw. teilgespeicherter Zustand.
- **Fehlermeldung liegt jedoch hinter dem geöffneten Buchungsformular und ist dadurch für den Benutzer nicht sichtbar.**

**Möglicher Ticket-Titel:** `Validierungs- und Kollisionsmeldungen oberhalb des Buchungsdialogs anzeigen`

---

## B6 – Parallele Buchung in anderem Raum

**Ergebnis:** ✅ Erfolgreich

**Feststellungen:**
- Gleichzeitige Buchungen in Besprechungsraum Nord und Besprechungsraum Süd wurden akzeptiert.
- Beide Buchungen blieben bestehen.
- Raumzuordnung korrekt.
- Keine fälschliche raumübergreifende Kollisionsprüfung.

---

## B7 – Zeitgrenzen und Zeitraster

**Ergebnis:** ❌ Nicht erfolgreich

**Ursprüngliches Testsoll:** Buchungen vor 06:00 Uhr, nach 21:00 Uhr, über Nacht bzw. außerhalb des vorgesehenen Rasters sollten abgewiesen werden.

**Während der Abnahme fachlich angepasstes Soll:**
- Keine generelle Begrenzung auf 06:00–21:00 Uhr.
- Buchungen sollen auch nachts möglich sein, z. B. für Homeoffice-/Überstunden-Szenarien.
- Falls ein Zeitraster verwendet wird, soll dieses höchstens ca. 5 Minuten betragen.
- UI-Auswahl und serverseitige Validierung müssen konsistent sein.
- Zeiten, die das UI anbietet, dürfen anschließend nicht vom Server als unzulässig zurückgewiesen werden.
- Umgang mit Buchungen über Mitternacht ist noch fachlich endgültig festzulegen.

**Ist-Verhalten:**
- vor 06:00 Uhr nicht möglich.
- nach 21:00 Uhr nicht möglich.
- über Nacht nicht möglich.
- Rasterprüfung vorhanden.
- Keine inkonsistenten Teilzustände.
- Rasterfehler sichtbar.

**Bewertung:** Das Ist-Verhalten entspricht nicht dem im Test aktualisierten fachlichen Soll.

**Mögliche Tickets:**
- `Raumplaner: Harte Buchungsgrenze 06:00–21:00 entfernen`
- `Raumplaner: Zeitraster zwischen UI und Server vereinheitlichen`

---

## B8 – Eigene Buchung löschen

**Ergebnis:** ✅ Erfolgreich

**Feststellungen:**
- Eigene Buchung konnte gelöscht werden.
- Keine Abweichungen gemeldet.

---

# C – Berechtigungen und Sicherheit bei Buchungen

## C1 – Fremde Buchung lesen

**Ergebnis:** ✅ Erfolgreich

**Prüfung:** Fremde Buchung eines anderen Kontos wurde mit dem Nicht-Admin-Testkonto `adc-demo-bl-now` betrachtet.

**Feststellungen:**
- Fremde Buchung sichtbar.
- Zeit sichtbar.
- Titel/Zweck sichtbar.
- Eigentümerinformation sichtbar.
- Leserechte entsprechen dem fachlichen Modell.

---

## C2 – Fremde Buchung verändern

**Ergebnis:** ✅ Erfolgreich

**UI-Prüfung:**
- Nicht-Admin konnte fremde Buchung weder bearbeiten noch verschieben noch löschen.

**Direkte API-Prüfung mit `adc-demo-bl-now`:**
- `PUT` zum Bearbeiten einer fremden Buchung: **HTTP 403**
- `PUT` zum Verschieben in einen anderen Raum: **HTTP 403**
- `DELETE` der fremden Buchung: **HTTP 403**
- Antwort jeweils sinngemäß: `Keine Berechtigung.`

**Feststellung:** Die Buchung blieb unverändert. Die Berechtigung wird nicht nur im Frontend, sondern serverseitig durchgesetzt.

---

## C3 – Besitzer aus Sitzung statt Requestdaten

**Ergebnis:** ✅ Erfolgreich

**Prüfung:** Als `adc-demo-bl-now` wurde per direktem API-Request eine Buchung erzeugt und im Payload versucht, `userUid: admin` mitzugeben.

**Ergebnis:**
- Server legte die Buchung erfolgreich an.
- Eigentümer wurde trotzdem das tatsächlich angemeldete Konto `adc-demo-bl-now`.
- Der manipulierte `userUid` aus dem Request wurde nicht als Besitzer übernommen.

**Bewertung:** Besitzerzuordnung erfolgt vertrauenswürdig aus der Sitzung.

---

## C4 – Administratorzugriff auf fremde Buchungen

**Ergebnis:** ✅ Erfolgreich

**Feststellung:** `admin` durfte eine Buchung bearbeiten, die dem Nicht-Admin-Testkonto `adc-demo-bl-now` gehörte.

**Hinweis:** Einzelne Unterpunkte wie administratives Löschen wurden nicht separat protokolliert; bestätigt wurde die administrative Bearbeitbarkeit und damit das vorgesehene Admin-Rechtemodell.

---

## C5 – CSRF-Schutz bei Buchungen

**Ergebnis:** ✅ Erfolgreich

**Prüfung:** Schreibender `PUT`-Request auf eine Buchung ohne Requesttoken.

**Ergebnis:**
- HTTP **412 Precondition Failed**
- Antwort: `{"message":"CSRF check failed"}`
- Bestehende Buchung blieb unverändert.

**Bewertung:** Serverseitiger CSRF-Schutz für Buchungsänderungen wirksam.

---

# D – Raumverwaltung und Demo-Daten

## D1 – Adminbereich / Raumverwaltung

**Ergebnis:** ✅ Erfolgreich

**Prüfung als Admin:**
- Synthetischer Raum angelegt.
- Raum nach Reload weiterhin vorhanden.
- Raum in Monatsansicht sichtbar.

**Prüfung als Nicht-Admin:**
- Raumadministration in der Oberfläche nicht verfügbar.
- Direkter `PUT`-Request als `adc-demo-bl-now` auf einen Raum mit gültigem Requesttoken: **HTTP 403**
- Antwort: `{"message":"Das angemeldete Konto muss ein Administrator sein"}`
- Raum blieb unverändert.

**Bewertung:** Adminrechte werden UI-seitig und serverseitig korrekt durchgesetzt.

---

## D2 – Raumdaten und Reihenfolge

**Ergebnis:** ✅ Erfolgreich

**Feststellungen:**
- Namen mehrerer synthetischer Räume geändert.
- Beschreibungen geändert.
- `sortOrder` verändert.
- Änderungen nach Neuladen korrekt übernommen.
- Monatsansicht folgte der neuen Reihenfolge.
- Raumauswahl im Buchungsdialog folgte der neuen Reihenfolge.
- Bestehende Buchungen blieben dem richtigen Raum zugeordnet.
- Keine Buchung wurde allein durch Umbenennung oder Sortierung verschoben.
- Keine Abweichungen gemeldet.

---

## D3 – Löschbestätigung

**Ergebnis:** ✅ Erfolgreich

**Testraum:** `sem 2`

**Feststellungen:**
- Testraum enthielt eine Testbuchung.
- Erste Löschwarnung wurde abgebrochen.
- Raum blieb nach Abbruch erhalten.
- Buchung blieb nach Abbruch erhalten.
- Löschwirkung wurde verständlich angekündigt.
- Löschung anschließend bewusst bestätigt.
- Raum wurde entfernt.
- Zugehörige Testbuchung wurde entfernt.
- Andere Räume und Buchungen blieben unverändert.

---

## D4 – CSRF-Schutz der Raumverwaltung

**Ergebnis:** ✅ Erfolgreich

**Prüfung:** Schreibender `PUT`-Request auf die Raumverwaltung ohne Requesttoken.

**Ergebnis:**
- HTTP **412 Precondition Failed**
- Antwort: `{"message":"CSRF check failed"}`

**Hinweis:** Eine zusätzliche Sichtkontrolle des Raumzustands wurde nach diesem Request nicht separat protokolliert. Die serverseitige Ablehnung des schreibenden Requests ist eindeutig nachgewiesen.

---

## D5 – Demo-Pack-Schutz

**Ergebnis:** ✅ Erfolgreich

**Feststellungen:**
- Installation ohne erforderliche Bestätigung blockiert.
- Ohne Bestätigung wurden keine Daten erzeugt.
- Installation nach ausdrücklicher Bestätigung möglich.
- Nur synthetische Räume erzeugt.
- Nur synthetische Buchungen erzeugt.
- Lokales Demokonto synthetisch.
- Bestehende Daten unverändert.
- Erneute Installation sicher behandelt; keine unkontrollierte Duplizierung gemeldet.

---

# E – Robustheit, Bedienbarkeit und Datenminimierung

## E1 – Betrieb ohne AD Kalender

**Ergebnis:** ✅ Erfolgreich

**Prüfung:** `adcalendar` vorübergehend deaktiviert.

**Feststellungen:**
- Monatsansicht vollständig geladen.
- Räume und vorhandene Buchungen sichtbar.
- Neue Buchung möglich.
- Änderung möglich.
- Löschung möglich.
- Monats- und Raumwechsel möglich.
- Fehlender AD Kalender erzeugte keinen Fehler.
- Buchungen wurden dadurch nicht blockiert.

---

## E2 – Betrieb ohne AdPlaner

**Ergebnis:** ✅ Erfolgreich

**Prüfung:** `adplaner` vorübergehend deaktiviert.

**Feststellungen:**
- Monatsansicht vollständig geladen.
- Räume und vorhandene Buchungen sichtbar.
- Neue Buchung möglich.
- Änderung möglich.
- Löschung möglich.
- Monats- und Raumwechsel möglich.
- Fehlender AdPlaner erzeugte keinen Fehler.
- Buchungen wurden dadurch nicht blockiert.

---

## E3 – Kalenderkontext / LocalBase nicht verfügbar

**Ergebnis:** ⏸️ Nicht geprüft

**Hintergrund:**
- Ein erster Versuch, `localbase` vollständig zu deaktivieren, führte beim Raumplaner zu HTTP 500.
- Anschließend wurde festgestellt, dass LocalBase möglicherweise als harte technische Abhängigkeit gedacht ist.
- Gleichzeitig sind an dieser Architektur bzw. den Abhängigkeiten noch Umstellungen vorgesehen.

**Bewertung:** Der erzeugte Zustand wird deshalb derzeit nicht als fachlich gültiger Abnahmetest gewertet. E3 bleibt bewusst **nicht geprüft** und soll nach Festlegung der Zielarchitektur neu definiert und wiederholt werden.

**Nicht als Abnahmefehler gewertet:** HTTP 500 beim künstlichen vollständigen Abschalten von LocalBase.

---

## E4 – Tastatur und Fokus

**Ergebnis:** ❌ Nicht erfolgreich

**Feststellung:**
- Das Buchungs-/Formular-Overlay lässt sich nicht mit `Esc` schließen.

**Hinweis:** Weitere Teilkriterien zur Tastaturbedienung wurden nicht vollständig einzeln dokumentiert und deshalb nicht pauschal negativ bewertet.

**Möglicher Ticket-Titel:** `Raumplaner: Formular-Overlay muss per Escape schließbar sein`

---

## E5 – Verständliche Fehlermeldungen

**Ergebnis:** ❌ Nicht erfolgreich

**Feststellung:**
- Kollisionsprüfung selbst funktioniert.
- Die dabei erzeugte Fehlermeldung befindet sich jedoch hinter dem geöffneten Formular-Overlay und ist für den Benutzer nicht wahrnehmbar.

**Hinweis:** Weitere Teilkriterien wie leerer Titel, fehlender Raum oder weitere ungültige Eingaben wurden nicht separat protokolliert und deshalb nicht zusätzlich bewertet.

**Möglicher Ticket-Titel:** `Raumplaner: Validierungsfehler im aktiven Formular sichtbar anzeigen`

---

## E6 – Datenminimierung

**Ergebnis:** ✅ Erfolgreich

**Prüfung:** Monats-API als Nicht-Admin-Testkonto `adc-demo-bl-now`.

**Übertragene Daten umfassen im Wesentlichen:**
- Raum-ID, Raumname, Beschreibung und Sortierung.
- Buchungs-ID und Raum-ID.
- Eigentümer-UID und Anzeigename.
- Zweck und Titel.
- Start- und Endzeit.
- `canManage`.
- Fähigkeit `canManageRooms`.

**Nicht festgestellt:**
- keine E-Mail-Adressen.
- keine Telefonnummern.
- keine Gruppenlisten.
- keine zusätzlichen Rolleninformationen.
- keine weitergehenden Profildaten.
- keine sachfremden personenbezogenen Zusatzinformationen.

**Bewertung:** Die Antwort enthält keine erkennbar unnötigen personenbezogenen Informationen für die getestete Funktion.

**Optimierungsmöglichkeit:** Es kann geprüft werden, ob `userUid` bei fremden Buchungen im Frontend tatsächlich benötigt wird, da `canManage` bereits serverseitig geliefert wird. Dies wurde nicht als Abnahmefehler bewertet.

---

# Zusammenfassung der nicht erfolgreichen Prüffälle

## 1. A7 – Scrolling / kleiner Viewport

**Problem:** Navigation und horizontale Scrollführung sind bei kleinem Viewport nicht ausreichend ergonomisch.

**Erforderlich:**
- sticky Monatsnavigation,
- kein zweiter Seiten-Scrollbar,
- horizontaler Matrix-Scrollbar am Viewport erreichbar.

---

## 2. B5 – Überschneidungen / Fehlermeldung

**Problem:** Serverseitige Kollisionsprüfung funktioniert, die Fehlermeldung liegt aber hinter dem Formular-Overlay.

**Erforderlich:** Validierungs- und Kollisionsmeldungen innerhalb bzw. oberhalb des aktiven Dialogs anzeigen.

---

## 3. B7 – Zeitgrenzen und Raster

**Problem:** Aktuelle harte Zeitgrenzen entsprechen nicht mehr dem fachlichen Soll.

**Erforderlich:**
- keine pauschale Grenze 06:00–21:00 Uhr,
- Zeitraster ggf. auf ca. 5 Minuten reduzieren,
- UI-Auswahl und Servervalidierung konsistent gestalten,
- Verhalten über Mitternacht fachlich festlegen.

---

## 4. E4 – Escape / Tastaturbedienung

**Problem:** Formular-Overlay lässt sich nicht per `Esc` schließen.

**Erforderlich:** Dialog muss per Escape geschlossen werden können; anschließend sollte der Fokus sinnvoll zum auslösenden Element zurückkehren.

---

## 5. E5 – Fehlermeldungen

**Problem:** Fehler werden teilweise technisch erzeugt, aber im aktiven Bedienkontext nicht sichtbar dargestellt.

**Erforderlich:** Fehlermeldungen müssen unmittelbar beim Formular sichtbar und verständlich sein.

---

# Nicht geprüfte Prüffälle

## A6 – Abweichende persönliche Zeitzone

Nicht geprüft, da kein entsprechendes Testkonto eingerichtet wurde und der Test im aktuellen Durchlauf geringe Priorität hatte.

## E3 – Kalenderkontext / LocalBase

Nicht geprüft, da die Zielarchitektur und die Rolle von LocalBase noch umgestellt werden. Der Test muss nach Abschluss dieser Umstellungen neu definiert werden.

---

# Weitere technische Beobachtung außerhalb der Abnahmewertung

Beim Wiederherstellen des Normalzustands wurden beim Ausführen von `occ` Meldungen ausgegeben, dass mehrere Demo-Seed-Commands einen nicht mehr vorhandenen LocalBase-Service referenzieren:

`OCA\LocalBase\Service\DemoAccountProvisioningService`

Betroffen waren die Seed-Demo-Commands von:
- AD Kalender,
- AdPlaner,
- AD Raumplaner,
- AD Urlaub.

Alle relevanten Apps ließen sich anschließend aktivieren und der Raumplaner funktionierte nach Reload wieder normal.

**Bewertung:** Separater technischer Befund, nicht einem der 31 Abnahmetests zugerechnet. Vermutlich Versions-/Umbauartefakt zwischen den Apps und LocalBase.

**Technische Nachprüfung für `0.11.0-rc.1`:** Mit LocalBase `0.10.0-rc.2`
wurde das Nextcloud-Upgrade einschließlich AD Raumplaner erfolgreich
ausgeführt; der frühere DI-Befund ist im aktuellen Stand nicht reproduzierbar.

---

# Schlussentscheidung

**Gesamtstatus: Noch nicht vollständig abnahmefähig.**

Die geprüften Kernfunktionen des Raumplaners sind überwiegend stabil. Besonders positiv sind die serverseitig bestätigten Berechtigungsprüfungen für fremde Buchungen, die Besitzerermittlung aus der Sitzung, die Adminberechtigungen sowie der CSRF-Schutz für Buchungen und Raumverwaltung.

Vor einer vollständigen Abnahme sollten mindestens die fünf als nicht erfolgreich bewerteten Prüffälle korrigiert bzw. fachlich abschließend entschieden und erneut getestet werden. E3 sollte nach Abschluss der vorgesehenen Architekturänderungen neu spezifiziert und nachgetestet werden.

---

# Technische Datenschutz-Pilotabnahme vom 23.08.2026

Diese ergänzende Abnahme bewertet ausschließlich die optionale Anbindung an
die Standalone-App `filzmann_data_protection`. Sie ändert nicht die oben
dokumentierte Gesamtentscheidung zur allgemeinen Produktabnahme.

| Prüfpunkt | Ergebnis |
|---|---|
| Ausgangszustand | `adroom` und `localbase` aktiv, `filzmann_data_protection` deaktiviert |
| Privacy-App deaktiviert | AD Raumplaner weiterhin über HTTPS mit HTTP 200 erreichbar |
| Privacy-App aktiviert | App aktivierbar, Nextcloud `34.0.2` bleibt gesund, kein offenes Datenbankupgrade |
| Self-Service | authentifizierter API-Aufruf mit nativer Nextcloud-CSRF-/OCS-Anforderung liefert HTTP 200 |
| Provider | `adroom` wird lazy registriert und meldet `complete` |
| Drittpersonenschutz | freier Buchungstitel wird als `[Freitext mit möglichen Drittpersonenangaben entfernt]` ausgegeben |
| Kontexterhalt | Raum, Zweck sowie Start- und Endzeit bleiben im Datensatz erhalten |
| Oberfläche/Assets | Berichtseite, CSS und beide JavaScript-Assets über HTTPS mit passendem Content-Type erreichbar |
| Rückbau | `filzmann_data_protection` anschließend wieder deaktiviert; AD Raumplaner weiterhin HTTP 200 |

Die Repository-Tests decken zusätzlich Fremdbuchungen, Seitenlimit und
Teilantwort sowie die Ablehnung einer inkompatiblen Vertragsversion ab. Nicht
als reale DDEV-Installation geprüft sind ein physisch fehlendes App-Verzeichnis
und eine tatsächlich installierte inkompatible Privacy-App; diese beiden
Fälle bleiben als automatisierbare Integrationsprüfungen offen.
