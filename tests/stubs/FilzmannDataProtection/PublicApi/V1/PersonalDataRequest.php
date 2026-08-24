<?php

declare(strict_types=1);

namespace OCA\FilzmannDataProtection\PublicApi\V1;

final class PersonalDataRequest {
    /** @param array<string, string> $providerCursors */
    public function __construct(
        private DataSubjectRef $subject,
        private string $language,
        private string $purpose,
        private int $pageLimit,
        private array $providerCursors,
    ) {
    }

    public function subject(): DataSubjectRef { return $this->subject; }
    public function language(): string { return $this->language; }
    public function purpose(): string { return $this->purpose; }
    public function pageLimit(): int { return $this->pageLimit; }
    public function cursor(): ?string { return $this->providerCursors['adroom'] ?? null; }
}
