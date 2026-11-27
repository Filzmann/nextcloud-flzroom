<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use DomainException;
use InvalidArgumentException;
use OCA\FlzRoom\AppInfo\AppId;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

/** App-local capability assignments keyed by semantic organization V1 role identities. */
final class RoomPermissionPolicyService {
    public const ROOM_READ = 'room.read';
    public const BOOKING_MANAGE_OWN = 'booking.manage-own';
    public const BOOKING_TITLE_READ = 'booking.title.read';
    public const BOOKING_INTERVENE_FOREIGN = 'booking.intervene-foreign';
    public const ROOM_MANAGE = 'room.manage';

    private const CONFIG_KEY = 'permission_role_assignments_v1';
    private const DPO_GROUP = 'Datenschutzbeauftragte';
    private const ASSISTANT_GROUP = 'flz-AS';
    private const ASSISTANT_TEAM_PREFIX = 'flz-ASN-';

    /** @var array<string,array{label:string,description:string}> */
    private const PERMISSIONS = [
        self::ROOM_READ => ['label' => 'Raumplan lesen', 'description' => 'Monatsplan und Buchungsdaten lesen'],
        self::BOOKING_MANAGE_OWN => ['label' => 'Eigene Buchungen verwalten', 'description' => 'Eigene Buchungen anlegen, ändern, verschieben und löschen'],
        self::BOOKING_TITLE_READ => ['label' => 'Freie Buchungstitel lesen', 'description' => 'Frei eingegebene Buchungstitel lesen'],
        self::BOOKING_INTERVENE_FOREIGN => ['label' => 'Fremde Buchungen bearbeiten', 'description' => 'Fremde Buchungen nur begründet und mit aktivem Risikoskop ändern oder löschen'],
        self::ROOM_MANAGE => ['label' => 'Räume verwalten', 'description' => 'Raumstammdaten anlegen, ändern und löschen'],
    ];

    /** @var array<string,list<string>|string> */
    private const DEFAULT_ROLE_KEYS = [
        self::ROOM_READ => '*',
        self::BOOKING_MANAGE_OWN => '*',
        self::BOOKING_TITLE_READ => '*',
        self::BOOKING_INTERVENE_FOREIGN => ['secretariat', 'bl', 'deputy_bl', 'pdl', 'deputy_pdl', 'gf_as', 'gf_digi'],
        self::ROOM_MANAGE => ['secretariat'],
    ];

    public function __construct(
        private IAppConfig $config,
        private IGroupManager $groups,
        private IUserManager $users,
        private IUserSession $session,
        private OrganizationRoleCatalogService $catalog,
        private LoggerInterface $logger,
    ) {}

    public function canConfigure(): bool {
        $uid = $this->session->getUser()?->getUID() ?? '';
        if ($uid === '') return false;
        try {
            return $this->groups->isInGroup($uid, self::DPO_GROUP);
        } catch (Throwable $error) {
            $this->logger->warning('Raumplaner permission configurator check failed', [
                'exceptionClass' => $error::class,
            ]);
            return false;
        }
    }

    public function isGranted(string $uid, string $permission): bool {
        if ($uid === '' || !isset(self::PERMISSIONS[$permission])) return false;
        try {
            $user = $this->users->get($uid);
            if ($user === null) return false;
            $actorGroupIds = array_map('strval', $this->groups->getUserGroupIds($user));
            if (in_array(self::ASSISTANT_GROUP, $actorGroupIds, true)) return false;
            foreach ($actorGroupIds as $groupId) {
                if (str_starts_with($groupId, self::ASSISTANT_TEAM_PREFIX)) return false;
            }
            $configuration = $this->resolvedConfiguration();
            if ($configuration['status'] !== 'VALID') return false;
            $roleGroupIds = [];
            foreach ($configuration['roles'] as $role) $roleGroupIds[$role['roleKey']] = $role['groupId'];
            foreach ($configuration['assignments'][$permission] as $roleKey) {
                if (isset($roleGroupIds[$roleKey]) && in_array($roleGroupIds[$roleKey], $actorGroupIds, true)) return true;
            }
        } catch (Throwable $error) {
            $this->logger->warning('Raumplaner permission check failed', [
                'permission' => $permission,
                'exceptionClass' => $error::class,
            ]);
        }
        return false;
    }

    /** @return list<string> */
    public function groupIdsFor(string $permission): array {
        if (!isset(self::PERMISSIONS[$permission])) return [];
        $configuration = $this->resolvedConfiguration();
        if ($configuration['status'] !== 'VALID') return [];
        $assigned = array_fill_keys($configuration['assignments'][$permission], true);
        return array_values(array_map(
            static fn(array $role): string => $role['groupId'],
            array_filter($configuration['roles'], static fn(array $role): bool => isset($assigned[$role['roleKey']])),
        ));
    }

