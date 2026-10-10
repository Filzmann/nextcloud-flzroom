# Changelog

## Unreleased

- Nextcloud 35.0.1 durch Fresh Install, Upgrade 34→35 sowie Provider-,
  Berechtigungs-, Runtime-, UI-/API- und Asset-Smokes nachgewiesen und den
  unterstützten Bereich auf die lückenlosen Hauptversionen 33 bis 35
  erweitert. Der Permission-Provider-Listener erfüllt nun Nextclouds
  öffentlichen `IEventListener`-Vertrag. Die Vorschau dauerhaft
  fehlgeschlagener Zustellungen verwendet den gültigen Retention-Trigger
  `COMPLETED_AT`; Berechnung ab `failedAt`, 30-Tage-Frist und reine
  `REVIEW`-Semantik bleiben unverändert. Nextcloud 36 bleibt ungeprüft.
- Fail-closed Consumer-Guard für den neutralen öffentlichen V1-Risikoscope
  des Datenschutz-Centers ergänzt. Er verlangt für den künftigen
  Sekretariatseingriff zugleich Fachrolle, fremde Zielbuchung und eine
  kompatible aktive Scope-Freigabe, erhält keine kundenlokalen Policydetails
  und hält kundenlokale Policydetails aus der Fachapp heraus.
- Begründete Fremdeingriffe durch `flz-Sekretariat` vollständig angebunden:
  10–500 Zeichen Pflichtbegründung, atomare Buchungsänderung mit append-only
  Audit und persistenter Nextcloud-Benachrichtigungsqueue, Wiederholungen nach
  5 Minuten, 1 Stunde und 24 Stunden sowie feste Löschung des Audits nach 12
  Monaten und dauerhaft fehlgeschlagener Queueeinträge nach 30 Tagen.
- Eingriffsaudit auf `Datenschutzbeauftragte` begrenzt und Processing-Katalog,
  PersonalDataProvider, PermissionProvider sowie Retention-Vorschau um die
  neuen Datenklassen ergänzt; kundenlokale Vereinbarungen oder BR-Gruppen
  werden nicht hardcodiert.
- Raumplan und eigene Buchungen serverseitig auf von `Datenschutzbeauftragte`
  konfigurierte Nextcloud-Gruppen begrenzt; eine leere Konfiguration sperrt
  reguläre Zugriffe. Freie Buchungstitel werden nur Besitzer*in und
  `flz-Sekretariat` ausgegeben.
- Retention-Dry-Run vom LocalBase-Pilot auf den öffentlichen
  V1-Providervertrag des Datenschutz-Centers migriert; globale Vorschauen sind
  paginiert, datenminimiert und weiterhin strikt `REVIEW`-only.
- Fristen für Raumbuchungen und Adminfreigabehistorien ausschließlich für
  `Datenschutzbeauftragte` gemeinsam versioniert, mit Wirksamkeitszeitpunkt,
  optimistischer Revision und jährlichem Review geführt; die Vorschau wertet
  vorhandene Datensätze vom ursprünglichen Ende mit der aktuellen Version neu
  aus und bietet weiterhin keinen Ausführungspfad.
- Temporären fachlichen Admin-Vollzugriff auf höchstens 24 Stunden begrenzt:
  Nur `Datenschutzbeauftragte` dürfen aktuelle Nextcloud-Administrationskonten
  in der Hauptoberfläche freigeben oder widerrufen; native Administration
  allein bleibt ohne Freigabe- und Historienzugriff.
- App-eigenen Processing-Metadatenkatalog für Raumbuchungen,
  Adminfreigabehistorie und persönliche Adminanordnung sowie dessen optionalen
  öffentlichen V1-Provider ergänzt; offene Datenschutzentscheidungen bleiben
  sichtbar und lösen keine automatische Maßnahme aus.
- Tatsächlich gespeicherte persönliche Adminanordnungen subjectgebunden in die
  Art.-15-Auskunft aufgenommen; fremde und bloße Standardwerte bleiben außen vor.
- Nextcloud 33.0.7 bis 34.0.2 durch Fresh Install und Upgrade 33→34 mit
  App-Suiten, DI-/Registrierungs-, API-, Rechte-, HTTPS-, Asset- und UI-Smokes unterstützt.
- Dokumentations- und Steuerungsstruktur vereinheitlicht.

## 0.12.0-rc.1

- Subjectgebundene persönliche Datenauskunft für eigene Raumbuchungen mit Zweck, Zeitraum und Aufbewahrungshinweis ergänzt.
- Aufbewahrungsregel und ausschließlich lesende `REVIEW`-Vorschau im zugänglich klapp- und verschiebbaren Adminbereich ergänzt.
- Buchungszeiten im Frontend an der fachlichen Organisationszeitzone ausgerichtet.

## 0.11.0-rc.1

- Raumzeiten auf ein durchgängiges 5-Minuten-Raster ohne feste 06–21-Uhr-Grenze erweitert; Buchungen bleiben bis zur Produktentscheidung auf einen Kalendertag begrenzt.
- Buchungsfehler im aktiven Dialog sichtbar gemacht sowie Escape-Schließen und Fokusrückgabe abgesichert.
- Monatsnavigation und zweidimensional scrollbare Raummatrix für kleine Viewports stabilisiert.

## 0.9.0-rc.1

- Eigenständige Navigation ohne OrgSuite ergänzt.
- Raumverfügbarkeits- und Buchungsfähigkeiten über optionale LocalBase-Verträge veröffentlicht.
- Ungültige harte App-Abhängigkeiten aus den Nextcloud-Metadaten entfernt.

## 0.8.8-rc.1

- Eigener Nextcloud-Adminabschnitt für die ausschließlich appbezogene Raumverwaltung.
- Öffentliche Projekt-, Quellcode- und Fehlerkanäle ergänzt.

## 0.8.7-rc.1

- Erster reproduzierbarer Staging-Releasekandidat für Nextcloud 34 und PHP ab 8.3.
- Zeitachsenansicht für mehrere Räume und kollisionsfreie Buchungen.
- Titel und frei erweiterbare standardisierte Buchungszwecke.
- Authentifizierter DOM-, CSRF-, Anlege-, Kollisions- und Lösch-Smoke.
