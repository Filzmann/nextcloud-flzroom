<?php

declare(strict_types=1);

namespace OCA\AdRoom\Privacy;

use OCA\LocalBase\Privacy\RetentionProviderRegistryEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

final class RoomPrivacyProviderListener implements IEventListener {
    public function __construct(private RoomRetentionProvider $retention) {}

    public function handle(Event $event): void {
        if ($event instanceof RetentionProviderRegistryEvent) $event->register($this->retention);
    }
}
