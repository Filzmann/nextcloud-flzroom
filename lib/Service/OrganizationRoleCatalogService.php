<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use InvalidArgumentException;
use OCA\LocalBase\PublicApi\V1\OrganizationSnapshot;
use OCA\LocalBase\PublicApi\V1\OrganizationSnapshotService;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/** Resolves semantic organization roles through the public LocalBase V1 snapshot. */
class OrganizationRoleCatalogService {
    private const PROVIDER_APP_ID = 'localbase';
    private const SUPPORTED_CONTRACT_VERSION = '1.0';

    public function __construct(
        private IAppManager $apps,
        private IGroupManager $groups,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array{status:string,message:?string,roles:list<array{roleKey:string,groupId:string,label:string}>}
     */
    public function catalog(): array {
        try {
            if (!array_key_exists(self::PROVIDER_APP_ID, $this->apps->getAppInstalledVersions(false))) {
                return $this->unavailable('MISSING', 'Die Organisationsdefinition ist nicht installiert.');
            }
            if (!in_array(self::PROVIDER_APP_ID, $this->apps->getEnabledApps(), true)) {
                return $this->unavailable('DISABLED', 'Die Organisationsdefinition ist derzeit deaktiviert.');
            }
        } catch (\Throwable $error) {
            return $this->providerFailure($error);
        }

        if (!$this->providerContractAvailable()) {
            return $this->unavailable('INCOMPATIBLE', 'Die Organisationsschnittstelle ist nicht kompatibel.');
        }

        try {
            $snapshot = $this->readProviderSnapshot();
            if ($this->providerContractVersion($snapshot) !== self::SUPPORTED_CONTRACT_VERSION) {
                return $this->unavailable('INCOMPATIBLE', 'Die Organisationsschnittstelle ist nicht kompatibel.');
            }
            if (!$snapshot->isValid()) {
                return $this->unavailable('INVALID', 'Die Organisationsdefinition ist ungültig.');
            }
            $roles = [];
            foreach ($snapshot->roles() as $roleKey => $mapping) {
                $groupId = $mapping['groupId'];
                if ($groupId === 'flz-AS' || str_starts_with($groupId, 'flz-ASN-')) continue;
                if (!$this->groups->groupExists($groupId)) continue;
                $roles[] = ['roleKey' => $roleKey, 'groupId' => $groupId, 'label' => $mapping['label']];
            }
        } catch (InvalidArgumentException|UnexpectedValueException) {
            return $this->unavailable('INCOMPATIBLE', 'Die Organisationsschnittstelle ist nicht kompatibel.');
        } catch (\Throwable $error) {
            return $this->providerFailure($error);
        }

        return ['status' => 'VALID', 'message' => null, 'roles' => $roles];
    }

    protected function providerContractAvailable(): bool {
        return class_exists(OrganizationSnapshot::class) && class_exists(OrganizationSnapshotService::class);
    }

    protected function readProviderSnapshot(): OrganizationSnapshot {
        $provider = Server::get(OrganizationSnapshotService::class);
        if (!$provider instanceof OrganizationSnapshotService) {
            throw new UnexpectedValueException('LocalBase Organization V1 service is not resolvable.');
        }
        return $provider->snapshot();
    }

    protected function providerContractVersion(OrganizationSnapshot $snapshot): string {
        return $snapshot->contractVersion();
    }

    /** @return array{status:string,message:string,roles:array{}} */
    private function unavailable(string $status, string $message): array {
        return ['status' => $status, 'message' => $message, 'roles' => []];
    }

    /** @return array{status:string,message:string,roles:array{}} */
    private function providerFailure(\Throwable $error): array {
        $this->logger->warning('Raumplaner organization snapshot unavailable', [
            'app' => 'flzroom',
            'provider' => self::PROVIDER_APP_ID,
            'exceptionClass' => $error::class,
        ]);
        return $this->unavailable('UNAVAILABLE', 'Die Organisationsdefinition ist derzeit nicht verfügbar.');
    }
}
