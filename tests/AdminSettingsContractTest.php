<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$info = file_get_contents($root . '/appinfo/info.xml');
$admin = file_get_contents($root . '/lib/Settings/Admin.php');
$section = file_get_contents($root . '/lib/Settings/AdminSection.php');
$template = file_get_contents($root . '/templates/admin.php');
$script = file_get_contents($root . '/js/admin.js');
$routes = file_get_contents($root . '/appinfo/routes.php');

foreach ([$info, $admin, $section] as $source) {
    if ($source === false) throw new RuntimeException('Admin-Vertragsdatei konnte nicht gelesen werden.');
}

foreach (['<admin>OCA\AdRoom\Settings\Admin</admin>', '<admin-section>OCA\AdRoom\Settings\AdminSection</admin-section>'] as $contract) {
    if (!str_contains($info, $contract)) throw new RuntimeException("Admin-Registrierung fehlt: {$contract}");
}
foreach (['return Application::APP_ID;', "return 'AD Raumplaner';", 'IIconSection'] as $contract) {
    if (!str_contains($admin . $section, $contract)) throw new RuntimeException("Eigener Raumplaner-Adminabschnitt fehlt: {$contract}");
}
foreach (['data-dashboard-scope="main"', 'data-widget-id="rooms"', 'data-widget-id="demo"', 'data-dashboard-toggle', 'data-dashboard-move'] as $contract) if (!str_contains((string)$template,$contract)) throw new RuntimeException("Admin-Kartenvertrag fehlt: {$contract}");
foreach (['OrganizationDashboard','/api/admin/settings','/api/admin/layout'] as $contract) if (!str_contains((string)$script.(string)$routes,$contract)) throw new RuntimeException("Admin-Kartenanbindung fehlt: {$contract}");
foreach (['data-widget-id="retention"','adr-retention-form','/api/admin/retention-policy'] as $obsolete) if (str_contains((string)$template.(string)$script.(string)$routes,$obsolete)) throw new RuntimeException("Technische Admin-Retention ist noch aktiv: {$obsolete}");
foreach (['/api/privacy/retention-policy','/api/privacy/retention-policy/review'] as $contract) if (!str_contains((string)$routes,$contract)) throw new RuntimeException("DPO-Retention-Route fehlt: {$contract}");
if (str_contains($admin, "return 'orgsuite';")) throw new RuntimeException('Raumverwaltung darf nicht im Suite-Adminabschnitt hängen.');

echo "AdminSettingsContractTest: OK\n";
