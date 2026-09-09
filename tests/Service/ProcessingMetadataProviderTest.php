<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    class Event { public function __construct() {} }
    interface IEventListener { public function handle(Event $event): void; }
}

namespace {
    use OCA\AdRoom\Privacy\RoomProcessingMetadataProvider;
    use OCA\AdRoom\Privacy\RoomProcessingMetadataProviderListener;
    use OCA\FilzmannDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
    use OCP\EventDispatcher\Event;

    $provider = new RoomProcessingMetadataProvider();
    $catalog = $provider->catalog();
    if ($provider->descriptor()->appId() !== 'adroom' || $catalog->appId() !== 'adroom') {
        throw new RuntimeException('Processing-Metadata-Provider und Katalog verwenden nicht die kanonische App-ID.');
    }
    if ($catalog->processingIds() !== ['room_booking_management', 'temporary_admin_full_access', 'personal_admin_layout']) {
        throw new RuntimeException('Der app-lokale Processing-Katalog ist unvollständig.');
    }
    if (array_key_exists('personal_runtime_data', $catalog->toArray())) {
        throw new RuntimeException('Der Processing-Katalog enthält personenbezogene Laufzeitdaten.');
    }

    $registration = new RegisterProcessingMetadataProvidersEvent();
    $listener = new RoomProcessingMetadataProviderListener($provider);
    $listener->handle(new Event());
    if ($registration->providers() !== []) {
        throw new RuntimeException('Ein fremdes Event registriert den Processing-Metadata-Provider.');
    }
    $listener->handle($registration);
    if (($registration->providers()['adroom'] ?? null) !== $provider) {
        throw new RuntimeException('Der Processing-Metadata-Provider wird nicht lazy registriert.');
    }

    echo "AD Raumplaner processing metadata provider test passed\n";
}
