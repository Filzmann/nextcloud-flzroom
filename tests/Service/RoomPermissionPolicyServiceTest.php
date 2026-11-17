<?php

declare(strict_types=1);

namespace OCP {
    interface IAppConfig {
        public function getValueString(string $appId, string $key, string $default = ''): string;
        public function setValueString(string $appId, string $key, string $value): bool;
    }
    interface IUser { public function getUID(): string; }
    interface IUserManager { public function get(string $uid): ?IUser; }
    interface IUserSession { public function getUser(): ?IUser; }
    interface IGroupManager {
        public function isInGroup(string $uid, string $gid): bool;
        public function getUserGroupIds(IUser $user): array;
    }
}

namespace Psr\Log {
    interface LoggerInterface { public function warning(string $message, array $context = []): void; }
}

namespace {
    use OCA\FlzRoom\Service\OrganizationRoleCatalogService;
    use OCA\FlzRoom\Service\RoomPermissionPolicyService;

    final class RoomPermissionCatalogFake extends OrganizationRoleCatalogService {
        public array $payload = ['status' => 'VALID', 'message' => null, 'roles' => []];
        public function __construct() {}
        public function catalog(): array { return $this->payload; }
    }

    $config = new class implements OCP\IAppConfig {
        public array $values = [];
        public function getValueString(string $appId, string $key, string $default = ''): string { return $this->values[$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): bool { $this->values[$key] = $value; return true; }
    };
    $users = new class implements OCP\IUserManager {
        public function get(string $uid): ?OCP\IUser {
            if ($uid === 'missing') return null;
            return new class($uid) implements OCP\IUser {
                public function __construct(private string $uid) {}
                public function getUID(): string { return $this->uid; }
            };
        }
    };
    $session = new class implements OCP\IUserSession {
        public string $uid = 'dpo';
        public function getUser(): ?OCP\IUser {
            return new class($this->uid) implements OCP\IUser {
                public function __construct(private string $uid) {}
                public function getUID(): string { return $this->uid; }
            };
        }
    };
    $groups = new class implements OCP\IGroupManager {
        public bool $fail = false;
        public array $memberships = [
            'dpo' => ['Datenschutzbeauftragte'],
            'pfk-user' => ['flz-PFK'],
            'assistant' => ['flz-PFK', 'flz-AS', 'flz-ASN-Nordost'],
            'secretariat-user' => ['flz-Sekretariat'],
            'bl-user' => ['flz-BL'],
        ];
        public function isInGroup(string $uid, string $gid): bool {
            if ($this->fail) throw new RuntimeException('synthetic group directory failure');
            return in_array($gid, $this->memberships[$uid] ?? [], true);
        }
        public function getUserGroupIds(OCP\IUser $user): array {
            if ($this->fail) throw new RuntimeException('synthetic group directory failure');
            return $this->memberships[$user->getUID()] ?? [];
        }
    };
    $logger = new class implements Psr\Log\LoggerInterface {
        public array $warnings = [];
        public function warning(string $message, array $context = []): void { $this->warnings[] = [$message, $context]; }
    };
    $catalog = new RoomPermissionCatalogFake();
    $catalog->payload['roles'] = [
        ['roleKey' => 'pfk', 'groupId' => 'flz-PFK', 'label' => 'Pflegefachkraft'],
        ['roleKey' => 'secretariat', 'groupId' => 'flz-Sekretariat', 'label' => 'Sekretariat'],
        ['roleKey' => 'bl', 'groupId' => 'flz-BL', 'label' => 'Büroleitung'],
        ['roleKey' => 'deputy_bl', 'groupId' => 'flz-StvBL', 'label' => 'Stellvertretende Büroleitung'],
        ['roleKey' => 'pdl', 'groupId' => 'flz-PDL', 'label' => 'Pflegedienstleitung'],
        ['roleKey' => 'deputy_pdl', 'groupId' => 'flz-StvPDL', 'label' => 'Stellvertretende Pflegedienstleitung'],
        ['roleKey' => 'gf_as', 'groupId' => 'flz-GF-AS', 'label' => 'Geschäftsführung Assistenz'],
        ['roleKey' => 'gf_digi', 'groupId' => 'flz-GF-Digi', 'label' => 'Geschäftsführung Digitales und Finanzen'],
    ];

    $policy = new RoomPermissionPolicyService($config, $groups, $users, $session, $catalog, $logger);

    foreach ([RoomPermissionPolicyService::ROOM_READ, RoomPermissionPolicyService::BOOKING_MANAGE_OWN, RoomPermissionPolicyService::BOOKING_TITLE_READ] as $permission) {
        if (!$policy->isGranted('pfk-user', $permission)) throw new RuntimeException("Organigrammrolle erhält Standardrecht {$permission} nicht.");
        if ($policy->isGranted('assistant', $permission)) throw new RuntimeException("AS/ASN erhält unzulässig {$permission}.");
    }
    if (!$policy->isGranted('secretariat-user', RoomPermissionPolicyService::BOOKING_INTERVENE_FOREIGN)
        || !$policy->isGranted('bl-user', RoomPermissionPolicyService::BOOKING_INTERVENE_FOREIGN)) {
        throw new RuntimeException('Bestätigte Standardrollen erhalten den Fremdeingriff nicht.');
    }
    if ($policy->isGranted('pfk-user', RoomPermissionPolicyService::BOOKING_INTERVENE_FOREIGN)) {
        throw new RuntimeException('Nicht zugewiesene Rolle erhält den Fremdeingriff.');
    }
    if (!$policy->isGranted('secretariat-user', RoomPermissionPolicyService::ROOM_MANAGE)
        || $policy->isGranted('bl-user', RoomPermissionPolicyService::ROOM_MANAGE)) {
        throw new RuntimeException('Raumverwaltungsstandard ist nicht auf Sekretariat begrenzt.');
    }
    if (!$policy->canConfigure()) throw new RuntimeException('Datenschutz kann die Rechtezuordnung nicht verwalten.');

    $saved = $policy->save([
        RoomPermissionPolicyService::ROOM_READ => ['pfk'],
        RoomPermissionPolicyService::BOOKING_MANAGE_OWN => ['pfk'],
        RoomPermissionPolicyService::BOOKING_TITLE_READ => ['pfk'],
        RoomPermissionPolicyService::BOOKING_INTERVENE_FOREIGN => ['pfk'],
        RoomPermissionPolicyService::ROOM_MANAGE => ['pfk'],
    ]);
    if ($saved[RoomPermissionPolicyService::ROOM_MANAGE] !== ['pfk']
        || !$policy->isGranted('pfk-user', RoomPermissionPolicyService::ROOM_MANAGE)
        || $policy->isGranted('secretariat-user', RoomPermissionPolicyService::ROOM_MANAGE)) {
        throw new RuntimeException('Gespeicherte semantische Rollenrechte werden nicht wirksam.');
    }

    $before = $config->values;
    try {
        $policy->save([
            RoomPermissionPolicyService::ROOM_READ => ['invented-group'],
            RoomPermissionPolicyService::BOOKING_MANAGE_OWN => [],
            RoomPermissionPolicyService::BOOKING_TITLE_READ => [],
            RoomPermissionPolicyService::BOOKING_INTERVENE_FOREIGN => [],
            RoomPermissionPolicyService::ROOM_MANAGE => [],
        ]);
        throw new RuntimeException('Manipulierter Rollenschlüssel wurde gespeichert.');
    } catch (InvalidArgumentException) {
    }
    if ($config->values !== $before) throw new RuntimeException('Abgelehntes Mapping hat AppConfig verändert.');

    $config->values['permission_role_assignments_v1'] = '{broken';
    if ($policy->isGranted('pfk-user', RoomPermissionPolicyService::ROOM_READ)) throw new RuntimeException('Beschädigte Konfiguration wirkt nicht fail-closed.');
    if ($logger->warnings === []) throw new RuntimeException('Beschädigte Konfiguration bleibt nicht diagnostizierbar.');

    $catalog->payload = ['status' => 'UNAVAILABLE', 'message' => 'synthetic', 'roles' => []];
    if ($policy->isGranted('pfk-user', RoomPermissionPolicyService::ROOM_READ)) throw new RuntimeException('Fehlender Snapshot wirkt nicht fail-closed.');

    $catalog->payload['status'] = 'VALID';
    $groups->fail = true;
    $warningCount = count($logger->warnings);
    if ($policy->isGranted('pfk-user', RoomPermissionPolicyService::ROOM_READ)) throw new RuntimeException('Verzeichnisfehler gibt ein Fachrecht frei.');
    if ($policy->canConfigure()) throw new RuntimeException('Verzeichnisfehler gibt die Rechteverwaltung frei.');
    if (count($logger->warnings) !== $warningCount + 2) throw new RuntimeException('Verzeichnisfehler der Rechteprüfung bleibt nicht diagnostizierbar.');
    foreach (array_slice($logger->warnings, -2) as [, $context]) {
        if (($context['exceptionClass'] ?? null) !== RuntimeException::class || isset($context['uid'])) {
            throw new RuntimeException('Diagnose eines Verzeichnisfehlers ist nicht minimal und datensparsam.');
        }
    }

    echo "Filzmann Raumplaner permission policy tests passed\n";
}
