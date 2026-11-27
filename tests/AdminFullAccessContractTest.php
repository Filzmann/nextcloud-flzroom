<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$technicalAdminTemplate = (string)file_get_contents($root . '/templates/admin.php');
$template = (string)file_get_contents($root . '/templates/index.php');
$script = is_file($root . '/js/admin-access.js') ? (string)file_get_contents($root . '/js/admin-access.js') : '';
$bundleOverlay = is_file($root . '/js/shared/overlay.js') ? (string)file_get_contents($root . '/js/shared/overlay.js') : '';
$routes = (string)file_get_contents($root . '/appinfo/routes.php');
$pageController = (string)file_get_contents($root . '/lib/Controller/PageController.php');
$accessController = (string)file_get_contents($root . '/lib/Controller/TemporaryAdminAccessController.php');

foreach (['flz-room-full-access-form','flz-room-full-access-enabled','flz-room-full-access-history','Maximal 24 Stunden'] as $contract) {
    if (!str_contains($template, $contract)) throw new RuntimeException("App-lokale DPO-Freigabesteuerung fehlt: {$contract}");
}
foreach (['/api/admin/full-access','durationMinutes','targetUid'] as $contract) {
    if (!str_contains($script . $routes, $contract)) throw new RuntimeException("Vollzugriffs-UI/API-Vertrag fehlt: {$contract}");
}
if (!str_contains($routes, "'verb' => 'DELETE'")) throw new RuntimeException('Widerrufroute fehlt.');
if (str_contains($technicalAdminTemplate, 'flz-room-full-access-form')) throw new RuntimeException('Freigabesteuerung ist noch an den technischen Adminbereich gebunden.');
foreach (['canManageAdminAccess','showMissingAdminGrant','showAdminAccessLink'] as $contract) {
    if (!str_contains($template,$contract) || !str_contains($pageController,"'{$contract}'")) throw new RuntimeException("Rollenabhängige Eintrittsgrenze fehlt: {$contract}");
}
if (!str_contains($pageController, "'showAdminAccessLink' => \$canManageAdminAccess && \$showMissingAdminGrant")) throw new RuntimeException('Direktlink ist nicht auf gleichzeitige Datenschutz- und Adminrolle begrenzt.');
if (str_contains($accessController, 'PublicPage')) throw new RuntimeException('Freigaberouten dürfen nicht öffentlich erreichbar sein.');

// Overlay-Vertrag: Das Warn-Icon am App-Titel ist ein Button, der ein modales Overlay öffnet.
$overlayId = 'flz-room-admin-access-overlay';
foreach ([
    'id="' . $overlayId . '"',
    'role="dialog"',
    'aria-modal="true"',
    'flz-room-admin-access-overlay-close',
    'id="flz-room-admin-grant-warning"',
    'aria-haspopup="dialog"',
    'aria-controls="' . $overlayId . '"',
    'aria-expanded',
] as $contract) {
    if (!str_contains($template, $contract)) throw new RuntimeException("Overlay-Vertrag fehlt in der Vorlage: {$contract}");
}
if (str_contains($template, '<details class="flz-room-admin-grant-warning"')) throw new RuntimeException('Warn-Icon ist noch als aufklappbares details-Element umgesetzt.');
if (str_contains($template, 'href="#flz-room-full-access"')) throw new RuntimeException('Veralteter Anchor-Verweis auf die Freigabesteuerung ist noch vorhanden.');
$overlayPos = strpos($template, 'id="' . $overlayId . '"');
$sectionPos = strpos($template, 'flz-room-full-access-form');
$warningPos = strpos($template, 'Kein fachlicher Admin-Vollzugriff.');
if ($overlayPos === false || $sectionPos === false || $warningPos === false || $overlayPos >= $sectionPos || $overlayPos >= $warningPos) throw new RuntimeException('Warnung und Freigabesteuerung liegen nicht innerhalb des Overlays.');
if (!str_contains($script, $overlayId) || !str_contains($script, 'AdminAccessOverlay')) throw new RuntimeException("Overlay-Steuerung fehlt im JS: {$overlayId}");
foreach (['Overlay','AdminAccessOverlay','Escape','aria-expanded','data-open-admin-access','flz-shared-overlay','flz-shared-warn-trigger','Widerrufen','hidden'] as $contract) {
    if (!str_contains($bundleOverlay, $contract)) throw new RuntimeException("Geteiltes Overlay-Bundle fehlt: {$contract}");
}
foreach (["addScript('flzroom','shared/overlay')", 'class="flz-shared-overlay"', 'class="flz-shared-warn-trigger"'] as $contract) {
    if (!str_contains($template, $contract)) throw new RuntimeException("Overlay-Vertrag fehlt in der Vorlage: {$contract}");
}
foreach (['status','activate','revoke'] as $method) {
    if (!preg_match('/#\[NoAdminRequired\](?:\s+#\[NoCSRFRequired\])?\s+public function '.$method.'\(/',$accessController)) throw new RuntimeException("DPO-Nichtadmins erreichen {$method} nicht.");
}
if (!preg_match('/#\[NoAdminRequired\]\s+#\[NoCSRFRequired\]\s+public function status\(\)/',$accessController)) throw new RuntimeException('Read-only Statusvertrag fehlt.');
if (preg_match('/#\[NoCSRFRequired\]\s+public function (activate|revoke)\(/',$accessController)) throw new RuntimeException('Schreibende Freigabe umgeht CSRF.');

echo "Filzmann Raumplaner admin full access UI contract tests passed\n";
