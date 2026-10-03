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

Der Fremdeingriff besitzt zusätzlich einen serverseitigen
`SecretariatForeignBookingInterventionGuard`. Er kombiniert die aktuelle
Session, eine tatsächlich fremde Zielbuchung, die app-lokale
Sekretariatsrolle und den öffentlichen V1-Risikoscope
`adroom.secretariat_foreign_booking_intervention` des optionalen
Datenschutz-Centers. Fehlender oder deaktivierter Provider, ausbleibende
Antwort, Deny, Vertragsinkompatibilität und Providerfehler bleiben
fail-closed. Das Datenschutz-Center liefert dabei weder Kunden-, Policy-,
Vereinbarungs- noch DPO-Details. Die Buchungsänderung beziehungsweise
-löschung, der append-only Nachweis und die persistente Outbox werden in einer
Datenbanktransaktion gespeichert. Audit- oder Outbox-Schreibfehler rollen die
Fachmutation zurück; ein Fehler der erst nach dem Commit versuchten nativen
Benachrichtigung lässt die Änderung bestehen und wird aus der Queue genau nach
5 Minuten, 1 Stunde und 24 Stunden wiederholt. Erfolgreiche Queuezeilen werden
sofort gelöscht, dauerhaft fehlgeschlagene samt minimalem Fehlercode nach 30
Tagen. Das Audit enthält weder Titel noch Zweck oder Eigentümer-UID und wird
zwölf Monate nach dem Eingriff vollständig gelöscht. Seine API ist nur für
`Datenschutzbeauftragte` lesbar; ein Betriebsratszugriff wird nicht als
Produktrolle hardcodiert. Normale Raumansicht, eigene Buchungen und der
getrennte temporäre Admin-Vollzugriff verwenden den Scopevertrag nicht.

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

Organisationszuordnung, Seiten-/API-Zugriff, Freititelprojektion,
Sekretariatsverwaltung, Begründungs- und Auditpflicht sowie persistente
Benachrichtigungswiederholung sind serverseitig umgesetzt. Die reale
Nextcloud-Runtimeabnahme von Migration, nativer Benachrichtigung und Jobs
bleibt ein Release-Gate. Der Datenschutzprovider liefert weiterhin nur
app-eigene, typisierte Buchungsbezüge und redigiert freie Titel.

Der kanonische Policykatalog liegt unter
`resources/privacy-processing.json`. Der optionale
`RoomProcessingMetadataProvider` liest ausschließlich diese feste app-eigene
Datei und registriert sie lazy über den öffentlichen V1-Vertrag von
`filzmann_data_protection`. Der Katalog umfasst die Verarbeitung von
Raumbuchungen, der temporären Adminfreigabehistorie und der persönlichen
Admin-Kartenanordnung. Er enthält keine Laufzeitdatensätze und führt keine
Retention-Maßnahme aus. Als auslieferbare Metadatenquelle enthält er keine
kunden- oder instanzspezifische Rechtsgrundlage, verantwortliche Organisation,
Vereinbarung oder Evidenz. Diese Zuordnung wird je Installation außerhalb des
Produktpakets dokumentiert; ihr Fehlen wird nicht durch technische Defaults
ersetzt.

Für beendete Raumbuchungen gilt ein Jahr ab Buchungsende als administrativ
konfigurierbarer Standardwert. Mitglieder der Nextcloud-Gruppe
`Datenschutzbeauftragte` dürfen die Buchungsfrist innerhalb von 30 Tagen bis
drei Jahren verkürzen oder verlängern; eine neue, nicht rückdatierte Revision
wird für Buchungen anhand der bei ihrem ursprünglichen Buchungsende wirksamen
Policy ausgewertet. Derselbe versionierte Konfigurationsvertrag führt für die
Adminfreigabehistorie unveränderlich sechs Monate ab ihrem tatsächlichen Ende
als empfohlenen Standardwert. Jede Änderung enthält Revision,
Wirksamkeitszeitpunkt und Akteur; abgewiesene, veraltete oder beschädigte
Änderungen verändern die Historie nicht. Die Policyänderung wird mindestens
24 Monate auditierbar gehalten und mindestens jährlich durch diese Gruppe
überprüft. Bis zur ersten dokumentierten Konfiguration oder Prüfung gilt der
Review als fällig.
Nach Fristablauf wird die Buchung vollständig gelöscht; es verbleibt weder
ein anonymisierter Rest noch eine Statistik. Eine aktive rechtliche oder
datenschutzrechtliche Sperre blockiert die Löschung, begrenzt die Nutzung auf
den dokumentierten Sperrzweck und darf nur durch `Datenschutzbeauftragte`
begründet und auditiert aufgehoben werden. Nach einem Restore wird die Frist
vom ursprünglichen Buchungsende neu bewertet und eine abgelaufene ungesperrte
Buchung erneut zur Löschung eingeplant.

Der öffentliche V1-Provider bleibt eine read-only Vorschau ohne
`execute()`-Methode. Der getrennte V2-Provider bietet die beiden ausdrücklich
versionierten DELETE-Policies `room_booking_delete` und
`temporary_admin_access_history_delete` an. Er ermittelt Kandidaten aus den
app-eigenen Repositories, prüft Policyversion, Fälligkeit, Ausführungstoken,
aktuellen Datensatz und Hold unmittelbar vor der Mutation erneut und löscht
nur innerhalb der app-eigenen Transaktionsgrenze. Veraltete, manipulierte,
zwischenzeitlich geänderte oder gesperrte Kandidaten werden ohne verbotene
Nebenwirkung abgewiesen.

Die Ausführung startet im Datenschutz-Center standardmäßig deaktiviert. Nur
native Nextcloud-Administration kann sie über die dortige technische
Aktivierungsrevision ein- oder ausschalten. Kundenlokale Rechtsgrundlagen,
Betriebs- oder Dienstvereinbarungen, DPO-/Betriebsratsbestätigungen und
Evidenzreferenzen sind weder Felder dieser Aktivierung noch technische
DELETE-Gates. Fail-closed bleiben dagegen fehlende, deaktivierte, zukünftige,
fällige oder beschädigte Aktivierung, nicht aktuelle Backup-/Restore-
Prüfzeitpunkte, unzulässige Backupgrenzen, Policy- oder
Providerinkompatibilität, Holds, Integritäts- und Nebenläufigkeitskonflikte
sowie nicht atomar ausführbare Löschungen. Die technische Backupgrenze liegt
bei 1 bis 365 Tagen plus 0 bis 5 Tagen Puffer; der nächste technische
Prüftermin darf höchstens ein Jahr entfernt liegen.

V1 und V2 registrieren sich lazy über die öffentlichen Verträge von
`filzmann_data_protection`. Die app-eigene Abfrage und Löschung bleiben
Eigentum des Raumplaners; UID, freier Titel und Zweck verlassen den
Retentionpfad nicht. Ein fehlendes, deaktiviertes oder inkompatibles
Datenschutz-Center ist ein expliziter Standalone-Zustand, in dem keine
automatische Löschung läuft, und kein Anlass für einen LocalBase-, SQL- oder
Reflection-Fallback. Die reale Datenbank-, Migrations-, Job-,
Nebenläufigkeits- und Restore-Abnahme des V2-Piloten steht noch aus; aus den
lokalen Unit- und Contract-Tests folgt kein Releaseurteil.

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
