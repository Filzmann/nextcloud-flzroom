<?php

declare(strict_types=1);

namespace OCA\AdRoom\Service;

use DomainException;
use InvalidArgumentException;
use OCA\AdRoom\AppInfo\AppId;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use Throwable;

/** Kanonische app-lokale Zuordnung der Nextcloud-Gruppen zur Organisation. */
final class OrganizationGroupPolicyService {
    public const SECRETARIAT_GROUP = 'ad-Sekretariat';
    private const CONFIG_KEY = 'organization_group_ids_v1';
    private const MAX_GROUPS = 100;
    private const MAX_GROUP_ID_LENGTH = 255;

    public function __construct(
        private IAppConfig $config,
        private IGroupManager $groups,
        private IUserSession $session,
    ) {}

    public function canConfigure(): bool {
        $uid = $this->session->getUser()?->getUID() ?? '';
        if ($uid === '') return false;
        try {
            return $this->groups->isInGroup($uid, TemporaryAdminAccessService::GRANT_MANAGER_GROUP);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    public function organizationGroupIds(): array {
        $encoded = $this->config->getValueString(AppId::VALUE, self::CONFIG_KEY, '');
        if ($encoded === '') return [];
        try {
            $decoded = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || count($decoded) > self::MAX_GROUPS) return [];
            return $this->normalize($decoded, false);
        } catch (Throwable) {
            return [];
        }
    }

    public function isOrganizationMember(string $uid): bool {
        if ($uid === '') return false;
        try {
            foreach ($this->organizationGroupIds() as $groupId) {
                if ($this->groups->isInGroup($uid, $groupId)) return true;
            }
        } catch (Throwable) {
        }
        return false;
    }

    public function isSecretariat(string $uid): bool {
        if ($uid === '') return false;
        try {
            return $this->groups->isInGroup($uid, self::SECRETARIAT_GROUP);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    public function save(array $groupIds): array {
        if (!$this->canConfigure()) throw new DomainException('Zugriff verweigert.');
        $normalized = $this->normalize($groupIds, true);
        $this->config->setValueString(AppId::VALUE, self::CONFIG_KEY, json_encode($normalized, JSON_THROW_ON_ERROR));
        return $normalized;
    }

    /** @return list<string> */
    private function normalize(array $groupIds, bool $requireExisting): array {
        if (count($groupIds) > self::MAX_GROUPS) throw new InvalidArgumentException('Zu viele Organisationsgruppen.');
        $normalized = [];
        foreach ($groupIds as $groupId) {
            if (!is_string($groupId)) throw new InvalidArgumentException('Gruppen-ID ist ungültig.');
            $groupId = trim($groupId);
            if (
                $groupId === ''
                || strlen($groupId) > self::MAX_GROUP_ID_LENGTH
                || preg_match('/[\x00-\x1F\x7F]/', $groupId) === 1
            ) {
                throw new InvalidArgumentException('Gruppen-ID ist ungültig.');
            }
            if ($requireExisting && !$this->groups->groupExists($groupId)) {
                throw new InvalidArgumentException('Nextcloud-Gruppe existiert nicht: ' . $groupId);
            }
            $normalized[$groupId] = true;
        }
        $result = array_keys($normalized);
        sort($result, SORT_STRING);
        return $result;
    }
}
