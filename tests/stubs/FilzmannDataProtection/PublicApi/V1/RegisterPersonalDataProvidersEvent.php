<?php

declare(strict_types=1);

namespace OCA\FilzmannDataProtection\PublicApi\V1;

use OCP\EventDispatcher\Event;

final class RegisterPersonalDataProvidersEvent extends Event {
    /** @var array<string, PersonalDataProvider> */
    private array $providers = [];

    public function register(PersonalDataProvider $provider): void {
        $this->providers[$provider->descriptor()->appId()] = $provider;
    }

    /** @return array<string, PersonalDataProvider> */
    public function providers(): array {
        return $this->providers;
    }
}
