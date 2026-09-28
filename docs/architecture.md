# Architektur – AD Raumplaner

## Verantwortung

AD Raumplaner ist die kanonische Quelle für Räume und Raumbuchungen. Kalender,
Assistenzplanung und OrgSuite bleiben optionale Integrationen ohne direkten
Zugriff auf Raumtabellen oder private Assets.

## Fach- und Datenmodell

- Buchungen besitzen Raum, Beginn, Ende, Zweck, Titel und die Nextcloud-UID
  der buchenden Person.
- Beginn und Ende jeder Buchung liegen am selben lokalen Kalendertag der
  fachlichen Organisationszeitzone; Buchungen über eine lokale
  Kalendertagsgrenze hinweg sind nicht vorgesehen.
- Buchungen desselben Raums dürfen sich nicht überschneiden; angrenzende
  Buchungen bleiben erlaubt.
- Validierung und Konfliktprüfung liegen im Booking-Service, Rechte im
  Access-Service und Persistenz in Repositorys.
- Feiertage und fachliche Zeitzone werden ausschließlich aus dem gemeinsamen
  LocalBase-Kalenderkontext projiziert.

## Rechte und Datenschutz

Eigene Buchungen werden aus der Session-UID serverseitig gebunden. Der
Raumplaner gewährt nur Mitgliedern der von `Datenschutzbeauftragte`
konfigurierten, bestehenden Nextcloud-Gruppen Zugriff; eine leere oder
fehlerhafte Liste sperrt reguläre Zugriffe. Nextcloud bleibt Quelle der
Gruppen und Mitgliedschaften. Organisationskräfte verwalten ausschließlich
eigene Buchungen; der höchstens 24 Stunden freigegebene Adminpfad bleibt die
ausdrücklich dokumentierte Ausnahme.

Die kanonische LocalBase-Gruppe `ad-Sekretariat` bildet eine dauerhafte,
app-lokale Fachrolle. Sie ist ausdrücklich kein Ersatz für den getrennten,
höchstens 24 Stunden gültigen technischen Admin-Vollzugriff. Das Sekretariat
darf Räume verwalten und zur Lösung organisatorischer Konflikte fremde
Buchungen ändern oder löschen. Jeder solche Eingriff setzt eine vorab
angegebene Begründung voraus und muss app-lokal auditierbar sein. Die
betroffene buchende Person erhält über den nativen Nextcloud-Mechanismus eine
datensparsame Benachrichtigung: alter und, soweit anwendbar, neuer Raum und
Zeitraum sowie die Begründung. Namen oder Inhalte anderer Personen und
Buchungen und weitere Buchungsdaten werden nicht mitgeteilt.

Der temporäre fachliche Admin-Vollzugriff wird ausschließlich von Mitgliedern
der kanonischen Nextcloud-Gruppe `Datenschutzbeauftragte` erteilt und
widerrufen. Nativer Adminstatus allein erteilt weder Vollzugriff noch Zugriff
auf Freigabehistorie oder -steuerung. Ziel ist immer ein aktuell von
Nextcloud bestätigtes Administrationskonto; die app-lokale Freigabe gilt
höchstens 24 Stunden und wird bei Ablauf, Widerruf, Verlust des nativen
Adminstatus oder Prüffehlern deny by default unwirksam. Die Steuerung liegt
rollenabhängig in der Hauptoberfläche. Der Eintrittshinweis erscheint nur für
native Administrationskonten ohne aktive Freigabe; ein Direktlink wird nur
bei zusätzlicher Datenschutzrolle gerendert.

Für die direkte Abstimmung sind buchende Person und frei eingegebener Zweck
erforderlich und für berechtigte Organisationskräfte sichtbar. Der Freititel
wird serverseitig nur der buchenden Person und `ad-Sekretariat` ausgegeben.
Zweck und Titel bleiben freie Eingaben, weil ein abschließender Zweckkatalog
den Planungsbedarf nicht abbildet. Die Oberfläche muss deshalb sichtbar zur
Datenminimierung auffordern und Namen, Gesundheits-, Fall- sowie andere
unnötige Drittpersonenangaben ausdrücklich ausschließen.

Organisationszuordnung, Seiten-/API-Zugriff und Freititelprojektion sind in
der Laufzeit serverseitig umgesetzt. Bis Sekretariatsverwaltung,
Begründungs- und Auditpflicht sowie Benachrichtigung mit positiven und
negativen Servertests umgesetzt sind, ist dieser verbleibende Teil des
Zielvertrags nicht als produktionsreif zu behandeln. Der
Datenschutzprovider liefert weiterhin nur app-eigene, typisierte
Buchungsbezüge und redigiert freie Titel.

Der kanonische Policykatalog liegt unter
`resources/privacy-processing.json`. Der optionale
`RoomProcessingMetadataProvider` liest ausschließlich diese feste app-eigene
Datei und registriert sie lazy über den öffentlichen V1-Vertrag von
`filzmann_data_protection`. Der Katalog umfasst die Verarbeitung von
Raumbuchungen, der temporären Adminfreigabehistorie und der persönlichen
Admin-Kartenanordnung. Er enthält keine Laufzeitdatensätze und führt keine
Retention-Maßnahme aus.

