#!/usr/bin/env bash
set -euo pipefail

# Execute inside the local PHP container; all writes belong to a disposable clone.
server_root="$(realpath "${1:?Pass the local Nextcloud server root.}")"
app_root="$(cd "$(dirname "$0")/.." && pwd)"
intervention_mode="${2:-}"
[[ -z "$intervention_mode" || "$intervention_mode" == --with-foreign-intervention ]]
[[ -f "$server_root/occ" && -f "$server_root/lib/base.php" ]]
php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'
fixture_root="$(mktemp -d /tmp/flzroom-job-lifecycle.XXXXXX)"
cleanup()
{
    rm -rf -- "$fixture_root"
}
trap cleanup EXIT
mkdir -p "$fixture_root/server/config" "$fixture_root/server/custom_apps/flzroom"
tar -C "$server_root" --exclude='./config' --exclude='./data' \
    --exclude='./custom_apps' --exclude='./.git' -cf - . \
    | tar -C "$fixture_root/server" -xf -
tar -C "$app_root" --exclude='./.git' --exclude='./node_modules' -cf - . \
    | tar -C "$fixture_root/server/custom_apps/flzroom" -xf -
clone_root="$fixture_root/server"
clone_app="$clone_root/custom_apps/flzroom"
cp "$clone_app/appinfo/info.xml" "$fixture_root/target-info.xml"
if [[ "$intervention_mode" == --with-foreign-intervention ]]
then
    for provider in localbase flz_organization flz_data_protection
    do
        mkdir -p "$clone_root/custom_apps/$provider"
        tar -C "$server_root/custom_apps/$provider" --exclude='./.git' \
            --exclude='./node_modules' -cf - . \
            | tar -C "$clone_root/custom_apps/$provider" -xf -
    done
fi
cat > "$clone_root/config/isolation.config.php" <<'PHP'
<?php
$CONFIG = [
    'appstoreenabled' => false,
    'updatechecker' => false,
    'apps_paths' => [
        ['path' => __DIR__ . '/../apps', 'url' => '/apps', 'writable' => false],
        ['path' => __DIR__ . '/../custom_apps', 'url' => '/custom_apps', 'writable' => true],
    ],
];
PHP
export OC_PASS
OC_PASS="$(php -r 'echo bin2hex(random_bytes(24));')"
php "$clone_root/occ" maintenance:install --database=sqlite \
    --admin-user=synthetic-lifecycle-admin --admin-pass="$OC_PASS" \
    --data-dir="$fixture_root/data" --no-interaction > "$fixture_root/install.log" 2>&1
unset OC_PASS
if [[ "$intervention_mode" == --with-foreign-intervention ]]
then
    for provider in localbase flz_organization flz_data_protection notifications
    do
        php "$clone_root/occ" app:enable "$provider" >> "$fixture_root/enable.log" 2>&1
    done
fi
php "$clone_root/occ" app:enable flzroom > "$fixture_root/enable.log" 2>&1
php "$clone_app/tests/integration-background-job-registration.php" "$clone_root"

cat > "$fixture_root/lifecycle.php" <<'PHP'
<?php
declare(strict_types=1);
define('OC_CONSOLE', true);
require $argv[1] . '/lib/base.php';
$mode = $argv[2];
$jobs = \OCP\Server::get(\OCP\BackgroundJob\IJobList::class);
$jobClass = \OCA\FlzRoom\BackgroundJob\InterventionNotificationJob::class;
$db = \OCP\Server::get(\OCP\IDBConnection::class);
$countMigrations = static function () use ($db): int
{
    $query = $db->getQueryBuilder();
    return (int)$query->selectAlias($query->func()->count('*'), 'count')
        ->from('migrations')
        ->where($query->expr()->eq('app', $query->createNamedParameter('flzroom')))
        ->executeQuery()->fetchOne();
};
if ($countMigrations() !== 4)
{
    throw new RuntimeException('Fresh install did not apply all four app migrations.');
}
if ($mode === 'lose-job')
{
    // Reproduce an already-migrated development install with a missing scheduler row.
    $jobs->remove($jobClass, null);
    if ($jobs->has($jobClass, null))
    {
        throw new RuntimeException('The lost-registration fixture was not established.');
    }
    echo "Fresh-install migrations verified; missing-registration fixture prepared.\n";
    exit(0);
}
if ($mode !== 'upgrade')
{
    throw new RuntimeException('Unknown lifecycle test phase.');
}
if (!\OCP\Server::get(\OCP\App\IAppManager::class)->upgradeApp('flzroom'))
{
    throw new RuntimeException('Native app upgrade failed.');
}
if ($countMigrations() !== 4)
{
    throw new RuntimeException('The upgrade changed the migration inventory.');
}
echo "Native app upgrade completed without invoking the scheduled job.\n";
PHP

php "$fixture_root/lifecycle.php" "$clone_root" lose-job
php -r '
    $info = simplexml_load_file($argv[1]);
    unset($info->{"background-jobs"});
    $info->asXML($argv[1]);
' "$clone_app/appinfo/info.xml"
php "$fixture_root/lifecycle.php" "$clone_root" upgrade
set +e
php "$clone_app/tests/integration-background-job-registration.php" "$clone_root" \
    > "$fixture_root/red.log" 2>&1
red_status=$?
set -e
if [[ "$red_status" == 0 ]]
then
    cat "$fixture_root/red.log" >&2
    php "$clone_root/occ" status >&2
    php -r '$xml = simplexml_load_file($argv[1]); echo $xml->asXML();' "$clone_app/appinfo/info.xml" >&2
    echo 'The regression fixture unexpectedly passed.' >&2
    exit 1
fi
if ! php -r '
    $output = file_get_contents($argv[1]);
    exit(str_contains($output, "no intervention background job") ? 0 : 1);
' "$fixture_root/red.log"
then
    cat "$fixture_root/red.log" >&2
    exit 1
fi
echo 'RED: native upgrade without the declaration leaves the migrated app unregistered.'

cp "$fixture_root/target-info.xml" "$clone_app/appinfo/info.xml"
php "$fixture_root/lifecycle.php" "$clone_root" upgrade
php "$clone_app/tests/integration-background-job-registration.php" "$clone_root"
php "$fixture_root/lifecycle.php" "$clone_root" upgrade
php "$clone_app/tests/integration-background-job-registration.php" "$clone_root"
echo 'GREEN: fresh installation and repeated native upgrades register exactly one job.'
if [[ "$intervention_mode" == --with-foreign-intervention ]]
then
    php "$clone_app/tests/integration-foreign-booking-intervention.php" "$clone_root"
    echo 'Disposable SQLite fixture removed on exit; only the synthetic intervention job was run.'
else
    echo 'Disposable SQLite fixture removed on exit; no scheduled job executed.'
fi
