# Manuelles Abnahmeformular – Filzmann Raumplaner

Dieses Formular enthält ausschließlich wiederholbare manuelle Prüfungen. Es ist
keine Produktivfreigabe. Ausschließlich synthetische Räume, Buchungen und
Testkonten verwenden; keine Zugangsdaten, URLs oder personenbezogenen Inhalte
in Ergebnisfeldern dokumentieren.

## Kopfdaten

| Feld | Eintrag |
|---|---|
| Datum und Uhrzeit | |
| Prüfer*in | |
| Umgebung und App-Version | |
| Nextcloud-Version, Browser und Viewport | |
| Neutrale Testkonten und Testdaten | |

Ergebniskennzeichnung: `[ ] erfolgreich` / `[ ] nicht erfolgreich` /
`[ ] nicht geprüft`. Abweichungen müssen reproduzierbar beschrieben werden.

## A. Einstieg, Darstellung und Navigation

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| A1 | Standalone und Suite-Einstieg | Mit deaktivierter und aktivierter OrgSuite öffnen. | Der vorgesehene Einstieg ist ohne Doppelnavigation nutzbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A2 | Monatsansicht und Zeitachse | Monate wechseln, Räume und parallele Buchungen ansehen. | Daten bleiben eindeutig, vollständig und in der fachlichen Zeitzone dargestellt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A3 | Wochenenden, Feiertage und Viewport | Bei kleinem Viewport scrollen und Feiertage prüfen. | App-Root scrollt vertikal; nur die Matrix scrollt horizontal, Fokus und Inhalte bleiben erreichbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A4 | Persönliche Zeitzone | Dasselbe synthetische Objekt mit abweichender persönlicher Zeitzone öffnen. | Fachliche Wandzeit, Tageszuordnung und Bearbeitungsdialog bleiben konsistent. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## B. Eigene Buchungen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| B1 | Anlegen, ändern und löschen | Synthetische Buchung erstellen, ändern, Raum wechseln und löschen. | Nur die eigene Buchung ändert sich; Bestätigung und Neuladen sind konsistent. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B2 | Zweck, Zeiten und Raster | Freitext, direkt angrenzende Zeiten, Überschneidung sowie Randzeiten prüfen. | Zulässige Werte bleiben erhalten; Überschneidungen und ungültige Werte werden ohne Mutation verständlich abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B3 | Parallele Räume und Konflikte | Gleichzeitige Buchung in anderem Raum und konkurrierende Änderung versuchen. | Andere Räume bleiben möglich; Konflikte erzeugen keinen widersprüchlichen Stand. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## C. Fremdbuchungen und Schutzgrenzen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| C1 | Lesen und Ändern | Fremde Buchung mit berechtigten und unberechtigten Konten über UI und direkten Request prüfen. | Sichtbarkeit und Mutation folgen serverseitig der Berechtigung; abgewiesene Requests ändern nichts. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C2 | Besitzer und CSRF | Manipulierte Besitzerkennung sowie Schreibrequest ohne CSRF senden. | Besitzer stammt aus der Sitzung; Manipulation und tokenlose Requests bleiben mutationsfrei. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## D. Raumverwaltung und Integrationen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| D1 | Raumverwaltung | Räume anlegen, ordnen, ändern und mit Bestätigung löschen; als Nichtadmin wiederholen. | Nur Berechtigte verwalten Räume; Lösch- und CSRF-Schutz verhindern Nebenwirkungen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D2 | Optionale Provider | Betrieb ohne Filzmann Kalender und FlzPlaner im freigegebenen Testkontext prüfen; `flz_organization` getrennt deaktivieren. | Fehlende optionale Kalender-/Planer-Provider blockieren die manuelle Buchung nicht. Ohne `flz_organization` werden keine Feiertage behauptet und reguläre Rollenrechte bleiben fail-closed. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## E. Bedienbarkeit, Fehler und temporärer Admin-Vollzugriff

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| E1 | Tastatur und Fehler | Dialoge per Tastatur bedienen, Escape, Fokuswechsel und Validierungsfehler prüfen. | Fokus ist sichtbar, Escape schließt Dialoge ohne Falle und Fehler sind verständlich im Bedienkontext sichtbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E2 | Datenminimierung | Oberfläche, Antwortdaten, Logs und Belege kontrollieren. | Keine unnötigen personenbezogenen Daten, Geheimnisse oder internen Pfade werden offengelegt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E3 | Adminfreigabe | DPO, nativen Admin, kombiniertes und gewöhnliches Konto sowie ungültige Ziele, CSRF, Ablauf und Widerruf prüfen. | Nur die DPO-Rolle steuert die zeitlich begrenzte Freigabe; unzulässige Fälle bleiben ohne Historien- oder Rechteänderung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E4 | Einstellungen und Rechtematrix | Tabs vollständig per Tastatur bedienen; als DPO einzelne Rechte Organigrammrollen zuordnen; einen unbekannten Rollenschlüssel, eine unvollständige Matrix sowie einen fehlenden oder deaktivierten `flz_organization`-V1-Snapshot per direktem Request prüfen. | Der Raumkalender bleibt frei von administrativen Formularen. Nur vorhandene semantische Organigrammrollen sind je Einzelrecht auswähl- und speicherbar; AS/ASN bleiben ausgeschlossen, Manipulation und Providerfehler ohne Rechte- oder Konfigurationsänderung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E5 | Raumverwaltung ohne Buchungszugriff | Mit Sekretariatsrolle und mit nativem Admin ohne temporäre Vollzugriffsfreigabe den Einstellungstab beziehungsweise Adminabschnitt öffnen, Räume lesen und ändern sowie Monats- und Fremdbuchungszugriff prüfen. | Raumstammdaten sind verwaltbar; die Raumlistenantwort enthält keine Buchungen und weder Sekretariat noch nativer Admin erhalten allein dadurch Fremdbuchungs- oder Admin-Vollzugriff. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## F. Früherer lokaler DDEV-Teilstand

