<?php

declare(strict_types=1);

namespace OCA\AdRoom\Repository;

use DateTimeImmutable;
use DateTimeZone;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Append-only persistence for the data-minimized foreign-booking intervention audit. */
final class BookingInterventionAuditRepository {
    public function __construct(private IDBConnection $db) {
    }

    /** @param array<string, mixed> $entry */
    public function append(array $entry): int {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('adr_booking_audit');
        $values = [
            'action' => [$entry['action'], IQueryBuilder::PARAM_STR],
            'actor_uid' => [$entry['actorUid'], IQueryBuilder::PARAM_STR],
            'booking_id' => [$entry['bookingId'], IQueryBuilder::PARAM_INT],
            'occurred_at' => [$entry['occurredAt'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'old_room_id' => [$entry['oldRoomId'], IQueryBuilder::PARAM_INT],
            'new_room_id' => [$entry['newRoomId'], IQueryBuilder::PARAM_INT],
            'old_room_name' => [$entry['oldRoom'], IQueryBuilder::PARAM_STR],
            'new_room_name' => [$entry['newRoom'], IQueryBuilder::PARAM_STR],
            'old_starts_at' => [$entry['oldStartsAt'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'old_ends_at' => [$entry['oldEndsAt'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'new_starts_at' => [$entry['newStartsAt'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'new_ends_at' => [$entry['newEndsAt'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'reason' => [$entry['reason'], IQueryBuilder::PARAM_STR],
        ];
        foreach ($values as $field => [$value, $type]) {
            $qb->setValue($field, $qb->createNamedParameter($value, $value === null ? IQueryBuilder::PARAM_NULL : $type));
        }
        $qb->executeStatement();
        return $qb->getLastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit, int $offset = 0): array {
        $qb = $this->baseSelect();
        $rows = $qb->orderBy('occurred_at', 'DESC')->addOrderBy('id', 'DESC')->setFirstResult($offset)->setMaxResults($limit)->executeQuery()->fetchAllAssociative();
        return array_map([$this, 'mapRow'], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function byActor(string $uid, int $limit): array {
        $qb = $this->baseSelect();
        $rows = $qb->where($qb->expr()->eq('actor_uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)))
            ->orderBy('occurred_at', 'DESC')->setMaxResults($limit)->executeQuery()->fetchAllAssociative();
        return array_map([$this, 'mapRow'], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function olderThan(DateTimeImmutable $cutoff, int $limit, int $offset = 0): array {
        $qb = $this->baseSelect();
        $rows = $qb->where($qb->expr()->lt('occurred_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->orderBy('occurred_at', 'ASC')->addOrderBy('id', 'ASC')->setFirstResult($offset)->setMaxResults($limit)->executeQuery()->fetchAllAssociative();
        return array_map([$this, 'mapRow'], $rows);
    }

    public function deleteOlderThan(DateTimeImmutable $cutoff): int {
        $qb = $this->db->getQueryBuilder();
        return $qb->delete('adr_booking_audit')
            ->where($qb->expr()->lt('occurred_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->executeStatement();
    }

    private function baseSelect(): IQueryBuilder {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('id', 'action', 'actor_uid', 'booking_id', 'occurred_at', 'old_room_id', 'new_room_id', 'old_room_name', 'new_room_name', 'old_starts_at', 'old_ends_at', 'new_starts_at', 'new_ends_at', 'reason')
            ->from('adr_booking_audit');
    }

    /** @return array<string, mixed> */
    private function mapRow(array $row): array {
        $utc = new DateTimeZone('UTC');
        return [
            'id' => (int)$row['id'],
            'action' => (string)$row['action'],
            'actorUid' => (string)$row['actor_uid'],
            'bookingId' => (int)$row['booking_id'],
            'occurredAt' => new DateTimeImmutable((string)$row['occurred_at'], $utc),
            'oldRoomId' => (int)$row['old_room_id'],
            'newRoomId' => $row['new_room_id'] === null ? null : (int)$row['new_room_id'],
            'oldRoom' => (string)$row['old_room_name'],
            'newRoom' => $row['new_room_name'] === null ? null : (string)$row['new_room_name'],
            'oldStartsAt' => new DateTimeImmutable((string)$row['old_starts_at'], $utc),
            'oldEndsAt' => new DateTimeImmutable((string)$row['old_ends_at'], $utc),
            'newStartsAt' => $row['new_starts_at'] === null ? null : new DateTimeImmutable((string)$row['new_starts_at'], $utc),
            'newEndsAt' => $row['new_ends_at'] === null ? null : new DateTimeImmutable((string)$row['new_ends_at'], $utc),
            'reason' => (string)$row['reason'],
        ];
    }
}