Für beendete Raumbuchungen gilt ein Jahr ab Buchungsende als administrativ
konfigurierbarer Standardwert. Mitglieder der Nextcloud-Gruppe
`Datenschutzbeauftragte` dürfen die Frist verkürzen oder verlängern; eine
Änderung wird anhand des ursprünglichen Buchungsendes auch auf bereits
vorhandene Buchungen angewendet. Derselbe versionierte Konfigurationsvertrag
führt für die Adminfreigabehistorie sechs Monate ab ihrem tatsächlichen Ende
als Standardwert. Jede Änderung enthält Revision, Wirksamkeitszeitpunkt und
Akteur; abgewiesene, veraltete oder beschädigte Änderungen verändern die
Historie nicht. Die Policyänderung wird mindestens 24 Monate auditierbar
gehalten und mindestens jährlich durch diese Gruppe überprüft. Bis zur ersten
dokumentierten Konfiguration oder Prüfung gilt der Review als fällig.
Nach Fristablauf wird die Buchung vollständig gelöscht; es verbleibt weder
ein anonymisierter Rest noch eine Statistik. Eine aktive rechtliche oder
datenschutzrechtliche Sperre blockiert die Löschung, begrenzt die Nutzung auf
den dokumentierten Sperrzweck und darf nur durch `Datenschutzbeauftragte`
begründet und auditiert aufgehoben werden. Nach einem Restore wird die Frist
vom ursprünglichen Buchungsende neu bewertet und eine abgelaufene ungesperrte
Buchung erneut zur Löschung eingeplant.

Der öffentliche V1-Provider projiziert beide Datenklassen mit der aktuellen
Policyrevision, berechnet den Stichtag bei jeder Vorschau aus dem
ursprünglichen Ende und liefert ausschließlich `REVIEW`; er besitzt keine
`execute()`-Methode. Dies ist noch kein ausführender Runtimevertrag. Die spätere Löschung läuft
automatisch ohne manuelle Einzelfreigabe. Nach automatischen
Wiederholungsversuchen erhält `Datenschutzbeauftragte` nur App, Datenklasse,
Zeitpunkt und technische Referenz; der inhaltsarme Fehlernachweis wird nach
30 Tagen gelöscht. Bis Policyversion und Wirksamkeitszeitpunkt, Reihenfolge,
Atomarität, Nebenläufigkeit, Idempotenz, betriebliche Backupgrenze,
Sperrdurchsetzung, Auditvollständigkeit, Fehlerrückbau und
Provider-/Consumer-Verhalten freigegeben und getestet sind, bleibt Retention
ausschließlich eine lesende `REVIEW`-Vorschau. Insbesondere fehlen derzeit
ein technisch durchgesetzter Hold-Datensatz samt Setzen/Aufheben/Audit und
Prüftermin, die betriebliche Backupentscheidung sowie der nebenläufigkeits-
und fehlerrückbaufeste Ausführungs- und Wiederholungsnachweis.

Diese Vorschau registriert sich lazy über den öffentlichen
V1-`RegisterRetentionProvidersEvent` von `filzmann_data_protection`.
Die app-eigene Abfrage bleibt Eigentum des Raumplaners und liefert globale
Treffer seitenweise mit opaker Fortsetzung; UID, freier Titel und Zweck
verlassen diesen Retentionpfad nicht. Ein fehlendes, deaktiviertes oder
inkompatibles Datenschutz-Center ist ein expliziter Standalone-Zustand und
kein Anlass für einen LocalBase-, SQL- oder Reflection-Fallback.

Die Katalogwerte sind die künftige kanonische Policyquelle. Die bestehenden
Projektionen in `RoomPersonalDataProvider` und `RoomRetentionProvider` bleiben
im ersten Consumer-Schritt unverändert; ihre Ableitung aus dem Katalog ist ein
gesonderter Rolloutschritt. Ein tatsächlich gespeicherter `IUserConfig`-Wert
`admin_dashboard_layout` wird inzwischen subjectgebunden und mit
verständlichen Kartenbezeichnungen ausgegeben. Fremde Layoutwerte und das nur
berechnete Standardlayout bleiben ausgeschlossen. Die Layoutpräferenz hat
keine eigene zeitliche Aufbewahrungsfrist: Sie wird beim ausdrücklichen Reset
durch die jeweilige Kontoinhaberin oder den jeweiligen Kontoinhaber, bei
Löschung dieses Nextcloud-Kontos oder bei Deinstallation der App gelöscht.
Nur das betroffene Konto darf seinen Wert zurücksetzen; weder native
Administration noch Mitglieder von `Datenschutzbeauftragte` dürfen fremde
Layouts zurücksetzen. Der bestehende Service kann den Wert derzeit nur lesen
und speichern. Ein selbstautorisierter Reset-Endpunkt sowie die verifizierte
Anbindung an Konto- und App-Lifecycle-Auslöser sind daher noch umzusetzen;
Backup- und Restore-Verhalten bleiben bis zu einer eigenen Entscheidung
offen.
