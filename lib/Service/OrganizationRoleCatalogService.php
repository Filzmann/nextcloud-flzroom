<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use InvalidArgumentException;
use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshotService as FlzOrganizationSnapshotService;
use OCA\LocalBase\PublicApi\V1\OrganizationSnapshot;
use OCA\LocalBase\PublicApi\V1\OrganizationSnapshotService;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/**
 * Resolves semantic organization roles through the public organization V1 snapshot.
 * 
 * Priority:
 * 1. flz_organization (new canonical source for FLZ organization management)
 * 2. localbase (historical fallback for compatibility)
 */
class OrganizationRoleCatalogService {
    private const FLZ_ORGANIZATION_APP_ID = 'flz_organization';
    private const LOCALBASE_APP_ID = 'localbase';
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
        // 1. Try flz_organization first (new canonical source)
        $flzOrgResult = $this->tryFlzOrganization();
        if ($flzOrgResult['status'] === 'VALID') {
            return $flzOrgResult;
        }

        // 2. Fallback to localbase (historical compatibility)
        $localBaseResult = $this->tryLocalBase();
        if ($localBaseResult !== null) {
            return $localBaseResult;
        }

        // Both providers failed
        return $this->unavailable('MISSING', 'Kein Organisationsprovider verfügbar.');
    }

    /**
     * Try to get organization snapshot from flz_organization.
     *
     * @return array{status:string,message:?string,roles:list<array{roleKey:string,groupId:string,label:string}>}
     */
    private function tryFlzOrganization(): array {
        try {
            if (!in_array(self::FLZ_ORGANIZATION_APP_ID, $this->apps->getEnabledApps(), true)) {
                return $this->unavailable('MISSING', 'flz_organization ist nicht aktiviert.');
            }
        } catch (\Throwable $error) {
            return $this->providerFailure($error, self::FLZ_ORGANIZATION_APP_ID);
        }

        if (!$this->flzOrganizationContractAvailable()) {
            return $this->unavailable('INCOMPATIBLE', 'Der flz_organization Vertrag ist nicht kompatibel.');
        }

        try {
            $snapshot = $this->readFlzOrganizationSnapshot();
            if ($this->providerContractVersion($snapshot) !== self::SUPPORTED_CONTRACT_VERSION) {
                return $this->unavailable('INCOMPATIBLE', 'Der flz_organization Vertrag ist nicht kompatibel.');
            }
            if (!$snapshot->isValid()) {
                return $this->unavailable('INVALID', 'Die flz_organization Definition ist ungültig.');
            }
            $roles = [];
            foreach ($snapshot->roles() as $roleKey => $mapping) {
                $groupId = $mapping['groupId'];
                if ($groupId === 'flz-AS' || str_starts_with($groupId, 'flz-ASN-')) continue;
                if (!$this->groups->groupExists($groupId)) continue;
                $roles[] = ['roleKey' => $roleKey, 'groupId' => $groupId, 'label' => $mapping['label']];
            }
            return ['status' => 'VALID', 'message' => null, 'roles' => $roles];
        } catch (InvalidArgumentException|UnexpectedValueException) {
            return $this->unavailable('INCOMPATIBLE', 'Der flz_organization Vertrag ist nicht kompatibel.');
        } catch (\Throwable $error) {
            return $this->providerFailure($error, self::FLZ_ORGANIZATION_APP_ID);
        }
    }

    /**
     * Try to get organization snapshot from localbase (fallback).
     *
     * @return array{status:string,message:?string,roles:list<array{roleKey:string,groupId:string,label:string}>}|null
     */
    private function tryLocalBase(): ?array {
        try {
            if (!in_array(self::LOCALBASE_APP_ID, $this->apps->getEnabledApps(), true)) {
                return null;
            }
        } catch (\Throwable $error) {
            return null;
        }

        if (!$this->localBaseContractAvailable()) {
            return null;
        }

        try {
            $snapshot = $this->readLocalBaseSnapshot();
            if ($this->providerContractVersion($snapshot) !== self::SUPPORTED_CONTRACT_VERSION) {
                return null;
            }
            if (!$snapshot->isValid()) {
                return null;
            }
            $roles = [];
            foreach ($snapshot->roles() as $roleKey => $mapping) {
                $groupId = $mapping['groupId'];
                if ($groupId === 'flz-AS' || str_starts_with($groupId, 'flz-ASN-')) continue;
                if (!$this->groups->groupExists($groupId)) continue;
                $roles[] = ['roleKey' => $roleKey, 'groupId' => $groupId, 'label' => $mapping['label']];
            }
            return ['status' => 'VALID', 'message' => null, 'roles' => $roles];
        } catch (InvalidArgumentException|UnexpectedValueException) {
            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function flzOrganizationContractAvailable(): bool {
        return class_exists(FlzOrganizationSnapshotService::class);
    }

    private function localBaseContractAvailable(): bool {
        return class_exists(OrganizationSnapshot::class) && class_exists(OrganizationSnapshotService::class);
    }

    protected function readFlzOrganizationSnapshot(): OrganizationSnapshot {
        $provider = Server::get(FlzOrganizationSnapshotService::class);
        if (!$provider instanceof FlzOrganizationSnapshotService) {
            throw new UnexpectedValueException('flz_organization Organization V1 service is not resolvable.');
        }
        return $provider->snapshot();
    }

    protected function readLocalBaseSnapshot(): OrganizationSnapshot {
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
    private function providerFailure(\Throwable $error, string $providerId): array {
        $this->logger->warning('Raumplaner organization snapshot unavailable', [
            'app' => 'flzroom',
            'provider' => $providerId,
            'exceptionClass' => $error::class,
        ]);
        return $this->unavailable('UNAVAILABLE', "Der Organisationsprovider '$providerId' ist derzeit nicht verfügbar.");
    }
}