Stand der dokumentierten Prüfung: 17. November 2026. Geprüfte Runtime:
Nextcloud 34.0.2 mit `flzroom` 0.13.0-rc.2. Die Migrationen 000001 bis
000004 waren in dieser lokalen Instanz eingetragen. Dies ist ein Teilnachweis
und keine vollständige Runtime- oder Releasefreigabe.

| ID | Was wurde geprüft? | Belegtes Ergebnis | Status und Grenze |
|---|---|---|---|
| F1 | Fremdeingriff und Schutzgrenzen mit synthetischen Fällen | Fremdes Update und Delete wurden mit 403 verweigert; eine zu kurze Begründung mit 400. Eigene Buchungsfälle und anonyme Aufrufe wurden ebenfalls verweigert. In den geprüften Negativfällen traten keine fachlichen Nebenwirkungen auf. | Lokal nachgewiesener Teilstand; keine vollständige Abnahme mit realen Organisationsrollen. |
| F2 | Native Benachrichtigung über eine separate synthetische Queue-Fixture | Eine echte native Nextcloud-Benachrichtigung wurde zugestellt; wiederholte Verarbeitung blieb idempotent. Der Inhalt war minimiert; E-Mail und Push wurden nicht versandt. Probeobjekte wurden über die vorgesehenen APIs entfernt, soweit dies für die Prüfung belegt ist. | Isolierter Zustellungsnachweis; kein vollständiger Ende-zu-Ende-Nachweis des Fremdeingriffs. |
| F3 | Organisations- und Risikokonfiguration sowie Jobregistrierung | Eine gültige Organisationsdefinition aus `flz_organization` sowie gültige Raumrollen- und Berechtigungskataloge wurden bestätigt. Der deklarierte `InterventionNotificationJob` war nach zwei gezielten nativen App-Upgrades jeweils genau einmal registriert. Der RiskScope wies `configuration_missing` aus. | Historischer Teilstand vom 17. November vor der isolierten Laufzeitabnahme. Der Job wurde in dieser Instanz nicht ausgeführt; der folgende Abschnitt dokumentiert den separaten synthetischen Laufzeittest. |

Die bestehende DDEV-Instanz und Staging enthalten ausschließlich Testdaten.
Eine dort ausdrücklich synthetische Scope-Fixture belegt keine Freigabe für
einen realen Betrieb; eine tatsächliche DPO-Entscheidung dafür liegt nicht vor.

## G. Isolierte synthetische Laufzeitabnahme

Am 17. November 2026 wurde eine separate, wegwerfbare Nextcloud-34-
SQLite-Instanz mit ausschließlich synthetischen Konten und Testdaten geprüft.
Klon und Testdaten wurden nach dem Lauf entfernt. Die Testfixture zur
Scope-Freigabe war ausdrücklich synthetisch und ist keine echte Bestätigung
einer Datenschutzbeauftragten. Der reproduzierbare Einstieg aus dem
`nextcloud-dev`-Root lautet:

```sh
ddev exec -d /var/www/html/html bash custom_apps/flzroom/tests/integration-background-job-lifecycle.sh /var/www/html/html --with-foreign-intervention
```

