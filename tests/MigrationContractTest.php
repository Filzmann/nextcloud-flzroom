<?php

declare(strict_types=1);

$initial = file_get_contents(__DIR__ . '/../lib/Migration/Version000001Date202607130001.php');
if ($initial === false) throw new RuntimeException('Initiale Raumplaner-Migration konnte nicht gelesen werden.');
if (!str_contains($initial, "addColumn('title', Types::STRING, ['length' => 255, 'notnull' => true])")) {
    throw new RuntimeException('Buchungstitel ist in der initialen Migration nicht verpflichtend.');
}
$adminAccess = file_get_contents(__DIR__ . '/../lib/Migration/Version000002Date202608250001.php');
if ($adminAccess === false) throw new RuntimeException('Additive Migration für temporären Admin-Vollzugriff fehlt.');
foreach (['adr_admin_access', 'target_uid', 'granted_by', 'starts_at', 'ends_at', 'revoked_at', 'revoked_by'] as $contract) {
    if (!str_contains($adminAccess, $contract)) throw new RuntimeException("Admin-Auditmigration ist unvollständig: {$contract}");
}
if (!str_contains($adminAccess, "if (!\$schema->hasTable('adr_admin_access'))")) throw new RuntimeException('Admin-Auditmigration muss wiederholbar additiv sein.');

echo "AD Raumplaner migration contract test passed\n";
