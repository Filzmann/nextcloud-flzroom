<?php

declare(strict_types=1);

namespace OCA\AdRoom\Privacy;

use OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

final class RoomPersonalDataProviderListener implements IEventListener {
    public function __construct(private RoomPersonalDataProvider $personalData) {
    }

    public function handle(Event $event): void {
        if ($event instanceof RegisterPersonalDataProvidersEvent) {
            $event->register($this->personalData);
        }
    }
}