| ID | Was wurde geprüft? | Belegtes Ergebnis | Status und Grenze |
|---|---|---|---|
| G1 | Fresh Install und Migrationen | Frische Nextcloud-Installation, alle vier flzroom-Migrationen, nativer Job, Dependency Injection und 60-Sekunden-Intervall. | Grün im isolierten SQLite-Klon; kein Job außerhalb der Testinstanz ausgeführt. |
| G2 | Upgrade-Regression | Vorheriger Stand ohne Jobdeklaration reproduzierte den fehlenden Job (Red); die aktuelle `info.xml`-Deklaration registrierte ihn (Green). Wiederholtes Upgrade ließ genau eine Registrierung bestehen. | Reproduzierbarer nachträglicher Regressionstest; kein zeitlicher TDD-Nachweis für die ursprüngliche Änderung. |
| G3 | Fremdeingriff und Negativfälle | Fehlender Scope und unberechtigte Rolle wurden verweigert; kurze Begründung ergab 400 ohne Nebenwirkungen. Berechtigtes Update und Delete erzeugten Audit. | Grün mit synthetischen Rollen, Testkonten und Buchungen. |
| G4 | Datenschutzansicht und Benachrichtigung | Audit war nur für die synthetische Datenschutzrolle sichtbar. Die native Nextcloud-Benachrichtigung wurde einschließlich Rendering geprüft; ihr Link wird über `IURLGenerator` absolut erzeugt. | Grün; weder E-Mail noch Push an reale Konten. |
| G5 | Job, Wiederholung und Retention | Der native Job verarbeitete fällige synthetische Queueeinträge; Wiederholung erzeugte keine Duplikate. Die Datenschutzrolle änderte die Auditfrist (30 bis 1095 Tage oder ein bis drei Kalenderjahre) und die Frist für dauerhaft fehlgeschlagene Zustellungen (ein bis 365 Tage). Eine neue Revision galt auch für bestehende Datensätze: Verlängerung erhielt zuvor fällige Einträge, Verkürzung ließ der nächste Joblauf alte Einträge löschen und junge bestehen. Erfolgreiche Queueeinträge wurden sofort entfernt. | Grün in der isolierten Testinstanz; keine Wirkung auf andere Installationen oder reale Daten. |

Die Testabnahme erteilt keine echte Datenschutzfreigabe. Der RiskScope in der
bestehenden DDEV-Instanz meldete beim dokumentierten Teilstand weiterhin
`configuration_missing`. Die synthetische Freigabe belegt nur die technische
Schutz- und Laufzeitkette. Eine reale fachliche, rechtliche oder
Datenschutzentscheidung wird durch diesen Test nicht getroffen.

### Nachträgliche Regressionen und TDD-Abweichung

Für die ursprünglichen Änderungen der Organisations-/Feiertagsadapter und
der Jobmetadaten ist die zeitliche Test-vor-Code-Reihenfolge nicht mehr
belegbar. Sie werden deshalb als begründete Übernahme eines bereits
vorhandenen Integrationsstands mit nachträglichen Regressionstests bewertet,
nicht als nachträglich behauptetes TDD. Die öffentlichen Provider- und
Nextcloud-Laufzeitgrenzen werden dabei mit tatsächlichem Verhalten geprüft.

Der frühere Rollenadapter scheiterte bei unverfälschter nativer Auflösung
eines gültigen `flz_organization`-V1-Snapshots; der frühere Feiertagsadapter
scheiterte am tatsächlichen Providervertrag. Die bestehenden Tests
`OrganizationGroupCatalogServiceTest.php` und
`OrganizationHolidayCalendarServiceTest.php` wurden um diese Pfade erweitert:
die isoliert eingesetzten früheren Quelldateien ergaben Exit 255, der aktuelle
Stand Exit 0. Fehlende Provider bleiben kontrolliert nicht verfügbar. Der
Jobmetadatenfehler ist separat durch den oben beschriebenen Einweg-Lifecycle-
Test reproduziert. Diese Nachweise belegen die behobenen Regressionen und
begründen die Übernahme; sie rekonstruieren keine historische TDD-Reihenfolge.

## Abschlussentscheidung

| Feld | Eintrag |
|---|---|
| Anzahl erfolgreich | |
| Anzahl nicht erfolgreich | |
| Anzahl nicht geprüft | |
| Kritische Abweichungen / Ticketreferenzen | |
| Erneute Prüfung erforderlich bis | |
| Entscheidung zum aktuellen Entwicklungsstand | [ ] abgenommen [ ] mit Auflagen abgenommen [ ] nicht abgenommen |
| Begründung der Gesamtentscheidung | |
| Name / Datum | |
