<?php

declare(strict_types=1);

namespace OCP\App {
    interface IAppManager {
        public function getAppInstalledVersions(bool $onlyEnabled = false): array;
        public function getEnabledApps(): array;
    }
}

namespace OCP {
    interface IGroupManager { public function groupExists(string $gid): bool; }
    final class Server
    {
        public static mixed $service = null;

        public static function get(string $name): mixed
        {
            return self::$service;
        }
    }
}

namespace Psr\Log {
    interface LoggerInterface {
        public function emergency($message, array $context = []): void;
        public function alert($message, array $context = []): void;
        public function critical($message, array $context = []): void;
        public function error($message, array $context = []): void;
        public function warning($message, array $context = []): void;
        public function notice($message, array $context = []): void;
        public function info($message, array $context = []): void;
        public function debug($message, array $context = []): void;
        public function log($level, $message, array $context = []): void;
    }
}

namespace OCA\FlzOrganization\PublicApi\V1 {
    final class Contract {
        public static function supports(string $version): bool { return $version === '1.0'; }
    }
    final class OrganizationSnapshot {
        public function __construct(private bool $valid, private int $version, private array $roles, private array $areas) {}
        public function contractVersion(): string { return '1.0'; }
        public function isValid(): bool { return $this->valid; }
        public function roles(): array { return $this->valid ? $this->roles : []; }
    }
    final class OrganizationSnapshotService
    {
        public function __construct(private OrganizationSnapshot $snapshot)
        {
        }

        public function snapshot(): OrganizationSnapshot
        {
            return $this->snapshot;
        }
    }
}

namespace {
    use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshot;
    use OCA\FlzRoom\Service\OrganizationRoleCatalogService;
    use OCP\App\IAppManager;
    use OCP\IGroupManager;
    use Psr\Log\LoggerInterface;

    final class RoomCatalogApps implements IAppManager {
        public function __construct(private array $installed, private array $enabled) {}
        public function getAppInstalledVersions(bool $onlyEnabled = false): array {
            return array_fill_keys($onlyEnabled ? $this->enabled : $this->installed, '1.0.0');
        }
        public function getEnabledApps(): array { return $this->enabled; }
    }

    final class RoomCatalogGroups implements IGroupManager {
        public bool $fail = false;
        public function __construct(private array $existing) {}
        public function groupExists(string $gid): bool {
            if ($this->fail) throw new RuntimeException('synthetic directory failure');
            return in_array($gid, $this->existing, true);
        }
    }

    final class RoomCatalogLogger implements LoggerInterface {
        public array $warnings = [];
        public function emergency($message, array $context = []): void {}
        public function alert($message, array $context = []): void {}
        public function critical($message, array $context = []): void {}
        public function error($message, array $context = []): void {}
        public function warning($message, array $context = []): void { $this->warnings[] = [$message, $context]; }
        public function notice($message, array $context = []): void {}
        public function info($message, array $context = []): void {}
        public function debug($message, array $context = []): void {}
        public function log($level, $message, array $context = []): void {}
    }

    final class RoomCatalogUnderTest extends OrganizationRoleCatalogService {
        public bool $compatible = true;
        public OrganizationSnapshot|Throwable|null $provider = null;
        protected function isContractCompatible(): bool { return $this->compatible; }
        protected function readProviderSnapshot(): OrganizationSnapshot {
            if ($this->provider instanceof Throwable) throw $this->provider;
            if (!$this->provider instanceof OrganizationSnapshot) throw new UnexpectedValueException('missing provider');
            return $this->provider;
        }
    }

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $provider = new OrganizationSnapshot(true, 5, [
        'gf_as' => ['groupId' => 'flz-GF-AS', 'label' => 'Geschäftsführung Assistenz'],
        'assistant_gf_digi' => ['groupId' => 'flz-AsdGF-Digi', 'label' => 'Assistenz der Geschäftsführung Digitalisierung'],
        'eb' => ['groupId' => 'flz-EB', 'label' => 'Einsatzbegleitung'],
        'office' => ['groupId' => 'flz-AS', 'label' => 'Manipulierte Assistenzzuordnung'],
        'secretariat' => ['groupId' => 'flz-Sekretariat', 'label' => 'Sekretariat'],
    ], []);
    $groups = new RoomCatalogGroups(['flz-GF-AS', 'flz-AsdGF-Digi', 'flz-EB', 'flz-AS', 'flz-Sekretariat']);
    $logger = new RoomCatalogLogger();
    \OCP\Server::$service = new \OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshotService($provider);
    $service = new OrganizationRoleCatalogService(
        new RoomCatalogApps(['flz_organization'], ['flz_organization']),
        $groups,
        $logger,
    );
    $catalog = $service->catalog();
    $assert($catalog['status'] === 'VALID', 'Ein gültiger flz_organization-Snapshot bleibt nicht verwendbar.');
    $assert(array_column($catalog['roles'], 'groupId') === ['flz-GF-AS', 'flz-AsdGF-Digi', 'flz-EB', 'flz-Sekretariat'], 'AS-/ASN-Gruppen dürfen nicht als Raumrollen erscheinen.');
    $assert($logger->warnings === [], 'Ein gültiger Snapshot erzeugt eine Warnung.');

    $localBaseOnly = new RoomCatalogUnderTest(new RoomCatalogApps(['localbase'], ['localbase']), new RoomCatalogGroups([]), new RoomCatalogLogger());
    $localBaseOnly->provider = $provider;
    $assert($localBaseOnly->catalog()['status'] === 'MISSING', 'LocalBase darf den fehlenden Standalone-Provider nicht ersetzen.');

    foreach ([
        [new RoomCatalogApps([], []), true, $provider, 'MISSING'],
        [new RoomCatalogApps(['flz_organization'], []), true, $provider, 'DISABLED'],
        [new RoomCatalogApps(['flz_organization'], ['flz_organization']), false, $provider, 'INCOMPATIBLE'],
        [new RoomCatalogApps(['flz_organization'], ['flz_organization']), true, new OrganizationSnapshot(false, 5, [], []), 'INVALID'],
    ] as [$apps, $compatible, $snapshot, $status]) {
        $consumer = new RoomCatalogUnderTest($apps, new RoomCatalogGroups([]), new RoomCatalogLogger());
        $consumer->compatible = $compatible;
        $consumer->provider = $snapshot;
        $result = $consumer->catalog();
        $assert($result['status'] === $status && $result['roles'] === [], "Status {$status} gibt Organigrammrollen frei.");
    }

    $groups->fail = true;
    $failed = $service->catalog();
    $assert($failed['status'] === 'UNAVAILABLE' && $failed['roles'] === [], 'Ein Verzeichnisfehler muss den gesamten Rollenkatalog sperren.');
    $assert(count($logger->warnings) === 1, 'Ein Verzeichnis- oder Providerfehler bleibt nicht diagnostizierbar.');

    echo "Filzmann Raumplaner organization group catalog tests passed\n";
}
