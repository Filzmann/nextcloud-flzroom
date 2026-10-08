<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use OCA\FlzRoom\Exception\BookingConflictException;
use OCA\FlzRoom\Model\Booking;
use OCA\FlzRoom\Repository\BookingInterventionAuditRepository;
use OCA\FlzRoom\Repository\InterventionNotificationQueueRepository;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use Throwable;

/** Atomically applies an authorized foreign-booking change, its audit, and its notification outbox entry. */
final class SecretariatBookingInterventionService {
    private const MIN_REASON_LENGTH = 10;
    private const MAX_REASON_LENGTH = 500;

    public function __construct(
        private IDBConnection $db,
        private BookingService $bookings,
        private RoomService $rooms,
        private SecretariatForeignBookingInterventionGuard $guard,
        private BookingInterventionAuditRepository $audit,
        private InterventionNotificationQueueRepository $queue,
        private InterventionNotificationDeliveryService $delivery,
        private LoggerInterface $logger,
    ) {
    }

    public function update(
        int $bookingId,
        int $roomId,
        string $start,
        string $end,
        string $purpose,
        string $title,
        string $reason,
    ): int {
        $reason = $this->validatedReason($reason);
        return $this->execute('update', $bookingId, $reason, function (Booking $booking) use ($roomId, $start, $end, $purpose, $title): Booking {
            $this->bookings->update($booking, $roomId, $start, $end, $purpose, $title);
            return $this->bookings->existing((int)$booking->id());
        });
    }

    public function delete(int $bookingId, string $reason): void {
        $reason = $this->validatedReason($reason);
        $this->execute('delete', $bookingId, $reason, function (Booking $booking): ?Booking {
            $this->bookings->delete((int)$booking->id());
            return null;
        });
    }

    /** @param callable(Booking):?Booking $mutation */
    private function execute(string $action, int $bookingId, string $reason, callable $mutation): int {
        $queueId = null;
        $this->db->beginTransaction();
        try {
            // Reload inside the transaction so authorization and snapshots use the current persisted object.
            $before = $this->bookings->existing($bookingId);
            $actorUid = $this->guard->authorizedActorUid($before);
            if ($actorUid === null) throw new DomainException('Keine Berechtigung.');

            $after = $mutation($before);
            $occurredAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $oldRoom = $this->rooms->get($before->roomId())?->name() ?? 'Nicht mehr vorhandener Raum';
            $newRoom = $after === null ? null : ($this->rooms->get($after->roomId())?->name() ?? 'Nicht mehr vorhandener Raum');
            $snapshot = $this->snapshot($action, $actorUid, $before, $after, $oldRoom, $newRoom, $reason, $occurredAt);
            $this->audit->append($snapshot);
            $queueId = $this->queue->enqueue([
                'recipientUid' => $before->userUid(),
                'action' => $action,
                'bookingId' => $bookingId,
                'oldRoomId' => $snapshot['oldRoomId'],
                'newRoomId' => $snapshot['newRoomId'],
                'oldRoom' => $snapshot['oldRoom'],
                'newRoom' => $snapshot['newRoom'],
                'oldStartsAt' => $snapshot['oldStartsAt'],
                'oldEndsAt' => $snapshot['oldEndsAt'],
                'newStartsAt' => $snapshot['newStartsAt'],
                'newEndsAt' => $snapshot['newEndsAt'],
                'reason' => $reason,
                'createdAt' => $occurredAt,
            ]);
            $this->db->commit();
        } catch (DomainException|InvalidArgumentException|BookingConflictException|\OutOfBoundsException $error) {
            $this->db->rollBack();
            throw $error;
        } catch (Throwable $error) {
            $this->db->rollBack();
            $this->logger->warning('Sekretariatseingriff konnte nicht atomar gespeichert werden.', [
                'action' => $action,
                'exception' => $error,
            ]);
            throw new \RuntimeException('Der Sekretariatseingriff konnte nicht sicher gespeichert werden.', 0, $error);
        }

        try {
            $this->delivery->deliverById((int)$queueId);
        } catch (Throwable $error) {
            // The transaction is already committed. The persistent queue remains the retry source of truth.
            $this->logger->warning('Benachrichtigung zum Sekretariatseingriff wird später erneut versucht.', [
                'action' => $action,
                'exception' => $error,
            ]);
        }

        return $bookingId;
    }

    private function validatedReason(string $reason): string {
        $reason = trim($reason);
        $length = function_exists('mb_strlen') ? mb_strlen($reason) : strlen($reason);
        if ($length < self::MIN_REASON_LENGTH || $length > self::MAX_REASON_LENGTH) {
            throw new InvalidArgumentException('Die Begründung muss 10 bis 500 Zeichen enthalten.');
        }
        return $reason;
    }

    /** @return array<string, mixed> */
    private function snapshot(
        string $action,
        string $actorUid,
        Booking $before,
        ?Booking $after,
        string $oldRoom,
        ?string $newRoom,
        string $reason,
        DateTimeImmutable $occurredAt,
    ): array {
        return [
            'action' => $action,
            'actorUid' => $actorUid,
            'bookingId' => (int)$before->id(),
            'occurredAt' => $occurredAt,
            'oldRoomId' => $before->roomId(),
            'newRoomId' => $after?->roomId(),
            'oldRoom' => $oldRoom,
            'newRoom' => $newRoom,
            'oldStartsAt' => $before->startsAt(),
            'oldEndsAt' => $before->endsAt(),
            'newStartsAt' => $after?->startsAt(),
            'newEndsAt' => $after?->endsAt(),
            'reason' => $reason,
        ];
    }
}
