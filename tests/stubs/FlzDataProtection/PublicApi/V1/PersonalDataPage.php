<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

final class PersonalDataPage {
    /** @param list<PersonalDataEntry> $entries @param list<string> $restrictions */
    public function __construct(
        private string $status,
        private array $entries = [],
        private array $restrictions = [],
        private ?string $nextCursor = null,
    ) {
    }

    public function status(): string { return $this->status; }
    /** @return list<PersonalDataEntry> */ public function entries(): array { return $this->entries; }
    /** @return list<string> */ public function restrictions(): array { return $this->restrictions; }
    public function nextCursor(): ?string { return $this->nextCursor; }
}
