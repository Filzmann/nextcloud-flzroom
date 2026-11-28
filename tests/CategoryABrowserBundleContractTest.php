<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$bundleRoot = $root . '/js/shared';
$violations = [];

foreach (['api-client.js', 'model.js', 'overlay.js', 'repository.js', 'ui.js', 'LICENSE', 'flz-shared.lock.json'] as $asset) {
    if (!is_file($bundleRoot . '/' . $asset)) {
        $violations[] = 'js/shared/' . $asset . ': app-lokales Kategorie-A-Artefakt fehlt';
    }
}

$lockPath = $bundleRoot . '/flz-shared.lock.json';
if (is_file($lockPath)) {
    $lock = json_decode((string)file_get_contents($lockPath), true, flags: JSON_THROW_ON_ERROR);
    if (($lock['schema'] ?? null) !== 1
        || ($lock['component'] ?? null) !== 'browser'
        || ($lock['app_id'] ?? null) !== 'flzroom'
        || ($lock['app_namespace'] ?? null) !== 'FlzRoom'
        || ($lock['browser_global'] ?? null) !== 'window.OCA.FlzRoom.Shared'
        || ($lock['packages']['browser']['name'] ?? null) !== '@filzmann/shared-browser'
        || ($lock['packages']['browser']['version'] ?? null) !== '0.1.0'
        || !preg_match('/^[a-f0-9]{64}$/', (string)($lock['packages']['browser']['sha256'] ?? ''))
        || array_keys($lock['packages'] ?? []) !== ['browser']) {
        $violations[] = 'js/shared/flz-shared.lock.json: Browser-Paket, Zielglobal oder Quelldigest ist inkohärent';
    }
}

$licensePath = $bundleRoot . '/LICENSE';
if (is_file($licensePath)) {
    $license = (string)file_get_contents($licensePath);
    if (!str_contains($license, 'SPDX-License-Identifier: AGPL-3.0-or-later')
        || !str_contains($license, 'GNU Affero General Public License')) {
        $violations[] = 'js/shared/LICENSE: gebündelte Lizenzmetadaten fehlen';
    }
}

$indexTemplate = (string)file_get_contents($root . '/templates/index.php');
$adminTemplate = (string)file_get_contents($root . '/templates/admin.php');
$adminSource = (string)file_get_contents($root . '/js/admin.js');
foreach (['api-client', 'model', 'repository', 'ui'] as $asset) {
    foreach (['templates/index.php' => $indexTemplate, 'templates/admin.php' => $adminTemplate] as $path => $template) {
        if (!str_contains($template, "addScript('flzroom', 'shared/{$asset}')")
            && !str_contains($template, "addScript('flzroom','shared/{$asset}')")) {
            $violations[] = "{$path}: shared/{$asset} wird nicht app-lokal geladen";
        }
    }
}
if (!str_contains($indexTemplate, "addScript('flzroom','shared/overlay')")
    && !str_contains($indexTemplate, "addScript('flzroom', 'shared/overlay')")) {
    $violations[] = 'templates/index.php: shared/overlay wird nicht app-lokal geladen';
}
if (strpos($indexTemplate, 'shared/overlay') > strpos($indexTemplate, "addScript('flzroom','admin-access')")) {
    $violations[] = 'templates/index.php: shared/overlay wird nicht vor admin-access geladen';
}
if (!str_contains($adminTemplate, "addScript('flzroom', 'components/organization-dashboard')")
    || !str_contains($adminSource, 'window.FlzRoom.OrganizationDashboard')
    || !is_file($root . '/js/components/organization-dashboard.js')) {
    $violations[] = 'Die app-lokale Dashboard-Komponente wird nicht app-lokal geladen';
}
if (str_contains($adminTemplate, "addScript('localbase'") || str_contains($adminTemplate, "addStyle('localbase'")
    || str_contains($adminSource, 'window.LocalBase')) {
    $violations[] = 'Admin lädt weiterhin LocalBase-Assets oder -Globals';
}

$sourceRoots = [$root . '/js', $root . '/templates'];
foreach ($sourceRoots as $sourceRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $contents = (string)file_get_contents($file->getPathname());
        foreach ([
            'window.LocalBase',
            "addScript('localbase'",
            "addStyle('localbase'",
            'window.LocalBase.api',
            'window.LocalBase.models',
            'window.LocalBase.repositories',
            'window.LocalBase.ui',
            "addScript('localbase', 'api/",
            "addScript('localbase','api/",
            "addScript('localbase', 'models/",
            "addScript('localbase','models/",
            "addScript('localbase', 'repositories/",
            "addScript('localbase','repositories/",
            "addScript('localbase', 'ui/",
            "addScript('localbase','ui/",
            '/../flz_shared/',
            '/../../flz_shared/',
        ] as $forbidden) {
            if (str_contains($contents, $forbidden)) {
                $violations[] = substr($file->getPathname(), strlen($root) + 1) . ': ' . $forbidden;
            }
        }
    }
}

$loadOrder = [
    'templates/index.php' => [$indexTemplate, "addScript('flzroom','models/room')"],
    'templates/admin.php' => [$adminTemplate, "addScript('flzroom', 'models/room')"],
];
foreach ($loadOrder as $path => [$template, $firstConsumer]) {
    $sharedPosition = strpos($template, 'shared/ui');
    $consumerPosition = strpos($template, $firstConsumer);
    if ($sharedPosition === false || $consumerPosition === false || $sharedPosition > $consumerPosition) {
        $violations[] = "{$path}: Shared-Primitives werden nicht vor ihren Consumern geladen";
    }
}

if ($violations !== []) {
    throw new RuntimeException(
        "Filzmann Raumplaner besitzt keinen isolierten Kategorie-A-Browservertrag:\n- "
        . implode("\n- ", array_unique($violations)),
    );
}

echo "CategoryABrowserBundleContractTest: OK\n";
