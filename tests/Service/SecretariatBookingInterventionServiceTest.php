<?php

declare(strict_types=1);

namespace OCP {
    interface IDBConnection {
        public function beginTransaction(): void;
        public function commit(): void;
        public function rollBack(): void;
    }
}

namespace Psr\Log {
    interface LoggerInterface {
        public function warning(string $message, array $context = []): void;
    }
}

namespace OCA\AdRoom\Service {
    use OCA\AdRoom\Model\Booking;
    use OCA\AdRoom\Model\Room;

    final class BookingService {
        public ?Booking $booking = null;
        public array $updates = [];
        public array $deletes = [];
        public function existing(int $id): Booking {
            if ($this->booking === null || $this->booking->id() !== $id) throw new \OutOfBoundsException('Buchung nicht gefunden.');
            return $this->booking;
        }
        public function update(Booking $booking, int $roomId, string $start, string $end, string $purpose, string $title): int {
            $this->updates[] = [$booking, $roomId, $start, $end, $purpose, $title];
            $this->booking = Booking::get([
                'id' => $booking->id(),
                'roomId' => $roomId,
                'userUid' => $booking->userUid(),
                'purpose' => $purpose,
                'title' => $title,
                'startsAt' => $start . ':00+00:00',
                'endsAt' => $end . ':00+00:00',
            ]);
            return (int)$booking->id();
        }
        public function delete(int $id): void { $this->deletes[] = $id; }
    }

    final class SecretariatForeignBookingInterventionGuard {
        public ?string $actorUid = 'secretariat-user';
        public function authorizedActorUid(Booking $booking): ?string { return $this->actorUid; }
    }

    final class RoomService {
        public function get(int $id): ?Room { return Room::get(['id' => $id, 'name' => 'Raum ' . $id]); }
    }

    final class InterventionNotificationDeliveryService {
        public array $delivered = [];
        public bool $throw = false;
        public function deliverById(int $id): void {
            $this->delivered[] = $id;
            if ($this->throw) throw new \RuntimeException('synthetic delivery failure');
        }
    }
}

namespace OCA\AdRoom\Repository {
    final class BookingInterventionAuditRepository {
        public array $entries = [];
        public bool $throw = false;
        public function append(array $entry): int {
            if ($this->throw) throw new \RuntimeException('synthetic audit failure');
            $this->entries[] = $entry;
            return count($this->entries);
        }
    }

    final class InterventionNotificationQueueRepository {
        public array $entries = [];
        public bool $throw = false;
        public function enqueue(array $entry): int {
            if ($this->throw) throw new \RuntimeException('synthetic queue failure');
            $this->entries[] = $entry;
            return 41;
        }
    }
}

namespace {
    use OCA\AdRoom\Model\Booking;
    use OCA\AdRoom\Repository\BookingInterventionAuditRepository;
    use OCA\AdRoom\Repository\InterventionNotificationQueueRepository;
    use OCA\AdRoom\Service\BookingService;
    use OCA\AdRoom\Service\InterventionNotificationDeliveryService;
    use OCA\AdRoom\Service\RoomService;
    use OCA\AdRoom\Service\SecretariatBookingInterventionService;
    use OCA\AdRoom\Service\SecretariatForeignBookingInterventionGuard;
    use OCP\IDBConnection;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $booking = Booking::get([
        'id' => 17,
        'roomId' => 4,
        'userUid' => 'booking-owner',
        'purpose' => 'Besprechung',
        'title' => 'Nicht im Audit speichern',
        'startsAt' => '2026-10-05T08:00:00+00:00',
        'endsAt' => '2026-10-05T09:00:00+00:00',
    ]);
    $db = new class implements IDBConnection {
        public array $events = [];
        public function beginTransaction(): void { $this->events[] = 'begin'; }
        public function commit(): void { $this->events[] = 'commit'; }
        public function rollBack(): void { $this->events[] = 'rollback'; }
    };
    $bookings = new BookingService();
    $bookings->booking = $booking;
    $guard = new SecretariatForeignBookingInterventionGuard();
    $audit = new BookingInterventionAuditRepository();
    $queue = new InterventionNotificationQueueRepository();
    $delivery = new InterventionNotificationDeliveryService();
    $logger = new class implements \Psr\Log\LoggerInterface {
        public array $warnings = [];
        public function warning(string $message, array $context = []): void { $this->warnings[] = [$message, $context]; }
    };
    $service = new SecretariatBookingInterventionService($db, $bookings, new RoomService(), $guard, $audit, $queue, $delivery, $logger);

