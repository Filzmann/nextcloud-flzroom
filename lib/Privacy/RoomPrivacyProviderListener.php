<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Privacy;

use OCA\FlzDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

final class RoomPrivacyProviderListener implements IEventListener {
    public function __construct(private RoomRetentionProvider $retention) {}

    public function handle(Event $event): void {
        if ($event instanceof RegisterRetentionProvidersEvent && $this->retention->isEnabled()) $event->register($this->retention);
    }
}
