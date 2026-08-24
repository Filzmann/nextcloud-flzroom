<?php

declare(strict_types=1);

namespace OCP {
    interface IRequest {}
}

namespace OCP\AppFramework {
    class Controller {
        public function __construct(string $appName, \OCP\IRequest $request) {}
    }

    final class Http {
        public const STATUS_CREATED = 201;
        public const STATUS_BAD_REQUEST = 400;
        public const STATUS_FORBIDDEN = 403;
        public const STATUS_NOT_FOUND = 404;
        public const STATUS_CONFLICT = 409;
    }
}

namespace OCP\AppFramework\Http {
    final class JSONResponse {
        public function __construct(private array $data = [], private int $status = 200) {}
        public function getData(): array { return $this->data; }
        public function getStatus(): int { return $this->status; }
    }
}

namespace OCP\AppFramework\Http\Attribute {
    #[\Attribute(\Attribute::TARGET_METHOD)] final class NoAdminRequired {}
    #[\Attribute(\Attribute::TARGET_METHOD)] final class NoCSRFRequired {}
}

namespace Psr\Log {
    interface LoggerInterface {
        public function error(string $message, array $context = []): void;
        public function warning(string $message, array $context = []): void;
    }
}

namespace OCA\AdRoom\AppInfo {
    final class Application { public const APP_ID = 'adroom'; }
}

namespace OCA\AdRoom\Service {
    final class RoomAccessService {
        public bool $view = true;
        public bool $manageBooking = true;
        public bool $manageRooms = true;
        public function canView(): bool { return $this->view; }
        public function currentUid(): string { return 'anna'; }
        public function canManageBooking(object $booking): bool { return $this->manageBooking; }
        public function canManageRooms(): bool { return $this->manageRooms; }
    }

    final class BookingService {
        public string $mode = 'success';
        public ?int $deleted = null;

        public function month(string $month, RoomAccessService $access): array {
            if ($this->mode === 'generic') throw new \RuntimeException('intern');
            return ['month' => $month];
        }

        public function create(int $roomId, string $start, string $end, string $purpose, string $title, string $uid): int {
            $this->throwConfigured();
            return 17;
        }

        public function existing(int $id): object {
            if ($this->mode === 'missing') throw new \OutOfBoundsException('Buchung nicht gefunden.');
            if ($this->mode === 'existing-error') throw new \RuntimeException('intern');
            return (object)['id' => $id];
        }

        public function update(object $booking, int $roomId, string $start, string $end, string $purpose, string $title): int {
            $this->throwConfigured();
            return 23;
        }

        public function delete(int $id): void {
            if ($this->mode === 'delete-error') throw new \RuntimeException('intern');
            $this->deleted = $id;
        }

        private function throwConfigured(): void {
            if ($this->mode === 'conflict') throw new \OCA\AdRoom\Exception\BookingConflictException('Bereits belegt.');
            if ($this->mode === 'not-found') throw new \OutOfBoundsException('Raum nicht gefunden.');
            if ($this->mode === 'generic') throw new \InvalidArgumentException('intern');
        }
    }

    final class RoomService {
        public string $mode = 'success';
        public ?int $deleted = null;

        public function save(?int $id, string $name, string $description, int $sortOrder): int {
            if ($this->mode === 'missing') throw new \OutOfBoundsException('Raum nicht gefunden.');
            if ($this->mode === 'generic') throw new \InvalidArgumentException('intern');
            return $id ?? 31;
        }

        public function delete(int $id): void {
            if ($this->mode === 'missing') throw new \OutOfBoundsException('Raum nicht gefunden.');
            if ($this->mode === 'generic') throw new \RuntimeException('intern');
            $this->deleted = $id;
        }
    }
}

namespace {
    use OCA\AdRoom\Controller\ApiController;
    use OCA\AdRoom\Service\BookingService;
    use OCA\AdRoom\Service\RoomAccessService;
    use OCA\AdRoom\Service\RoomService;
    use OCP\AppFramework\Http;

    $request = new class implements OCP\IRequest {};
    $access = new RoomAccessService();
    $bookings = new BookingService();
    $rooms = new RoomService();
    $logger = new class implements Psr\Log\LoggerInterface {
        public array $errors = [];
        public array $warnings = [];
        public function error(string $message, array $context = []): void { $this->errors[] = $message; }
        public function warning(string $message, array $context = []): void { $this->warnings[] = $message; }
    };
    $controller = new ApiController($request, $access, $bookings, $rooms, $logger);

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $status = static fn ($response): int => $response->getStatus();
    $data = static fn ($response): array => $response->getData();

    $access->view = false;
    $assert($status($controller->month('2026-07')) === Http::STATUS_FORBIDDEN, 'Monatsansicht ignoriert das Leserecht.');
    $assert($status($controller->createBooking(1, '2026-07-13T08:00', '2026-07-13T09:00', 'AT', 'Team A')) === Http::STATUS_FORBIDDEN, 'Buchungsanlage ignoriert das Leserecht.');
    $access->view = true;

