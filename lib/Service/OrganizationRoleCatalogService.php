<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use InvalidArgumentException;
use OCA\FlzOrganization\PublicApi\V1\Contract;
use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshot;
use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshotService;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/** Resolves room-role choices from the standalone public organization V1 snapshot. */
class OrganizationRoleCatalogService
{
    private const PROVIDER_APP_ID = 'flz_organization';
    private const SUPPORTED_CONTRACT_VERSION = '1.0';

    public function __construct(
        private IAppManager $apps,
        private IGroupManager $groups,
        private LoggerInterface $logger,
    )
    {
    }

    /** @return array{status:string,message:?string,roles:list<array{roleKey:string,groupId:string,label:string}>} */
    public function catalog(): array
    {
        try
        {
            if (!array_key_exists(self::PROVIDER_APP_ID, $this->apps->getAppInstalledVersions(false)))
            {
                return $this->unavailable('MISSING', 'Der Organisationsprovider ist nicht installiert.');
            }
            if (!in_array(self::PROVIDER_APP_ID, $this->apps->getEnabledApps(), true))
            {
                return $this->unavailable('DISABLED', 'Der Organisationsprovider ist nicht aktiviert.');
            }
        }
        catch (\Throwable $error)
        {
            return $this->providerFailure($error);
        }

        if (!$this->isContractCompatible())
        {
            return $this->unavailable('INCOMPATIBLE', 'Der Organisationsvertrag ist nicht kompatibel.');
        }

        try
        {
            $snapshot = $this->readProviderSnapshot();
            if ($snapshot->contractVersion() !== self::SUPPORTED_CONTRACT_VERSION)
            {
                return $this->unavailable('INCOMPATIBLE', 'Der Organisationsvertrag ist nicht kompatibel.');
            }
            if (!$snapshot->isValid())
            {
                return $this->unavailable('INVALID', 'Die Organisationsdefinition ist ungültig.');
            }

            $roles = [];
            foreach ($snapshot->roles() as $roleKey => $mapping)
            {
                if (!is_string($roleKey) || !is_array($mapping))
                {
                    throw new UnexpectedValueException('Ungültige Organisationsrollen-Zuordnung.');
                }
                $groupId = $mapping['groupId'] ?? null;
                $label = $mapping['label'] ?? null;
                if (!is_string($groupId) || !is_string($label))
                {
                    throw new UnexpectedValueException('Ungültige Organisationsrollen-Felder.');
                }
                if ($groupId === 'flz-AS' || str_starts_with($groupId, 'flz-ASN-'))
                {
                    continue;
                }
                if (!$this->groups->groupExists($groupId))
                {
                    continue;
                }
                $roles[] = ['roleKey' => $roleKey, 'groupId' => $groupId, 'label' => $label];
            }
            return ['status' => 'VALID', 'message' => null, 'roles' => $roles];
        }
        catch (InvalidArgumentException|UnexpectedValueException)
        {
            return $this->unavailable('INCOMPATIBLE', 'Der Organisationsvertrag ist nicht kompatibel.');
        }
        catch (\Throwable $error)
        {
            return $this->providerFailure($error);
        }
    }

    protected function isContractCompatible(): bool
    {
        return class_exists(Contract::class)
            && Contract::supports(self::SUPPORTED_CONTRACT_VERSION)
            && class_exists(OrganizationSnapshot::class)
            && class_exists(OrganizationSnapshotService::class);
    }

    protected function readProviderSnapshot(): OrganizationSnapshot
    {
        $provider = Server::get(OrganizationSnapshotService::class);
        if (!$provider instanceof OrganizationSnapshotService)
        {
            throw new UnexpectedValueException('Organization V1 service is not resolvable.');
        }
        return $provider->snapshot();
    }

    /** @return array{status:string,message:string,roles:array{}} */
    private function unavailable(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message, 'roles' => []];
    }

    /** @return array{status:string,message:string,roles:array{}} */
    private function providerFailure(\Throwable $error): array
    {
        $this->logger->warning(
            'Raumplaner organization snapshot unavailable',
            [
                'app' => 'flzroom',
                'provider' => self::PROVIDER_APP_ID,
                'exceptionClass' => $error::class,
            ],
        );
        return $this->unavailable('UNAVAILABLE', 'Der Organisationsprovider ist derzeit nicht verfügbar.');
    }
}
