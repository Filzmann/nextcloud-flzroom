<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

final class ProviderDescriptor {
    /** @param list<string> $subjectTypes @param list<string> $capabilities */
    public function __construct(
        private string $appId,
        private string $displayName,
        private string $contractVersion,
        private array $subjectTypes,
        private array $capabilities,
        private int $maxPageSize,
    ) {
    }

    public function appId(): string { return $this->appId; }
    public function displayName(): string { return $this->displayName; }
    public function contractVersion(): string { return $this->contractVersion; }
    /** @return list<string> */ public function subjectTypes(): array { return $this->subjectTypes; }
    /** @return list<string> */ public function capabilities(): array { return $this->capabilities; }
    public function maxPageSize(): int { return $this->maxPageSize; }
}
