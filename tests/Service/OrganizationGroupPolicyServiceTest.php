<?php

declare(strict_types=1);

namespace OCP {
    interface IAppConfig {
        public function getValueString(string $appId, string $key, string $default = ''): string;
        public function setValueString(string $appId, string $key, string $value): void;
    }
    interface IGroupManager {
        public function groupExists(string $gid): bool;
        public function isInGroup(string $uid, string $gid): bool;
    }
    interface IUser { public function getUID(): string; }
    interface IUserSession { public function getUser(): ?IUser; }
}

namespace {
    use OCA\FlzRoom\Service\OrganizationGroupPolicyService;

    $config = new class implements OCP\IAppConfig {
        /** @var array<string,string> */ public array $values = [];
        public int $writes = 0;
        public function getValueString(string $appId, string $key, string $default = ''): string { return $this->values[$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->writes++; $this->values[$key] = $value; }
    };
    $groups = new class implements OCP\IGroupManager {
        /** @var list<string> */ public array $existing = ['Organisation Nord', 'Organisation Süd', 'Datenschutzbeauftragte', 'flz-Sekretariat'];
        /** @var array<string,list<string>> */ public array $memberships = [
            'dpo' => ['Datenschutzbeauftragte'],
            'orga-user' => ['Organisation Nord'],
            'secretariat' => ['Organisation Süd', 'flz-Sekretariat'],
        ];
        public bool $failMembershipLookup = false;
        public function groupExists(string $gid): bool { return in_array($gid, $this->existing, true); }
        public function isInGroup(string $uid, string $gid): bool {
            if ($this->failMembershipLookup) throw new RuntimeException('Gruppenprüfung fehlgeschlagen.');
            return in_array($gid, $this->memberships[$uid] ?? [], true);
        }
    };
    $currentUser = new class implements OCP\IUser { public string $uid = 'ordinary'; public function getUID(): string { return $this->uid; } };
    $session = new class($currentUser) implements OCP\IUserSession {
        public function __construct(private OCP\IUser $user) {}
        public function getUser(): ?OCP\IUser { return $this->user; }
    };
    $service = new OrganizationGroupPolicyService($config, $groups, $session);

    if ($service->organizationGroupIds() !== [] || $service->isOrganizationMember('orga-user')) {
        throw new RuntimeException('Eine fehlende Gruppenkonfiguration muss deny by default wirken.');
    }
    try {
        $service->save(['Organisation Nord']);
        throw new RuntimeException('Ein gewöhnliches Konto konnte Organisationsgruppen konfigurieren.');
    } catch (DomainException) {
    }
    if ($config->writes !== 0) throw new RuntimeException('Ein verweigerter Konfigurationsversuch hat AppConfig verändert.');

    $currentUser->uid = 'dpo';
    if (!$service->canConfigure()) throw new RuntimeException('Datenschutzbeauftragte können die Organisationsgruppen nicht konfigurieren.');
    $saved = $service->save([' Organisation Süd ', 'Organisation Nord', 'Organisation Nord']);
    if ($saved !== ['Organisation Nord', 'Organisation Süd'] || $config->writes !== 1) {
        throw new RuntimeException('Organisationsgruppen werden nicht normalisiert und eindeutig gespeichert.');
    }
    if (!$service->isOrganizationMember('orga-user') || !$service->isOrganizationMember('secretariat') || $service->isOrganizationMember('ordinary')) {
        throw new RuntimeException('Die gespeicherte Organisationsmitgliedschaft wird nicht korrekt ausgewertet.');
    }
    if (!$service->isSecretariat('secretariat') || $service->isSecretariat('orga-user')) {
        throw new RuntimeException('Die kanonische Sekretariatsrolle wird nicht getrennt ausgewertet.');
    }

    foreach ([['Unbekannte Gruppe'], ["Organisation Nord\nManipulation"], [42]] as $invalid) {
        try {
            $service->save($invalid);
            throw new RuntimeException('Eine ungültige oder unbekannte Gruppen-ID wurde gespeichert.');
        } catch (InvalidArgumentException) {
        }
    }
    if ($config->writes !== 1) throw new RuntimeException('Eine abgelehnte Gruppenliste hat AppConfig verändert.');

    $groups->failMembershipLookup = true;
    if ($service->isOrganizationMember('orga-user') || $service->isSecretariat('secretariat')) {
        throw new RuntimeException('Ein Gruppenprüffehler muss fail closed wirken.');
    }

    echo "Filzmann Raumplaner organization group policy tests passed\n";
}
