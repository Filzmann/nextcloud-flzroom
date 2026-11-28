<?php

declare(strict_types=1);

namespace OCP
{
    interface IURLGenerator
    {
        public function linkToRouteAbsolute(string $routeName, array $arguments = []): string;
    }
}

namespace OCP\Notification {
    interface INotification {
        public function getApp(): string;
        public function getSubject(): string;
        public function getSubjectParameters(): array;
        public function setParsedSubject(string $subject): self;
        public function setLink(string $link): self;
    }
    interface INotifier {
        public function getID(): string;
        public function getName(): string;
        public function prepare(INotification $notification, string $languageCode): INotification;
    }
}

namespace OCA\FlzRoom\Service {
    class OrganizationCalendarContextService {
        public function timezone(): \DateTimeZone { return new \DateTimeZone('Europe/Berlin'); }
    }
}

namespace {
    use OCA\FlzRoom\Notification\Notifier;
    use OCA\FlzRoom\Service\OrganizationCalendarContextService;
    use OCP\Notification\INotification;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $notification = new class implements INotification {
        public string $parsedSubject = '';
        public string $link = '';
        public string $action = 'update';
        public function getApp(): string { return 'flzroom'; }
        public function getSubject(): string { return 'foreign_booking_intervention'; }
        public function getSubjectParameters(): array {
            return [
                'action' => $this->action,
                'oldRoom' => 'Raum 4',
                'newRoom' => 'Raum 8',
                'oldStartsAt' => '2026-10-05T08:00:00+00:00',
                'oldEndsAt' => '2026-10-05T09:00:00+00:00',
                'newStartsAt' => '2026-10-05T10:00:00+00:00',
                'newEndsAt' => '2026-10-05T11:00:00+00:00',
                'reason' => 'Organisatorischer Raumkonflikt wurde abgestimmt.',
            ];
        }
        public function setParsedSubject(string $subject): self { $this->parsedSubject = $subject; return $this; }
        public function setLink(string $link): self { $this->link = $link; return $this; }
    };
    $urls = new class implements \OCP\IURLGenerator
    {
        public function linkToRouteAbsolute(string $routeName, array $arguments = []): string
        {
            if ($routeName !== 'flzroom.page.index')
            {
                throw new RuntimeException('Der Benachrichtigungslink verweist nicht auf den Raumplaner.');
            }
            return 'https://nextcloud.example.test/index.php/apps/flzroom/';
        }
    };
    $notifier = new Notifier(new OrganizationCalendarContextService(), $urls);

    $notifier->prepare($notification, 'de');
    $assert(filter_var($notification->link, FILTER_VALIDATE_URL) !== false, 'Der Benachrichtigungslink muss eine vollständige, von Nextcloud akzeptierte URL sein.');
    $assert($notification->link === 'https://nextcloud.example.test/index.php/apps/flzroom/', 'Der Benachrichtigungslink berücksichtigt die native Nextcloud-Basisadresse nicht.');
    $assert(str_contains($notification->parsedSubject, 'durch eine berechtigte Stelle'), 'Änderungsbenachrichtigung bezeichnet den rollenübergreifenden Fremdeingriff nicht neutral.');
    $assert(!str_contains($notification->parsedSubject, 'Sekretariat'), 'Änderungsbenachrichtigung behauptet fälschlich einen Sekretariatseingriff.');

    $notification->action = 'delete';
    $notifier->prepare($notification, 'de');
    $assert(str_contains($notification->parsedSubject, 'durch eine berechtigte Stelle'), 'Löschbenachrichtigung bezeichnet den rollenübergreifenden Fremdeingriff nicht neutral.');
    $assert(!str_contains($notification->parsedSubject, 'Sekretariat'), 'Löschbenachrichtigung behauptet fälschlich einen Sekretariatseingriff.');

    echo "Intervention notifier tests passed\n";
}
