<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use OCA\FlzRoom\Model\Room;
use OCA\FlzRoom\Repository\RoomRepository;

/** Zweck: Validiert Raumstammdaten und delegiert ihre Persistenz. */
final class RoomService {
    public function __construct(private RoomRepository $rooms) {}

    /** @return list<Room> */
    public function all(): array {
        return $this->rooms->findAll();
    }

    public function get(int $id): ?Room {
        return $this->rooms->find($id);
    }

    public function save(?int $id, string $name, string $description, int $sortOrder): int {
        $name = trim($name);
        $description = trim($description);
        if ($name === '' || $this->length($name) > 255 || $this->length($description) > 500) {
            throw new \InvalidArgumentException('Raumname oder Beschreibung ist ungültig.');
        }
        if ($id !== null && $this->rooms->find($id) === null) {
            throw new \OutOfBoundsException('Raum nicht gefunden.');
        }

        return $this->rooms->save(Room::get(compact('id', 'name', 'description', 'sortOrder')));
    }

    public function delete(int $id): void {
        if ($this->rooms->find($id) === null) throw new \OutOfBoundsException('Raum nicht gefunden.');
        $this->rooms->delete($id);
    }

    private function length(string $value): int {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
