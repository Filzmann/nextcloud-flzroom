<?php

declare(strict_types=1);

namespace OCP\EventDispatcher { class Event { public function __construct() {} } interface IEventListener { public function handle(Event $event): void; } }
namespace OCA\FlzRoom\AppInfo { final class Application { public const APP_ID = 'flzroom'; } }

namespace {
    use OCA\FlzRoom\Listener\IntegrationCapabilityQueryListener;
    use OCA\FlzOrganization\PublicApi\V1\IntegrationCapabilities;
    use OCA\FlzOrganization\PublicApi\V1\IntegrationCapabilityQueryEvent;

    $event = new IntegrationCapabilityQueryEvent(IntegrationCapabilities::all());
    (new IntegrationCapabilityQueryListener())->handle($event);
    if ($event->providersFor(IntegrationCapabilities::ROOM_AVAILABILITY_READ) !== ['flzroom']) throw new RuntimeException('Raum-Verfügbarkeitsfähigkeit fehlt.');
    if ($event->providersFor(IntegrationCapabilities::ROOM_BOOKING_WRITE) !== ['flzroom']) throw new RuntimeException('Raum-Buchungsfähigkeit fehlt.');
    if ($event->isAvailable(IntegrationCapabilities::ABSENCE_READ)) throw new RuntimeException('Raumplaner meldet eine fremde Fähigkeit.');

    echo "FLZ Raum capability listener test passed\n";
}
