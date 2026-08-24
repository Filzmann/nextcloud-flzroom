<?php

declare(strict_types=1);

namespace OCP\EventDispatcher { class Event { public function __construct() {} } interface IEventListener { public function handle(Event $event): void; } }
namespace OCA\AdRoom\AppInfo { final class Application { public const APP_ID = 'adroom'; } }

namespace {
    use OCA\AdRoom\Listener\IntegrationCapabilityQueryListener;
    use OCA\LocalBase\Integration\AdIntegrationCapabilities;
    use OCA\LocalBase\Integration\IntegrationCapabilityQueryEvent;

    $event = new IntegrationCapabilityQueryEvent(AdIntegrationCapabilities::all());
    (new IntegrationCapabilityQueryListener())->handle($event);
    if ($event->providersFor(AdIntegrationCapabilities::ROOM_AVAILABILITY_READ) !== ['adroom']) throw new RuntimeException('Raum-Verfügbarkeitsfähigkeit fehlt.');
    if ($event->providersFor(AdIntegrationCapabilities::ROOM_BOOKING_WRITE) !== ['adroom']) throw new RuntimeException('Raum-Buchungsfähigkeit fehlt.');
    if ($event->isAvailable(AdIntegrationCapabilities::ABSENCE_READ)) throw new RuntimeException('Raumplaner meldet eine fremde Fähigkeit.');

    echo "AD Raum capability listener test passed\n";
}
