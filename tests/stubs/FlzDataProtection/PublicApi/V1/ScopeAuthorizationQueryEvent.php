<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

use OCP\EventDispatcher\Event;

final class ScopeAuthorizationQueryEvent extends Event {
    public const CONTRACT_VERSION = '1.0';
    public const FLZROOM_SECRETARIAT_FOREIGN_BOOKING_INTERVENTION = 'flzroom.secretariat_foreign_booking_intervention';

    private string $status = 'unanswered';

    public function __construct(
        private string $consumerAppId,
        private string $scopeId,
        private string $requestedContractVersion,
    ) {
        parent::__construct();
    }

    public function consumerAppId(): string { return $this->consumerAppId; }
    public function scopeId(): string { return $this->scopeId; }
    public function requestedContractVersion(): string { return $this->requestedContractVersion; }
    public function status(): string { return $this->status; }
    public function isAuthorized(): bool { return $this->status === 'authorized'; }
    public function respond(bool $authorized, string $providerContractVersion): void {
        if ($this->status !== 'unanswered') return;
        $this->status = $providerContractVersion === $this->requestedContractVersion
            ? ($authorized ? 'authorized' : 'denied')
            : 'incompatible';
    }
}
