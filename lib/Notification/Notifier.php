<?php

declare(strict_types=1);

namespace OCA\AdRoom\Notification;

use DateTimeImmutable;
use InvalidArgumentException;
use OCA\AdRoom\AppInfo\AppId;
use OCA\LocalBase\Calendar\CalendarContextSettingsService;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

/** Renders only the minimized room/time/reason snapshot stored in the native notification. */
final class Notifier implements INotifier {
    public function __construct(private CalendarContextSettingsService $calendarContext) {
    }

    public function getID(): string {
        return AppId::VALUE;
    }

    public function getName(): string {
        return 'AD Raumplaner';
    }

    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== AppId::VALUE || $notification->getSubject() !== 'foreign_booking_intervention') {
            throw new InvalidArgumentException('Unbekannte AD-Raumplaner-Benachrichtigung.');
        }
        $parameters = $notification->getSubjectParameters();
        $old = $this->slot((string)$parameters['oldRoom'], (string)$parameters['oldStartsAt'], (string)$parameters['oldEndsAt']);
        $reason = (string)$parameters['reason'];
        if (($parameters['action'] ?? '') === 'delete') {
            $message = sprintf('Ihre Raumbuchung %s wurde durch das Sekretariat gelöscht. Begründung: %s', $old, $reason);
        } else {
            $new = $this->slot((string)$parameters['newRoom'], (string)$parameters['newStartsAt'], (string)$parameters['newEndsAt']);
            $message = sprintf('Ihre Raumbuchung wurde durch das Sekretariat von %s auf %s geändert. Begründung: %s', $old, $new, $reason);
        }
        return $notification->setParsedSubject($message)->setLink('/apps/adroom/');
    }

    private function slot(string $room, string $startsAt, string $endsAt): string {
        $start = new DateTimeImmutable($startsAt);
        $end = new DateTimeImmutable($endsAt);
        $timezone = $this->calendarContext->context()->timezone();
        $start = $start->setTimezone($timezone);
        $end = $end->setTimezone($timezone);
        return sprintf('%s am %s von %s bis %s Uhr', $room, $start->format('d.m.Y'), $start->format('H:i'), $end->format('H:i'));
    }
}
