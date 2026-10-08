<?php

declare(strict_types=1);

namespace OCP\EventDispatcher { class Event { public function __construct() {} } interface IEventListener { public function handle(Event $event): void; } }
namespace OCA\FlzRoom\AppInfo { final class Application { public const APP_ID = 'flzroom'; } }

namespace {
    use OCA\FlzRoom\Listener\IntegrationCapabilityQueryListener;
    use OCA\LocalBase\Integration\FlzIntegrationCapabilities;
    use OCA\LocalBase\Integration\IntegrationCapabilityQueryEvent;

    $event = new IntegrationCapabilityQueryEvent(FlzIntegrationCapabilities::all());
    (new IntegrationCapabilityQueryListener())->handle($event);
    if ($event->providersFor(FlzIntegrationCapabilities::ROOM_AVAILABILITY_READ) !== ['flzroom']) throw new RuntimeException('Raum-Verfügbarkeitsfähigkeit fehlt.');
    if ($event->providersFor(FlzIntegrationCapabilities::ROOM_BOOKING_WRITE) !== ['flzroom']) throw new RuntimeException('Raum-Buchungsfähigkeit fehlt.');
    if ($event->isAvailable(FlzIntegrationCapabilities::ABSENCE_READ)) throw new RuntimeException('Raumplaner meldet eine fremde Fähigkeit.');

    echo "FLZ Raum capability listener test passed\n";
}
