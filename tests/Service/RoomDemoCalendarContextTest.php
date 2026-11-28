<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../../lib/Service/RoomDemoPackService.php');
if ($source === false) throw new RuntimeException('Raum-Demopack konnte nicht gelesen werden.');
foreach (['OrganizationCalendarContextService', '$this->contexts->timezone()'] as $contract) {
    if (!str_contains($source, $contract)) throw new RuntimeException("Demopack verwendet nicht den gemeinsamen Kalenderkontext: {$contract}");
}
if (str_contains($source, "new DateTimeZone('Europe/Berlin')")) throw new RuntimeException('Demopack enthält weiterhin eine feste Berliner Zeitzone.');
echo "RoomDemoCalendarContextTest: OK\n";
