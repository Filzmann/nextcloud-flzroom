<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$technicalAdminTemplate = (string)file_get_contents($root . '/templates/admin.php');
$template = (string)file_get_contents($root . '/templates/index.php');
$script = is_file($root . '/js/admin-access.js') ? (string)file_get_contents($root . '/js/admin-access.js') : '';
$routes = (string)file_get_contents($root . '/appinfo/routes.php');
$pageController = (string)file_get_contents($root . '/lib/Controller/PageController.php');
$accessController = (string)file_get_contents($root . '/lib/Controller/TemporaryAdminAccessController.php');

foreach (['adr-full-access-form','adr-full-access-enabled','adr-full-access-history','Maximal 24 Stunden'] as $contract) {
    if (!str_contains($template, $contract)) throw new RuntimeException("App-lokale DPO-Freigabesteuerung fehlt: {$contract}");
}
foreach (['/api/admin/full-access','durationMinutes','targetUid','Widerrufen'] as $contract) {
    if (!str_contains($script . $routes, $contract)) throw new RuntimeException("Vollzugriffs-UI/API-Vertrag fehlt: {$contract}");
}
if (!str_contains($routes, "'verb' => 'DELETE'")) throw new RuntimeException('Widerrufroute fehlt.');
if (str_contains($technicalAdminTemplate, 'adr-full-access-form')) throw new RuntimeException('Freigabesteuerung ist noch an den technischen Adminbereich gebunden.');
foreach (['canManageAdminAccess','showMissingAdminGrant','showAdminAccessLink'] as $contract) {
    if (!str_contains($template,$contract) || !str_contains($pageController,"'{$contract}'")) throw new RuntimeException("Rollenabhängige Eintrittsgrenze fehlt: {$contract}");
}
if (!str_contains($pageController, "'showAdminAccessLink' => \$canManageAdminAccess && \$showMissingAdminGrant")) throw new RuntimeException('Direktlink ist nicht auf gleichzeitige Datenschutz- und Adminrolle begrenzt.');
if (str_contains($accessController, 'PublicPage')) throw new RuntimeException('Freigaberouten dürfen nicht öffentlich erreichbar sein.');
foreach (['status','activate','revoke'] as $method) {
    if (!preg_match('/#\[NoAdminRequired\](?:\s+#\[NoCSRFRequired\])?\s+public function '.$method.'\(/',$accessController)) throw new RuntimeException("DPO-Nichtadmins erreichen {$method} nicht.");
}
if (!preg_match('/#\[NoAdminRequired\]\s+#\[NoCSRFRequired\]\s+public function status\(\)/',$accessController)) throw new RuntimeException('Read-only Statusvertrag fehlt.');
if (preg_match('/#\[NoCSRFRequired\]\s+public function (activate|revoke)\(/',$accessController)) throw new RuntimeException('Schreibende Freigabe umgeht CSRF.');

echo "AD Raumplaner admin full access UI contract tests passed\n";
