<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/lib/Service/RoomDemoPackService.php');
$command = file_get_contents($root . '/lib/Command/SeedDemoCommand.php');
$controller = file_get_contents($root . '/lib/Controller/DemoAdminController.php');
$routes = file_get_contents($root . '/appinfo/routes.php');
$template = file_get_contents($root . '/templates/admin.php');
$script = file_get_contents($root . '/js/admin.js');
if (in_array(false, [$service, $command, $controller, $routes, $template, $script], true)) throw new RuntimeException('Raum-Demo-Pack ist unvollständig.');
foreach (['DemoAccountProvisioningService', "->provision('ad-suite-demo'", 'ad-demo-room', 'Besprechungsraum Nord', 'BookingConflictException'] as $contract) if (!str_contains($service, $contract)) throw new RuntimeException("Raum-Demo-Vertrag fehlt: {$contract}");
foreach (['RoomDemoPackService', '->install()'] as $contract) if (!str_contains($command, $contract)) throw new RuntimeException("Demo-Command delegiert nicht: {$contract}");
foreach (['IUserManager', "addOption('user'", "getOption('user'"] as $unsafe) if (str_contains($command, $unsafe)) throw new RuntimeException("Raum-Demo verwendet ein reales Konto: {$unsafe}");
foreach (['/api/admin/demo-pack/install', "'verb' => 'POST'"] as $contract) if (!str_contains($routes, $contract)) throw new RuntimeException("Demo-Route fehlt: {$contract}");
foreach (['RoomAccessService', '$this->access->canManageRooms()', 'Http::STATUS_FORBIDDEN'] as $contract) if (!str_contains($controller, $contract)) throw new RuntimeException("Zeitbegrenzter app-lokaler Adminschutz fehlt: {$contract}");
if (str_contains($controller, '$this->groups->isAdmin(')) throw new RuntimeException('Demo-Installation darf nativen Adminstatus nicht als Vollzugriff verwenden.');
if (str_contains($controller, 'NoCSRFRequired')) throw new RuntimeException('Demo-Installation umgeht CSRF.');
foreach (['id="adr-demo-confirm"', 'id="adr-demo-install"', 'nicht automatisch'] as $contract) if (!str_contains($template, $contract)) throw new RuntimeException("Demo-Adminoberfläche fehlt: {$contract}");
foreach (['adr-demo-confirm', 'adr-demo-install', "/api/admin/demo-pack/install"] as $contract) if (!str_contains($script, $contract)) throw new RuntimeException("Demo-Admininteraktion fehlt: {$contract}");

echo "DemoPackContractTest: OK\n";
