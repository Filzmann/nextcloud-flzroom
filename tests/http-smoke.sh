#!/usr/bin/env bash
set -euo pipefail

: "${FLZR_BASE_URL:?FLZR_BASE_URL fehlt}"
: "${FLZR_USER:?FLZR_USER fehlt}"
: "${FLZR_PASSWORD:?FLZR_PASSWORD fehlt}"

workdir="$(mktemp -d)"
trap 'rm -rf "$workdir"' EXIT
page="$workdir/page.html"
cookies="$workdir/cookies.txt"
state="$workdir/state.json"
created="$workdir/created.json"
conflict="$workdir/conflict.json"

curl --fail --silent --show-error --insecure --user "$FLZR_USER:$FLZR_PASSWORD" --cookie-jar "$cookies" "$FLZR_BASE_URL/index.php/apps/flzroom/" --output "$page"
for contract in 'id="flzroom-app"' 'data-current-app="flzroom"' 'id="flz-room-booking-dialog"'; do
    if ! grep -q "$contract" "$page"; then echo "App-DOM-Vertrag fehlt: $contract" >&2; exit 1; fi
done
token="$(php -r '$html=file_get_contents($argv[1]); preg_match("~data-requesttoken=\"([^\"]+)\"~", $html, $m); echo html_entity_decode($m[1] ?? "", ENT_QUOTES);' "$page")"
if [[ -z "$token" ]]; then echo 'Request-Token fehlt.' >&2; exit 1; fi

curl --fail --silent --show-error --insecure --user "$FLZR_USER:$FLZR_PASSWORD" --cookie "$cookies" --cookie-jar "$cookies" "$FLZR_BASE_URL/index.php/apps/flzroom/api/month/2035-01" --output "$state"
for contract in '"rooms"' '"bookings"' '"holidays"' '"canManageRooms":true'; do
    if ! grep -q "$contract" "$state"; then echo "API-Vertrag fehlt: $contract" >&2; exit 1; fi
done
room_id="$(php -r '$data=json_decode(file_get_contents($argv[1]),true); echo $data["rooms"][0]["id"] ?? "";' "$state")"
if [[ -z "$room_id" ]]; then echo 'Demo-Raum fehlt.' >&2; exit 1; fi
payload="{\"roomId\":$room_id,\"start\":\"2035-01-15T00:05\",\"end\":\"2035-01-15T00:10\",\"purpose\":\"Sitzung\",\"title\":\"HTTP Smoke\"}"

curl --fail --silent --show-error --insecure --user "$FLZR_USER:$FLZR_PASSWORD" --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" -H 'Content-Type: application/json' -X POST --data "$payload" "$FLZR_BASE_URL/index.php/apps/flzroom/api/bookings" --output "$created"
booking_id="$(php -r '$data=json_decode(file_get_contents($argv[1]),true); echo $data["id"] ?? "";' "$created")"
if [[ -z "$booking_id" ]]; then echo 'Buchung wurde nicht angelegt.' >&2; exit 1; fi

status="$(curl --silent --show-error --insecure --user "$FLZR_USER:$FLZR_PASSWORD" --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" -H 'Content-Type: application/json' -X POST --data "$payload" --write-out '%{http_code}' "$FLZR_BASE_URL/index.php/apps/flzroom/api/bookings" --output "$conflict")"
if [[ "$status" != '409' ]] || ! grep -q 'bereits belegt' "$conflict"; then echo "Ueberschneidung ergab HTTP $status statt 409." >&2; exit 1; fi

curl --fail --silent --show-error --insecure --user "$FLZR_USER:$FLZR_PASSWORD" --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" -H 'Content-Type: application/json' -X DELETE "$FLZR_BASE_URL/index.php/apps/flzroom/api/bookings/$booking_id" >/dev/null
echo "Filzmann Raumplaner HTTP smoke: OK ($FLZR_USER)"