    /**
     * @return array{status:string,message:?string,roles:list<array{roleKey:string,label:string}>,permissions:list<array{permissionKey:string,label:string,description:string}>,assignments:array<string,list<string>>}
     */
    public function configuration(): array {
        $resolved = $this->resolvedConfiguration();
        return [
            'status' => $resolved['status'],
            'message' => $resolved['message'],
            'roles' => array_map(
                static fn(array $role): array => ['roleKey' => $role['roleKey'], 'label' => $role['label']],
                $resolved['roles'],
            ),
            'permissions' => array_map(
                static fn(string $permissionKey, array $definition): array => ['permissionKey' => $permissionKey, ...$definition],
                array_keys(self::PERMISSIONS),
                array_values(self::PERMISSIONS),
            ),
            'assignments' => $resolved['assignments'],
        ];
    }

    /** @return array<string,list<string>> */
    public function save(array $assignments): array {
        if (!$this->canConfigure()) throw new DomainException('Zugriff verweigert.');
        $catalog = $this->catalog->catalog();
        if ($catalog['status'] !== 'VALID') throw new UnexpectedValueException('Der Organisationssnapshot ist nicht verfügbar.');
        $available = array_fill_keys(array_column($catalog['roles'], 'roleKey'), true);
        if (array_keys($assignments) !== array_keys(self::PERMISSIONS)) {
            throw new InvalidArgumentException('Die Rechtezuordnung ist unvollständig oder enthält unbekannte Rechte.');
        }
        $normalized = [];
        foreach (self::PERMISSIONS as $permission => $_definition) {
            $roleKeys = $assignments[$permission];
            if (!is_array($roleKeys)) throw new InvalidArgumentException('Die Rollenzuordnung ist ungültig.');
            $roles = [];
            foreach ($roleKeys as $roleKey) {
                if (!is_string($roleKey) || !isset($available[$roleKey])) {
                    throw new InvalidArgumentException('Die Rollenzuordnung enthält eine unbekannte Organigrammrolle.');
                }
                $roles[$roleKey] = true;
            }
            $normalized[$permission] = array_values(array_keys($roles));
        }
        $this->config->setValueString(AppId::VALUE, self::CONFIG_KEY, json_encode($normalized, JSON_THROW_ON_ERROR));
        return $normalized;
    }

    /**
     * @return array{status:string,message:?string,roles:list<array{roleKey:string,groupId:string,label:string}>,assignments:array<string,list<string>>}
     */
    private function resolvedConfiguration(): array {
        $catalog = $this->catalog->catalog();
        if ($catalog['status'] !== 'VALID') {
            return ['status' => $catalog['status'], 'message' => $catalog['message'], 'roles' => [], 'assignments' => $this->emptyAssignments()];
        }
        $availableRoleKeys = array_column($catalog['roles'], 'roleKey');
        $encoded = $this->config->getValueString(AppId::VALUE, self::CONFIG_KEY, '');
        if ($encoded === '') {
            return [
                'status' => 'VALID',
                'message' => null,
                'roles' => $catalog['roles'],
                'assignments' => $this->defaultAssignments($availableRoleKeys),
            ];
        }
        try {
            $decoded = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || array_keys($decoded) !== array_keys(self::PERMISSIONS)) throw new InvalidArgumentException();
            $available = array_fill_keys($availableRoleKeys, true);
            $normalized = [];
            foreach (self::PERMISSIONS as $permission => $_definition) {
                if (!is_array($decoded[$permission])) throw new InvalidArgumentException();
                $normalized[$permission] = [];
                foreach ($decoded[$permission] as $roleKey) {
                    if (!is_string($roleKey) || !isset($available[$roleKey])) throw new InvalidArgumentException();
                    $normalized[$permission][$roleKey] = true;
                }
                $normalized[$permission] = array_values(array_keys($normalized[$permission]));
            }
            return ['status' => 'VALID', 'message' => null, 'roles' => $catalog['roles'], 'assignments' => $normalized];
        } catch (Throwable $error) {
            $this->logger->warning('Ungültige Berechtigungskonfiguration des Raumplaners', ['exceptionClass' => $error::class]);
            return [
                'status' => 'INVALID_CONFIGURATION',
                'message' => 'Die gespeicherte Rechtezuordnung ist ungültig.',
                'roles' => $catalog['roles'],
                'assignments' => $this->emptyAssignments(),
            ];
        }
    }

    /** @param list<string> $availableRoleKeys @return array<string,list<string>> */
    private function defaultAssignments(array $availableRoleKeys): array {
        $available = array_fill_keys($availableRoleKeys, true);
        $result = [];
        foreach (self::DEFAULT_ROLE_KEYS as $permission => $defaults) {
            $result[$permission] = $defaults === '*'
                ? $availableRoleKeys
                : array_values(array_filter($defaults, static fn(string $roleKey): bool => isset($available[$roleKey])));
        }
        return $result;
    }

    /** @return array<string,list<string>> */
    private function emptyAssignments(): array {
        return array_fill_keys(array_keys(self::PERMISSIONS), []);
    }
}
