<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Repository;

use DateTimeImmutable;
use DateTimeZone;
use OCA\FlzRoom\Model\Room;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Zweck: Kapselt alle gebundenen Zugriffe auf die Raumtabelle. */
final class RoomRepository {
    public function __construct(private IDBConnection $db) {}

    /** @return list<Room> */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb
            ->select('id', 'name', 'description', 'sort_order')
            ->from('flz_room_rooms')
            ->orderBy('sort_order', 'ASC')
            ->addOrderBy('name', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return Room::get_all(array_map([$this, 'mapRow'], $rows));
    }

    public function find(int $id): ?Room {
        $qb = $this->db->getQueryBuilder();
        $row = $qb
            ->select('id', 'name', 'description', 'sort_order')
            ->from('flz_room_rooms')
            ->where($qb->expr()->eq(
                'id',
                $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
            ))
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : Room::get($this->mapRow($row));
    }

    public function save(Room $room): int {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $values = [
            'name' => $room->name(),
            'description' => $room->description(),
            'sort_order' => $room->sortOrder(),
            'updated_at' => $now,
        ];
        $types = [
            'name' => IQueryBuilder::PARAM_STR,
            'description' => IQueryBuilder::PARAM_STR,
            'sort_order' => IQueryBuilder::PARAM_INT,
            'updated_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
        ];
        $qb = $this->db->getQueryBuilder();
        $insert = $room->id() === null;
        if ($insert) {
            $qb->insert('flz_room_rooms');
            $values['created_at'] = $now;
            $types['created_at'] = IQueryBuilder::PARAM_DATETIME_IMMUTABLE;
        } else {
            $qb
                ->update('flz_room_rooms')
                ->where($qb->expr()->eq(
                    'id',
                    $qb->createNamedParameter($room->id(), IQueryBuilder::PARAM_INT),
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

        return $room->id() ?? $qb->getLastInsertId();
    }

    public function delete(int $id): void {
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $qb
                ->delete('flz_room_bookings')
                ->where($qb->expr()->eq(
                    'room_id',
                    $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
                ))
                ->executeStatement();

            $qb = $this->db->getQueryBuilder();
            $qb
                ->delete('flz_room_rooms')
                ->where($qb->expr()->eq(
                    'id',
                    $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
                ))
                ->executeStatement();

            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    private function mapRow(array $row): array {
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'description' => (string)$row['description'],
            'sortOrder' => (int)$row['sort_order'],
        ];
    }
}
