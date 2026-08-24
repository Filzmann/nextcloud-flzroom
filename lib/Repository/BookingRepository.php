<?php

declare(strict_types=1);

namespace OCA\AdRoom\Repository;

use DateTimeImmutable;
use DateTimeZone;
use OCA\AdRoom\Model\Booking;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Zweck: Kapselt Buchungslisten, Persistenz und die atomare Überschneidungsabfrage.
 * Zusammenspiel: BookingService validiert fachlich; dieses Repository bindet Werte und hydriert Booking-Modelle.
 */
final class BookingRepository {
    public function __construct(private IDBConnection $db) {}

    /** @return list<Booking> */
    public function findRange(DateTimeImmutable $start, DateTimeImmutable $end): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb
            ->select('id', 'room_id', 'user_uid', 'purpose', 'title', 'starts_at', 'ends_at')
            ->from('adr_bookings')
            ->where($qb->expr()->lt(
                'starts_at',
                $qb->createNamedParameter($end, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
            ))
            ->andWhere($qb->expr()->gt(
                'ends_at',
                $qb->createNamedParameter($start, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
            ))
            ->orderBy('starts_at', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return Booking::get_all(array_map([$this, 'mapRow'], $rows));
    }

    public function find(int $id): ?Booking {
        $qb = $this->db->getQueryBuilder();
        $row = $qb
            ->select('id', 'room_id', 'user_uid', 'purpose', 'title', 'starts_at', 'ends_at')
            ->from('adr_bookings')
            ->where($qb->expr()->eq(
                'id',
                $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
            ))
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : Booking::get($this->mapRow($row));
    }

    /** @return list<Booking> */
    public function findByUserUid(string $uid, int $limit): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb
            ->select('id', 'room_id', 'user_uid', 'purpose', 'title', 'starts_at', 'ends_at')
            ->from('adr_bookings')
            ->where($qb->expr()->eq(
                'user_uid',
                $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR),
            ))
            ->orderBy('starts_at', 'ASC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return Booking::get_all(array_map([$this, 'mapRow'], $rows));
    }

    /** @return list<Booking> */
    public function findEndedByUserUid(string $uid, DateTimeImmutable $cutoff, int $limit): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb
            ->select('id', 'room_id', 'user_uid', 'purpose', 'title', 'starts_at', 'ends_at')
            ->from('adr_bookings')
            ->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->lte('ends_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->orderBy('ends_at', 'ASC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return Booking::get_all(array_map([$this, 'mapRow'], $rows));
    }

    public function overlaps(int $roomId, DateTimeImmutable $start, DateTimeImmutable $end, ?int $excludeId = null): bool {
        $qb = $this->db->getQueryBuilder();
        $qb
            ->select('id')
            ->from('adr_bookings')
            ->where($qb->expr()->eq(
                'room_id',
                $qb->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
            ))
            ->andWhere($qb->expr()->lt(
                'starts_at',
                $qb->createNamedParameter($end, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
            ))
            ->andWhere($qb->expr()->gt(
                'ends_at',
                $qb->createNamedParameter($start, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
            ))
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $qb->andWhere($qb->expr()->neq(
                'id',
                $qb->createNamedParameter($excludeId, IQueryBuilder::PARAM_INT),
            ));
        }

        return $qb->executeQuery()->fetchOne() !== false;
    }

    public function save(Booking $booking): int {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $values = [
            'room_id' => $booking->roomId(),
            'user_uid' => $booking->userUid(),
            'purpose' => $booking->purpose(),
            'title' => $booking->title(),
            'starts_at' => $booking->startsAt(),
            'ends_at' => $booking->endsAt(),
            'updated_at' => $now,
        ];
        $types = [
            'room_id' => IQueryBuilder::PARAM_INT,
            'user_uid' => IQueryBuilder::PARAM_STR,
            'purpose' => IQueryBuilder::PARAM_STR,
            'title' => IQueryBuilder::PARAM_STR,
            'starts_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
            'ends_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
            'updated_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
        ];
        $qb = $this->db->getQueryBuilder();
        $insert = $booking->id() === null;
        if ($insert) {
            $qb->insert('adr_bookings');
            $values['created_at'] = $now;
            $types['created_at'] = IQueryBuilder::PARAM_DATETIME_IMMUTABLE;
        } else {
            $qb
                ->update('adr_bookings')
                ->where($qb->expr()->eq(
                    'id',
                    $qb->createNamedParameter($booking->id(), IQueryBuilder::PARAM_INT),
                ));
        }
        foreach ($values as $field => $value) {
            $parameter = $qb->createNamedParameter($value, $types[$field]);
            if ($insert) {
                $qb->setValue($field, $parameter);
            } else {
                $qb->set($field, $parameter);
            }
        }
        $qb->executeStatement();

        return $booking->id() ?? $qb->getLastInsertId();
    }

    public function delete(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $qb
            ->delete('adr_bookings')
            ->where($qb->expr()->eq(
                'id',
                $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
            ))
            ->executeStatement();
    }

    private function mapRow(array $row): array {
        $utc = new DateTimeZone('UTC');

        return [
            'id' => (int)$row['id'],
            'roomId' => (int)$row['room_id'],
            'userUid' => (string)$row['user_uid'],
            'purpose' => (string)$row['purpose'],
            'title' => (string)$row['title'],
            'startsAt' => new DateTimeImmutable((string)$row['starts_at'], $utc),
            'endsAt' => new DateTimeImmutable((string)$row['ends_at'], $utc),
        ];
    }
}