    $result = $service->update(
        17,
        8,
        '2026-10-05T10:00',
        '2026-10-05T11:00',
        'Abstimmung',
        'Neuer vertraulicher Titel',
        'Raumkonflikt wurde organisatorisch abgestimmt.',
    );
    $assert($result === 17, 'Der Fremdeingriff gibt nicht die Buchungs-ID zurück.');
    $assert($db->events === ['begin', 'commit'], 'Mutation, Audit und Outbox wurden nicht gemeinsam transaktional bestätigt.');
    $assert(count($bookings->updates) === 1, 'Die freigegebene Fremdbuchung wurde nicht geändert.');
    $assert(count($audit->entries) === 1 && count($queue->entries) === 1, 'Audit oder persistente Benachrichtigungsqueue fehlt.');
    $auditEntry = $audit->entries[0];
    $assert($auditEntry['action'] === 'update' && $auditEntry['actorUid'] === 'secretariat-user', 'Audit enthält nicht Aktion und handelnde UID.');
    $assert($auditEntry['bookingId'] === 17 && $auditEntry['oldRoomId'] === 4 && $auditEntry['newRoomId'] === 8, 'Audit enthält nicht die erlaubten Objekt- und Raumdaten.');
    $assert($auditEntry['oldRoom'] === 'Raum 4' && $auditEntry['newRoom'] === 'Raum 8', 'Audit enthält nicht die zum Eingriff gültigen Raumnamen.');
    $assert($auditEntry['reason'] === 'Raumkonflikt wurde organisatorisch abgestimmt.', 'Audit enthält nicht die validierte Begründung.');
    $assert(!array_key_exists('title', $auditEntry) && !array_key_exists('purpose', $auditEntry) && !array_key_exists('ownerUid', $auditEntry), 'Audit enthält verbotene Buchungsinhalte.');
    $assert($queue->entries[0]['recipientUid'] === 'booking-owner', 'Queue ist nicht an die buchende Person adressiert.');
    $assert($queue->entries[0]['oldRoom'] === 'Raum 4' && $queue->entries[0]['newRoom'] === 'Raum 8', 'Queue enthält nicht die erforderlichen Raumnamen.');
    $assert(!array_key_exists('title', $queue->entries[0]) && !array_key_exists('purpose', $queue->entries[0]), 'Queue enthält verbotene Buchungsinhalte.');
    $assert($delivery->delivered === [41], 'Die Zustellung wurde nicht erst aus der persistenten Queue angestoßen.');

    $db->events = [];
    $audit->entries = [];
    $queue->entries = [];
    $delivery->delivered = [];
    $service->delete(17, 'Buchung blockiert die abgestimmte Raumnutzung.');
    $assert($bookings->deletes === [17], 'Freigegebene Fremdbuchung wurde nicht gelöscht.');
    $assert($audit->entries[0]['action'] === 'delete' && $audit->entries[0]['newRoomId'] === null, 'Lösch-Audit behauptet einen neuen Raum.');
    $assert($queue->entries[0]['newRoomId'] === null, 'Löschbenachrichtigung behauptet einen neuen Raum.');

    foreach (['zu kurz', str_repeat('x', 501)] as $invalidReason) {
        $db->events = [];
        $beforeUpdates = count($bookings->updates);
        try {
            $service->update(17, 8, '2026-10-05T10:00', '2026-10-05T11:00', 'Abstimmung', 'Titel', $invalidReason);
            throw new RuntimeException('Ungültige Begründung wurde akzeptiert.');
        } catch (InvalidArgumentException) {
        }
        $assert($db->events === [] && count($bookings->updates) === $beforeUpdates, 'Ungültige Begründung hatte eine Nebenwirkung.');
    }

    $guard->actorUid = null;
    $db->events = [];
    $beforeUpdates = count($bookings->updates);
    try {
        $service->update(17, 8, '2026-10-05T10:00', '2026-10-05T11:00', 'Abstimmung', 'Titel', 'Ausreichend lange Begründung.');
        throw new RuntimeException('Nicht freigegebener Fremdeingriff wurde akzeptiert.');
    } catch (DomainException) {
    }
    $assert($db->events === ['begin', 'rollback'] && count($bookings->updates) === $beforeUpdates, 'Deny hat Daten verändert oder die Transaktion nicht zurückgerollt.');

    $guard->actorUid = 'secretariat-user';
    $bookings->booking = null;
    $db->events = [];
    $beforeUpdates = count($bookings->updates);
    try {
        $service->update(999, 8, '2026-10-05T10:00', '2026-10-05T11:00', 'Abstimmung', 'Titel', 'Ausreichend lange Begründung.');
        throw new RuntimeException('Manipulierte fremde Buchungs-ID wurde akzeptiert.');
    } catch (OutOfBoundsException) {
    }
    $assert($db->events === ['begin', 'rollback'] && count($bookings->updates) === $beforeUpdates, 'Manipulierte Objekt-ID hatte eine Nebenwirkung.');
    $bookings->booking = $booking;

    $audit->throw = true;
    $db->events = [];
    try {
        $service->delete(17, 'Ausreichend lange Begründung.');
        throw new RuntimeException('Audit-Schreibfehler wurde verschluckt.');
    } catch (RuntimeException $error) {
        $assert($error->getMessage() === 'Der Sekretariatseingriff konnte nicht sicher gespeichert werden.', 'Unsichere technische Fehlerdetails wurden ausgegeben.');
    }
    $assert($db->events === ['begin', 'rollback'], 'Audit-Schreibfehler hat die Fachmutation nicht zurückgerollt.');
    $audit->throw = false;

    $queue->throw = true;
    $db->events = [];
    try {
        $service->delete(17, 'Ausreichend lange Begründung.');
        throw new RuntimeException('Outbox-Schreibfehler wurde verschluckt.');
    } catch (RuntimeException $error) {
        $assert($error->getMessage() === 'Der Sekretariatseingriff konnte nicht sicher gespeichert werden.', 'Outbox-Fehler wurde nicht sicher diagnostiziert.');
    }
    $assert($db->events === ['begin', 'rollback'], 'Outbox-Schreibfehler hat die Fachmutation nicht zurückgerollt.');
    $queue->throw = false;

    $delivery->throw = true;
    $db->events = [];
    $service->delete(17, 'Ausreichend lange Begründung.');
    $assert($db->events === ['begin', 'commit'], 'Zustellfehler hat eine bereits bestätigte Fachmutation zurückgerollt.');
    $assert(count($logger->warnings) >= 3, 'Schreib- oder Zustellfehler bleiben nicht datensparsam diagnostizierbar.');

    echo "Secretariat booking intervention service tests passed\n";
}
