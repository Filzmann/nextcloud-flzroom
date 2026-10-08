<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Model;

final class Room {
    private function __construct(
        private ?int $id,
        private string $name,
        private string $description,
        private int $sortOrder,
    ) {}

    public static function get(?array $data): ?self {
        if ($data === null) return null;
        return new self(isset($data['id']) ? (int)$data['id'] : null, trim((string)($data['name'] ?? '')), trim((string)($data['description'] ?? '')), (int)($data['sortOrder'] ?? 0));
    }

    /** @return list<self> */
    public static function get_all(array $items): array {
        return array_values(array_filter(array_map([self::class, 'get'], $items)));
    }

    public function id(): ?int { return $this->id; }
    public function name(): string { return $this->name; }
    public function description(): string { return $this->description; }
    public function sortOrder(): int { return $this->sortOrder; }
    public function toArray(): array { return ['id' => $this->id, 'name' => $this->name, 'description' => $this->description, 'sortOrder' => $this->sortOrder]; }
    public function save(): never { throw new \LogicException('Room wird ausschliesslich ueber das Repository gespeichert.'); }
}

