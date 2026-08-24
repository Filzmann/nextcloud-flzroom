<?php

declare(strict_types=1);

namespace OCA\AdRoom\Repository {
    use OCA\AdRoom\Model\Room;

    class RoomRepository {
        /** @var list<Room> */ public array $rooms = [];
        public ?Room $saved = null;
        public ?int $deleted = null;
        public function findAll(): array { return $this->rooms; }
        public function find(int $id): ?Room { foreach ($this->rooms as $room) if ($room->id() === $id) return $room; return null; }
        public function save(Room $room): int { $this->saved = $room; return $room->id() ?? 8; }
        public function delete(int $id): void { $this->deleted = $id; }
    }
}

namespace {
    use OCA\AdRoom\Model\Room;
    use OCA\AdRoom\Repository\RoomRepository;
    use OCA\AdRoom\Service\RoomService;

    $repository = new RoomRepository();
    $repository->rooms = [Room::get(['id' => 4, 'name' => 'Nord', 'description' => 'Test', 'sortOrder' => 2])];
    $service = new RoomService($repository);
    if (count($service->all()) !== 1 || $service->get(4)?->name() !== 'Nord') throw new RuntimeException('Raumliste oder Einzelsuche ist fehlerhaft.');
    if ($service->save(4, ' Nord neu ', ' Beschreibung ', 3) !== 4 || $repository->saved?->name() !== 'Nord neu') throw new RuntimeException('Raumänderung wird nicht normalisiert gespeichert.');
    if ($service->save(null, 'Süd', '', 1) !== 8) throw new RuntimeException('Neuer Raum wird nicht gespeichert.');
    foreach ([['', ''], [str_repeat('x', 256), ''], ['Raum', str_repeat('x', 501)]] as [$name, $description]) {
        try { $service->save(null, $name, $description, 0); throw new RuntimeException('Ungültige Raumdaten wurden akzeptiert.'); } catch (InvalidArgumentException) {}
    }
    try { $service->save(404, 'Fehlt', '', 0); throw new RuntimeException('Fehlender Raum wurde geändert.'); } catch (OutOfBoundsException) {}
    $service->delete(4);
    if ($repository->deleted !== 4) throw new RuntimeException('Raum wurde nicht gelöscht.');
    try { $service->delete(404); throw new RuntimeException('Fehlender Raum wurde gelöscht.'); } catch (OutOfBoundsException) {}

    echo "AD Raumplaner room service tests passed\n";
}
