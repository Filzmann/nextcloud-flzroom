<?php

declare(strict_types=1);

$source=file_get_contents(__DIR__.'/../../lib/Controller/ApiController.php');
$page=file_get_contents(__DIR__.'/../../lib/Controller/PageController.php');
$routes=file_get_contents(__DIR__.'/../../appinfo/routes.php');
if ($source===false || $page===false || $routes===false) throw new RuntimeException('Controllervertrag konnte nicht gelesen werden.');
foreach (['canManageBooking','canManageRooms','currentUid','STATUS_FORBIDDEN','STATUS_CONFLICT'] as $contract) if(!str_contains($source,$contract)) throw new RuntimeException("Sicherheitsvertrag fehlt: {$contract}");
foreach (['createBooking','updateBooking','deleteBooking','createRoom','updateRoom','deleteRoom'] as $method) { $position=strpos($source,'function '.$method); $prefix=substr($source,max(0,$position-100),100); if(str_contains($prefix,'NoCSRFRequired')) throw new RuntimeException("{$method} darf CSRF nicht deaktivieren."); }
foreach (['createRoom','updateRoom','deleteRoom'] as $method) { $position=strpos($source,'function '.$method); $prefix=substr($source,max(0,$position-100),100); if(str_contains($prefix,'NoAdminRequired')) throw new RuntimeException("{$method} muss auf Nextcloud-Admins beschränkt bleiben."); }
if (!str_contains($page,'NoAdminRequired') || !str_contains($page,'NoCSRFRequired')) throw new RuntimeException('Seitenattribute fehlen.');
foreach (["'verb' => 'POST'","'verb' => 'PUT'","'verb' => 'DELETE'"] as $contract) if(!str_contains($routes,$contract)) throw new RuntimeException("Schreibroute fehlt: {$contract}");
echo "Filzmann Raumplaner controller security smoke test passed\n";
