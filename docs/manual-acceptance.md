# Manuelles Abnahmeformular – AD Raumplaner

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
| D2 | Optionale Provider | Betrieb ohne AD Kalender, AdPlaner und LocalBase im freigegebenen Testkontext prüfen. | Fehlende optionale Provider werden kontrolliert behandelt und blockieren die Kernfunktion nicht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## E. Bedienbarkeit, Fehler und temporärer Admin-Vollzugriff

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| E1 | Tastatur und Fehler | Dialoge per Tastatur bedienen, Escape, Fokuswechsel und Validierungsfehler prüfen. | Fokus ist sichtbar, Escape schließt Dialoge ohne Falle und Fehler sind verständlich im Bedienkontext sichtbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E2 | Datenminimierung | Oberfläche, Antwortdaten, Logs und Belege kontrollieren. | Keine unnötigen personenbezogenen Daten, Geheimnisse oder internen Pfade werden offengelegt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E3 | Adminfreigabe | DPO, nativen Admin, kombiniertes und gewöhnliches Konto sowie ungültige Ziele, CSRF, Ablauf und Widerruf prüfen. | Nur die DPO-Rolle steuert die zeitlich begrenzte Freigabe; unzulässige Fälle bleiben ohne Historien- oder Rechteänderung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

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
