<?php

declare(strict_types=1);

namespace OCP\App {
    interface IAppManager {
        /** @return array<string,string> */
        public function getAppInstalledVersions(bool $onlyEnabled = false): array;
        /** @return list<string> */
        public function getEnabledApps(): array;
    }
}

namespace OCP {
    interface IGroupManager {
        public function groupExists(string $gid): bool;
    }

    if (!class_exists(Server::class)) {
        final class Server {
            public static mixed $service = null;
            public static function get(string $name): mixed { return self::$service; }
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

namespace {
    use OCA\FlzRoom\Service\OrganizationRoleCatalogService;
    use OCA\LocalBase\PublicApi\V1\OrganizationSnapshot;
    use OCP\App\IAppManager;
    use OCP\IGroupManager;
    use Psr\Log\LoggerInterface;

    final class RoomCatalogApps implements IAppManager {
        /** @param list<string> $installed @param list<string> $enabled */
        public function __construct(private array $installed, private array $enabled) {}
        public function getAppInstalledVersions(bool $onlyEnabled = false): array {
            return array_fill_keys($onlyEnabled ? $this->enabled : $this->installed, '1.0.0');
        }
        public function getEnabledApps(): array { return $this->enabled; }
    }

    final class RoomCatalogGroups implements IGroupManager {
        public bool $fail = false;
        /** @param list<string> $existing */
        public function __construct(private array $existing) {}
        public function groupExists(string $gid): bool {
            if ($this->fail) throw new RuntimeException('synthetic directory failure');
            return in_array($gid, $this->existing, true);
        }
    }

    final class RoomCatalogLogger implements LoggerInterface {
        /** @var list<array{message:mixed,context:array<mixed>}> */
        public array $warnings = [];
        public function emergency($message, array $context = []): void {}
        public function alert($message, array $context = []): void {}
        public function critical($message, array $context = []): void {}
        public function error($message, array $context = []): void {}
        public function warning($message, array $context = []): void { $this->warnings[] = ['message' => $message, 'context' => $context]; }
        public function notice($message, array $context = []): void {}
        public function info($message, array $context = []): void {}
        public function debug($message, array $context = []): void {}
        public function log($level, $message, array $context = []): void {}
    }

    final class RoomCatalogUnderTest extends OrganizationRoleCatalogService {
        public function __construct(
            IAppManager $apps,
            IGroupManager $groups,
            RoomCatalogLogger $logger,
            private bool $contractAvailable,
            private OrganizationSnapshot|Throwable|null $providerSnapshot,
            private string $contractVersion = OrganizationSnapshot::CONTRACT_VERSION,
        ) {
            parent::__construct($apps, $groups, $logger);
        }

        protected function providerContractAvailable(): bool { return $this->contractAvailable; }
        protected function readProviderSnapshot(): OrganizationSnapshot {
            if ($this->providerSnapshot instanceof Throwable) throw $this->providerSnapshot;
            if ($this->providerSnapshot === null) throw new UnexpectedValueException('synthetic incompatible provider');
            return $this->providerSnapshot;
        }
        protected function providerContractVersion(OrganizationSnapshot $snapshot): string { return $this->contractVersion; }
    }

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $provider = new OrganizationSnapshot(true, 4, [
        'gf_as' => ['groupId' => 'flz-GF-AS', 'label' => 'Geschäftsführung Assistenz'],
        'assistant_gf_digi' => ['groupId' => 'flz-AsdGF-Digi', 'label' => 'Assistenz der Geschäftsführung Digitalisierung'],
        'eb' => ['groupId' => 'flz-EB', 'label' => 'Einsatzbegleitung'],
        'office' => ['groupId' => 'flz-AS', 'label' => 'Manipulierte Assistenzzuordnung'],
        'secretariat' => ['groupId' => 'flz-Sekretariat', 'label' => 'Sekretariat'],
    ], [
        'west' => ['groupId' => 'flz-Bereich-West', 'label' => 'West'],
        'north' => ['groupId' => 'flz-ASN-Nord', 'label' => 'Manipulierte Assistenzteamzuordnung'],
    ]);
    $groups = new RoomCatalogGroups(['flz-GF-AS', 'flz-AsdGF-Digi', 'flz-EB', 'flz-AS', 'flz-Sekretariat', 'flz-Bereich-West', 'flz-ASN-Nord']);
    $logger = new RoomCatalogLogger();
    $service = new RoomCatalogUnderTest(new RoomCatalogApps(['localbase'], ['localbase']), $groups, $logger, true, $provider);
    $catalog = $service->catalog();
    $assert($catalog['status'] === 'VALID', 'Ein gültiger LocalBase-Snapshot bleibt nicht verwendbar.');
    $assert(array_column($catalog['roles'], 'groupId') === ['flz-GF-AS', 'flz-AsdGF-Digi', 'flz-EB', 'flz-Sekretariat'], 'Der Raumplaner bietet nicht genau die vorhandenen semantischen Organigrammrollen außer AS/ASN an.');
    $assert(array_column($catalog['roles'], 'roleKey') === ['gf_as', 'assistant_gf_digi', 'eb', 'secretariat'], 'Technische Gruppen werden nicht auf semantische Rollenschlüssel projiziert.');
    $assert($logger->warnings === [], 'Ein gültiger Snapshot erzeugt eine Warnung.');

    foreach ([
        [new RoomCatalogApps([], []), true, $provider, 'MISSING'],
        [new RoomCatalogApps(['localbase'], []), true, $provider, 'DISABLED'],
        [new RoomCatalogApps(['localbase'], ['localbase']), false, $provider, 'INCOMPATIBLE'],
        [new RoomCatalogApps(['localbase'], ['localbase']), true, new OrganizationSnapshot(false, 4, [], []), 'INVALID'],
    ] as [$apps, $available, $snapshot, $status]) {
        $result = (new RoomCatalogUnderTest($apps, new RoomCatalogGroups([]), new RoomCatalogLogger(), $available, $snapshot))->catalog();
        $assert($result['status'] === $status && $result['roles'] === [], "Status {$status} gibt Organigrammrollen frei.");
    }

    $groups->fail = true;
    $failed = $service->catalog();
    $assert($failed['status'] === 'UNAVAILABLE' && $failed['roles'] === [], 'Ein Verzeichnisfehler muss den gesamten Rollenkatalog sperren.');
    $assert(count($logger->warnings) === 1, 'Ein Verzeichnis- oder Providerfehler bleibt nicht diagnostizierbar.');

    echo "Filzmann Raumplaner organization group catalog tests passed\n";
}
