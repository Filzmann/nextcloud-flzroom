# Architektur – AD Raumplaner

## Verantwortung

AD Raumplaner ist die kanonische Quelle für Räume und Raumbuchungen. Kalender,
Assistenzplanung und OrgSuite bleiben optionale Integrationen ohne direkten
Zugriff auf Raumtabellen oder private Assets.

## Fach- und Datenmodell

- Buchungen besitzen Raum, Beginn, Ende, Zweck, Titel und die Nextcloud-UID
  der buchenden Person.
- Buchungen desselben Raums dürfen sich nicht überschneiden; angrenzende
  Buchungen bleiben erlaubt.
- Validierung und Konfliktprüfung liegen im Booking-Service, Rechte im
  Access-Service und Persistenz in Repositorys.
- Feiertage und fachliche Zeitzone werden ausschließlich aus dem gemeinsamen
  LocalBase-Kalenderkontext projiziert.

## Rechte und Datenschutz

Eigene Buchungen werden aus der Session-UID serverseitig gebunden. Fremde
Buchungen und Raumverwaltung benötigen eine app-lokale, zeitlich begrenzte
Adminfreigabe zusätzlich zum nativen Adminstatus. Der Datenschutzprovider
liefert nur app-eigene, typisierte Buchungsbezüge und redigiert freie Titel.