    $assert($data($controller->month('2026-07'))['month'] === '2026-07', 'Monatsdaten werden nicht durchgereicht.');
    $bookings->mode = 'generic';
    $assert($status($controller->month('ungueltig')) === Http::STATUS_BAD_REQUEST, 'Monatsfehler erhält keinen sicheren Status.');
    $assert($logger->errors === ['Raummonat konnte nicht geladen werden.'], 'Monatsfehler wird nicht protokolliert.');

    $bookings->mode = 'success';
    $response = $controller->createBooking(1, '2026-07-13T08:00', '2026-07-13T09:00', 'AT', 'Team A');
    $assert($status($response) === Http::STATUS_CREATED && $data($response)['id'] === 17, 'Buchungsanlage liefert keinen Created-Vertrag.');
    foreach (['conflict' => Http::STATUS_CONFLICT, 'not-found' => Http::STATUS_NOT_FOUND, 'generic' => Http::STATUS_BAD_REQUEST] as $mode => $expected) {
        $bookings->mode = $mode;
        $assert($status($controller->createBooking(1, '2026-07-13T08:00', '2026-07-13T09:00', 'AT', 'Team A')) === $expected, "Fehlerstatus bei Buchungsanlage ist falsch: {$mode}");
    }

    $bookings->mode = 'success';
    $access->manageBooking = false;
    $assert($status($controller->updateBooking(3, 1, '2026-07-13T08:00', '2026-07-13T09:00', 'AT', 'Team A')) === Http::STATUS_FORBIDDEN, 'Fremde Buchung kann geändert werden.');
    $assert($status($controller->deleteBooking(3)) === Http::STATUS_FORBIDDEN, 'Fremde Buchung kann gelöscht werden.');
    $access->manageBooking = true;
    $assert($data($controller->updateBooking(3, 1, '2026-07-13T08:00', '2026-07-13T09:00', 'AT', 'Team A'))['id'] === 23, 'Buchungsänderung liefert falsche ID.');
    foreach (['conflict' => Http::STATUS_CONFLICT, 'missing' => Http::STATUS_NOT_FOUND, 'generic' => Http::STATUS_BAD_REQUEST] as $mode => $expected) {
        $bookings->mode = $mode;
        $assert($status($controller->updateBooking(3, 1, '2026-07-13T08:00', '2026-07-13T09:00', 'AT', 'Team A')) === $expected, "Fehlerstatus bei Buchungsänderung ist falsch: {$mode}");
    }
    $bookings->mode = 'success';
    $assert($data($controller->deleteBooking(4))['deleted'] === true && $bookings->deleted === 4, 'Buchung wird nicht gelöscht.');
    foreach (['missing' => Http::STATUS_NOT_FOUND, 'delete-error' => Http::STATUS_BAD_REQUEST] as $mode => $expected) {
        $bookings->mode = $mode;
        $assert($status($controller->deleteBooking(4)) === $expected, "Fehlerstatus beim Buchungslöschen ist falsch: {$mode}");
    }

    $access->manageRooms = false;
    $assert($status($controller->createRoom('Nord')) === Http::STATUS_FORBIDDEN, 'Raum kann ohne Adminrecht angelegt werden.');
    $assert($status($controller->updateRoom(2, 'Nord')) === Http::STATUS_FORBIDDEN, 'Raum kann ohne Adminrecht geändert werden.');
    $assert($status($controller->deleteRoom(2)) === Http::STATUS_FORBIDDEN, 'Raum kann ohne Adminrecht gelöscht werden.');
    $access->manageRooms = true;

    $rooms->mode = 'success';
    $assert($status($controller->createRoom('Nord')) === Http::STATUS_CREATED, 'Raumanlage liefert keinen Created-Vertrag.');
    $assert($data($controller->updateRoom(2, 'Nord'))['id'] === 2, 'Raumänderung liefert falsche ID.');
    $assert($data($controller->deleteRoom(2))['deleted'] === true && $rooms->deleted === 2, 'Raum wird nicht gelöscht.');
    foreach (['missing' => Http::STATUS_NOT_FOUND, 'generic' => Http::STATUS_BAD_REQUEST] as $mode => $expected) {
        $rooms->mode = $mode;
        if ($mode === 'generic') {
            $assert($status($controller->createRoom('Nord')) === $expected, 'Fehlerstatus bei Raumanlage ist falsch.');
        }
        $assert($status($controller->updateRoom(2, 'Nord')) === $expected, "Fehlerstatus bei Raumänderung ist falsch: {$mode}");
        $assert($status($controller->deleteRoom(2)) === $expected, "Fehlerstatus beim Raumlöschen ist falsch: {$mode}");
    }

    echo "AD Raumplaner controller execution tests passed\n";
}
