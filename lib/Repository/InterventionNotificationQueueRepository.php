<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Repository;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Persistent outbox for data-minimized native Nextcloud intervention notifications. */
final class InterventionNotificationQueueRepository {
    public function __construct(private IDBConnection $db) {
    }

    /** @param array<string, mixed> $entry */
    public function enqueue(array $entry): int {
        $now = $entry['createdAt'];
        $qb = $this->db->getQueryBuilder();
        $qb->insert('flz_room_notify_queue');
        $values = [
            'recipient_uid' => [$entry['recipientUid'], IQueryBuilder::PARAM_STR],
            'action' => [$entry['action'], IQueryBuilder::PARAM_STR],
            'booking_id' => [$entry['bookingId'], IQueryBuilder::PARAM_INT],
            'old_room_id' => [$entry['oldRoomId'], IQueryBuilder::PARAM_INT],
            'new_room_id' => [$entry['newRoomId'], IQueryBuilder::PARAM_INT],
            'old_room_name' => [$entry['oldRoom'], IQueryBuilder::PARAM_STR],
            'new_room_name' => [$entry['newRoom'], IQueryBuilder::PARAM_STR],
            'old_starts_at' => [$entry['oldStartsAt'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'old_ends_at' => [$entry['oldEndsAt'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'new_starts_at' => [$entry['newStartsAt'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'new_ends_at' => [$entry['newEndsAt'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'reason' => [$entry['reason'], IQueryBuilder::PARAM_STR],
            'attempt_count' => [0, IQueryBuilder::PARAM_INT],
            // The post-commit path attempts delivery immediately; this future due time avoids a concurrent cron duplicate.
            'next_attempt_at' => [$now->add(new DateInterval('PT5M')), IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'state' => ['pending', IQueryBuilder::PARAM_STR],
            'created_at' => [$now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'updated_at' => [$now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
        ];
        foreach ($values as $field => [$value, $type]) {
            $qb->setValue($field, $qb->createNamedParameter($value, $value === null ? IQueryBuilder::PARAM_NULL : $type));
        }
        $qb->executeStatement();
        return $qb->getLastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array {
        $qb = $this->baseSelect();
        $row = $qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))->executeQuery()->fetchAssociative();
        return $row === false ? null : $this->mapRow($row);
    }

    /** @return list<array<string, mixed>> */
    public function due(DateTimeImmutable $now, int $limit): array {
        $qb = $this->baseSelect();
        $rows = $qb->where($qb->expr()->eq('state', $qb->createNamedParameter('pending', IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->lte('next_attempt_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->orderBy('next_attempt_at', 'ASC')->addOrderBy('id', 'ASC')->setMaxResults($limit)->executeQuery()->fetchAllAssociative();
        return array_map([$this, 'mapRow'], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function byRecipient(string $uid, int $limit): array {
        $qb = $this->baseSelect();
        $rows = $qb->where($qb->expr()->eq('recipient_uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)))
            ->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC')->setMaxResults($limit)->executeQuery()->fetchAllAssociative();
        return array_map([$this, 'mapRow'], $rows);
    }

    public function remove(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('flz_room_notify_queue')->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))->executeStatement();
    }

    public function scheduleRetry(int $id, int $attemptCount, DateTimeImmutable $nextAttemptAt, DateTimeImmutable $now, string $errorCode): void {
        $this->updateFailure($id, $attemptCount, 'pending', $nextAttemptAt, null, $now, $errorCode);
    }

    public function markPermanentlyFailed(int $id, int $attemptCount, DateTimeImmutable $failedAt, string $errorCode): void {
        $this->updateFailure($id, $attemptCount, 'failed', $failedAt, $failedAt, $failedAt, $errorCode);
    }

    /** @return list<array<string, mixed>> */
    public function permanentlyFailedBefore(DateTimeImmutable $cutoff, int $limit, int $offset = 0): array {
        $qb = $this->baseSelect();
        $rows = $qb->where($qb->expr()->eq('state', $qb->createNamedParameter('failed', IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->lt('failed_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->orderBy('failed_at', 'ASC')->addOrderBy('id', 'ASC')->setFirstResult($offset)->setMaxResults($limit)->executeQuery()->fetchAllAssociative();
        return array_map([$this, 'mapRow'], $rows);
    }

    public function deletePermanentlyFailedBefore(DateTimeImmutable $cutoff): int {
        $qb = $this->db->getQueryBuilder();
        return $qb->delete('flz_room_notify_queue')
            ->where($qb->expr()->eq('state', $qb->createNamedParameter('failed', IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->lt('failed_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->executeStatement();
    }

    private function updateFailure(int $id, int $attemptCount, string $state, DateTimeImmutable $nextAttemptAt, ?DateTimeImmutable $failedAt, DateTimeImmutable $updatedAt, string $errorCode): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('flz_room_notify_queue')
            ->set('attempt_count', $qb->createNamedParameter($attemptCount, IQueryBuilder::PARAM_INT))
            ->set('next_attempt_at', $qb->createNamedParameter($nextAttemptAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->set('state', $qb->createNamedParameter($state, IQueryBuilder::PARAM_STR))
            ->set('failed_at', $qb->createNamedParameter($failedAt, $failedAt === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->set('error_code', $qb->createNamedParameter($errorCode, IQueryBuilder::PARAM_STR))
            ->set('updated_at', $qb->createNamedParameter($updatedAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    private function baseSelect(): IQueryBuilder {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('id', 'recipient_uid', 'action', 'booking_id', 'old_room_id', 'new_room_id', 'old_room_name', 'new_room_name', 'old_starts_at', 'old_ends_at', 'new_starts_at', 'new_ends_at', 'reason', 'attempt_count', 'next_attempt_at', 'state', 'failed_at', 'error_code', 'created_at', 'updated_at')
            ->from('flz_room_notify_queue');
    }

    /** @return array<string, mixed> */
    private function mapRow(array $row): array {
        $utc = new DateTimeZone('UTC');
        $date = static fn(mixed $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable((string)$value, $utc);
        return [
            'id' => (int)$row['id'], 'recipientUid' => (string)$row['recipient_uid'], 'action' => (string)$row['action'], 'bookingId' => (int)$row['booking_id'],
            'oldRoomId' => (int)$row['old_room_id'], 'newRoomId' => $row['new_room_id'] === null ? null : (int)$row['new_room_id'],
            'oldRoom' => (string)$row['old_room_name'], 'newRoom' => $row['new_room_name'] === null ? null : (string)$row['new_room_name'],
            'oldStartsAt' => $date($row['old_starts_at']), 'oldEndsAt' => $date($row['old_ends_at']), 'newStartsAt' => $date($row['new_starts_at']), 'newEndsAt' => $date($row['new_ends_at']),
            'reason' => (string)$row['reason'], 'attemptCount' => (int)$row['attempt_count'], 'nextAttemptAt' => $date($row['next_attempt_at']), 'state' => (string)$row['state'],
            'failedAt' => $date($row['failed_at']), 'errorCode' => $row['error_code'] === null ? null : (string)$row['error_code'], 'createdAt' => $date($row['created_at']), 'updatedAt' => $date($row['updated_at']),
        ];
    }
}
