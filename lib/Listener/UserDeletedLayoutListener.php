<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Listener;

use OCA\FlzRoom\Service\RoomAdminLayoutService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/**
 * Löscht das persönliche Admin-Layout bei Kontolöschung des Kontoinhabers.
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
final class UserDeletedLayoutListener implements IEventListener {
    public function __construct(private RoomAdminLayoutService $layout) {
    }

    public function handle(Event $event): void {
        if (!$event instanceof UserDeletedEvent) {
            return;
        }
        $this->layout->deleteForUser($event->getUser()->getUID());
    }
}
