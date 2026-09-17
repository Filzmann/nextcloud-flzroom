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
beschlossene Zielvertrag gewährt nur Organisationskräften Zugriff auf den
Raumplaner; reine Assistenzkräfte erhalten keinen Zugriff. Organisationskräfte
verwalten ausschließlich eigene Buchungen. Die Zuordnung muss aus der
kanonischen Organisationsstruktur abgeleitet und auf jedem Lese- und
Schreibpfad serverseitig durchgesetzt werden.

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

Für die direkte Abstimmung sind buchende Person und frei eingegebener Zweck
erforderlich und für berechtigte Organisationskräfte sichtbar. Zweck und
Titel bleiben freie Eingaben, weil ein abschließender Zweckkatalog den
Planungsbedarf nicht abbildet. Die Oberfläche muss deshalb sichtbar zur
Datenminimierung auffordern und Namen, Gesundheits-, Fall- sowie andere
unnötige Drittpersonenangaben ausdrücklich ausschließen.

Dieser Zielvertrag ist noch nicht in der Laufzeit umgesetzt. Die bestehende
Laufzeit erlaubt weiterhin allen angemeldeten Konten das Lesen und Verwalten
eigener Buchungen und verwendet für fremde Buchungen sowie Raumverwaltung den
temporären Adminpfad. Bis Organisationszuordnung, Sekretariatsrolle,
Begründungs- und Auditpflicht sowie Benachrichtigung mit positiven und
negativen Servertests umgesetzt sind, ist der Zielvertrag nicht als wirksame
Berechtigungsgrenze oder produktionsreif zu behandeln. Der
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
vorhandene Buchungen angewendet. Die Maßnahme nach Fristablauf, fachliche
Sperren und der sichere Ausführungsvertrag bleiben offen. Bis Policyversion
und Wirksamkeitszeitpunkt, rückwirkende Neuberechnung, Nebenläufigkeit,
Backup/Restore, Fehlerdiagnostik und Roll-forward freigegeben und getestet
sind, bleibt Retention ausschließlich eine lesende `REVIEW`-Vorschau.

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
