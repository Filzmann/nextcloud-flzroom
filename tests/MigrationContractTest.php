<?php

declare(strict_types=1);

$initial = file_get_contents(__DIR__ . '/../lib/Migration/Version000001Date202607130001.php');
if ($initial === false) throw new RuntimeException('Initiale Raumplaner-Migration konnte nicht gelesen werden.');
if (!str_contains($initial, "addColumn('title', Types::STRING, ['length' => 255, 'notnull' => true])")) {
    throw new RuntimeException('Buchungstitel ist in der initialen Migration nicht verpflichtend.');
}
$adminAccess = file_get_contents(__DIR__ . '/../lib/Migration/Version000002Date202608250001.php');
if ($adminAccess === false) throw new RuntimeException('Additive Migration für temporären Admin-Vollzugriff fehlt.');
foreach (['flz_room_admin_access', 'target_uid', 'granted_by', 'starts_at', 'ends_at', 'revoked_at', 'revoked_by'] as $contract) {
    if (!str_contains($adminAccess, $contract)) throw new RuntimeException("Admin-Auditmigration ist unvollständig: {$contract}");
}
if (!str_contains($adminAccess, "if (!\$schema->hasTable('flz_room_admin_access'))")) throw new RuntimeException('Admin-Auditmigration muss wiederholbar additiv sein.');

$intervention = file_get_contents(__DIR__ . '/../lib/Migration/Version000003Date202609290001.php');
if ($intervention === false) throw new RuntimeException('Additive Migration für Sekretariatseingriffe fehlt.');
foreach (['flz_room_booking_audit', 'actor_uid', 'booking_id', 'old_room_name', 'new_room_name', 'reason', 'flz_room_notify_queue', 'recipient_uid', 'attempt_count', 'next_attempt_at', 'failed_at', 'error_code'] as $contract) {
    if (!str_contains($intervention, $contract)) throw new RuntimeException("Sekretariatseingriffsmigration ist unvollständig: {$contract}");
}
foreach (['flz_room_booking_audit', 'flz_room_notify_queue'] as $table) {
    if (!str_contains($intervention, "if (!\$schema->hasTable('{$table}'))")) throw new RuntimeException("Migration ist für {$table} nicht wiederholbar additiv.");
}
if (!str_contains($intervention, 'InterventionNotificationJob::class')) throw new RuntimeException('Wiederholbarer Queue-/Retention-Job wird nicht registriert.');

echo "Filzmann Raumplaner migration contract test passed\n";
