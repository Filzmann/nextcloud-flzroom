<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Model;

use DateTimeImmutable;

final class Booking {
    private function __construct(
        private ?int $id,
        private int $roomId,
        private string $userUid,
        private string $userName,
        private string $purpose,
        private string $title,
        private DateTimeImmutable $startsAt,
        private DateTimeImmutable $endsAt,
    ) {}

    public static function get(?array $data): ?self {
        if ($data === null) return null;
        return new self(
            isset($data['id']) ? (int)$data['id'] : null,
            (int)($data['roomId'] ?? 0),
            (string)($data['userUid'] ?? ''),
            (string)($data['userName'] ?? $data['userUid'] ?? ''),
            trim((string)($data['purpose'] ?? '')),
            trim((string)($data['title'] ?? '')),
            $data['startsAt'] instanceof DateTimeImmutable ? $data['startsAt'] : new DateTimeImmutable((string)$data['startsAt']),
            $data['endsAt'] instanceof DateTimeImmutable ? $data['endsAt'] : new DateTimeImmutable((string)$data['endsAt']),
        );
    }

    /** @return list<self> */
    public static function get_all(array $items): array { return array_values(array_filter(array_map([self::class, 'get'], $items))); }
    public function id(): ?int { return $this->id; }
    public function roomId(): int { return $this->roomId; }
    public function userUid(): string { return $this->userUid; }
    public function purpose(): string { return $this->purpose; }
    public function title(): string { return $this->title; }
    public function startsAt(): DateTimeImmutable { return $this->startsAt; }
    public function endsAt(): DateTimeImmutable { return $this->endsAt; }
    public function toArray(): array { return ['id'=>$this->id,'roomId'=>$this->roomId,'userUid'=>$this->userUid,'userName'=>$this->userName,'purpose'=>$this->purpose,'title'=>$this->title,'startsAt'=>$this->startsAt->format(DATE_ATOM),'endsAt'=>$this->endsAt->format(DATE_ATOM)]; }
    public function save(): never { throw new \LogicException('Booking wird ausschliesslich ueber das Repository gespeichert.'); }
}
