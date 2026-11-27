# Store-Readiness und Umbenennungsmatrix (ROOM-STORE-01/02)

Stand: 27. November 2026. Erhoben als Grundlage für das Rebranding zu
**Filzmann Rooms**; die zeitabhängigen Store-Vorgaben werden vor jedem Release
Candidate erneut gegen die offiziellen Quellen verifiziert.

## Offizielle Store-Anforderungen (Quellen, abgerufen 27.11.2026)

- Nextcloud App Store Rules / Publishing: <https://docs.nextcloud.com/server/stable/developer_manual/app/publishing.html>
- App Developer Guide (appstore): <https://github.com/nextcloud/appstore/blob/master/docs/developer.rst>

Kernvorgaben daraus:

- App-ID: eindeutig, lowercase, alphanumerisch (Start mit Buchstabe); sichtbarer
  Name und Beschreibung in `info.xml` vollständig und korrekt.
- Lizenz muss App-Store-kompatibel sein (AGPL wie bisher).
- Paket als Archiv (tar.gz) aus genau einer App-Wurzel; keine Entwicklungsdateien,
  Secrets oder privaten Schlüssel; `info.xml` valide und vollständig.
- Code-Signing über Nextcloud-Zertifikat (`occ appstore:register`/`sign`) —
  erst als eigener späterer Freigabeschritt (ROOM-STORE-08), hier nur vorbereitet.
- Review-Prozess des App Store; Alpha/Beta-Kanäle möglich.
- Unterstützte Nextcloud-Versionen in `info.xml` (`min-version`/`max-version`)
  gemäß belegter Kompatibilitätsmatrix.

## Umbenennungsmatrix (festgelegt 27.11.2026)

| Objekt | heute | Ziel |
| --- | --- | --- |
| App-ID | `flzroom` | `flzrooms` |
| Sichtbarer Name | Filzmann Raumplaner | **Filzmann Rooms** |
| PHP-Namespace | `OCA\FlzRoom` | `OCA\FlzRooms` |
| JS-Global | `OCA.FlzRoom` / `window.FlzRoom` | `OCA.FlzRooms` |
| CSS/DOM-Präfix | `flz-room-*` | `flz-rooms-*` |
| Routen | `/apps/flzroom/...` | `/apps/flzrooms/...` |
| l10n-Domain | `flzroom` | `flzrooms` |
| AppConfig-/Config-Keys | `flzroom_*` | `flzrooms_*` |
| occ-Kommandos | `flzroom:*` | `flzrooms:*` |
| Repositorium/Archiv | `nextcloud-flzroom` | `nextcloud-flzrooms` (Umbenennung als eigener Schritt) |

Inventar: 87 produktive Dateien (ohne tests) tragen heute `flzroom`/`FlzRoom`/
`Flzroom`-Bezüge; CSS/JS zusätzlich `flz-room`-Präfixe. Erhaltungsbedarf:
Entwicklungsphase ohne produktiven Bestand — Fresh Install/Reinstall ist der
kanonische Weg; Rückbau = Reinstall auf Ziel-ID. Consumer anderer Repositories
werden nur in jeweils separat freigegebenen Aufträgen angepasst (ROOM-STORE-04).
