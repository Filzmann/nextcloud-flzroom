<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Permission;

use OCA\FlzRoom\Service\RoomPermissionPolicyService;
use OCA\FlzRoom\Service\TemporaryAdminAccessService;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionCondition;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProvider;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProviderDescriptor;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProviderResult;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionRule;

final class RoomPermissionProvider implements PermissionProvider {
    public function __construct(private ?RoomPermissionPolicyService $permissions = null) {}

    public function descriptor(): PermissionProviderDescriptor {
        return new PermissionProviderDescriptor('flzroom', 'Filzmann Raumplaner', '1.0', ['permissions']);
    }

    public function collect(): PermissionProviderResult {
        $temporaryAdmin = PermissionCondition::all([PermissionCondition::nextcloudAdmin(), PermissionCondition::temporaryAppAdminGrant()]);
        $dpo = PermissionCondition::group(TemporaryAdminAccessService::GRANT_MANAGER_GROUP);
        $rules = [];
        $warnings = [];

        $read = PermissionCondition::any([...$this->groups(RoomPermissionPolicyService::ROOM_READ), $temporaryAdmin]);
        $rules[] = $this->rule('Raum und Buchung', 'Monatsplan', 'Zugewiesene Organigrammrollen und zeitweise freigegebene Nextcloud-Administration dürfen Räume und Buchungen lesen; AS und ASN bleiben serverseitig ausgeschlossen.', RoomPermissionPolicyService::ROOM_READ, 'Lesen', 'app', $read);

        foreach ([
            [RoomPermissionPolicyService::BOOKING_MANAGE_OWN, 'Buchung', 'Eigene Buchung', 'Anlegen, ändern, verschieben und löschen', 'Eigene Buchung verwalten', 'own-booking', true],
            [RoomPermissionPolicyService::BOOKING_TITLE_READ, 'Buchung', 'Freier Titel', 'Zugewiesene Organigrammrollen dürfen den freien Buchungstitel lesen.', 'Freien Titel lesen', 'booking', false],
            [RoomPermissionPolicyService::BOOKING_INTERVENE_FOREIGN, 'Buchung', 'Begründeter Fremdeingriff', 'Zugewiesene Rollen dürfen eine fremde Buchung nur zusätzlich mit aktiver neutraler Risikoscope-Freigabe begründet ändern oder löschen.', 'Fremde Buchung begründet verwalten', 'foreign-booking', false],
        ] as [$permission, $type, $name, $detail, $label, $scope, $requiresSelf]) {
            $condition = $this->roleCondition($permission);
            if ($condition === null) {
                $warnings[] = $permission . ' has no assigned organization role';
                continue;
            }
            if ($requiresSelf) $condition = PermissionCondition::all([$condition, PermissionCondition::self()]);
            $rules[] = $this->rule($type, $name, $detail, $permission, $label, $scope, $condition);
        }

        $rules[] = $this->rule('Buchung', 'Eingriffsaudit', 'Nur Datenschutzbeauftragte dürfen das app-lokale Eingriffsaudit im autorisierten Prüfpfad lesen.', 'booking.intervention-audit.read', 'Eingriffsaudit lesen', 'intervention-audit', $dpo);
        $rules[] = $this->rule('Buchung', 'Alle Buchungen', 'Fremde Buchungen nur mit zeitlich begrenzter app-lokaler Adminfreigabe verwalten.', 'booking.manage-all', 'Alle Buchungen verwalten', 'all-bookings', $temporaryAdmin);

        $roomManagers = $this->groups(RoomPermissionPolicyService::ROOM_MANAGE);
        $rules[] = $this->rule('Raum', 'Raumliste', 'Zugewiesene Organigrammrollen sowie native Nextcloud-Administration dürfen Räume verwalten; dies erteilt keinen Zugriff auf fremde Buchungen.', RoomPermissionPolicyService::ROOM_MANAGE, 'Räume verwalten', 'all-rooms', PermissionCondition::any([...$roomManagers, PermissionCondition::nextcloudAdmin()]));
        if ($roomManagers === []) $warnings[] = 'room.manage has no assigned organization role; native admin path remains active';

        $rules[] = $this->rule('Berechtigung', 'Rechtezuordnung', 'Nur Datenschutzbeauftragte dürfen App-Rechte Organigrammrollen zuordnen.', 'permission.assignments.manage', 'Rechtezuordnung verwalten', 'app-permissions', $dpo);
        $rules[] = $this->rule('Datenschutz', 'Retention und Holds', 'Nur Datenschutzbeauftragte dürfen Retention-Regeln und Holds verwalten.', 'retention.manage', 'Retention und Holds verwalten', 'retention', $dpo);
        $rules[] = $this->rule('Administration', 'Temporäre Freigaben', 'Nur Datenschutzbeauftragte dürfen temporären Admin-Vollzugriff erteilen oder widerrufen.', 'temporary-admin.manage', 'Temporäre Adminfreigaben verwalten', 'temporary-admin-access', $dpo);

        return new PermissionProviderResult($rules, $warnings === [], $warnings);
    }

    /** @return list<PermissionCondition> */
    private function groups(string $permission): array {
        return array_map(
            static fn(string $groupId): PermissionCondition => PermissionCondition::group($groupId),
            $this->permissions?->groupIdsFor($permission) ?? [],
        );
    }

    private function roleCondition(string $permission): ?PermissionCondition {
        $groups = $this->groups($permission);
        if ($groups === []) return null;
        return count($groups) === 1 ? $groups[0] : PermissionCondition::any($groups);
    }

    private function rule(string $type, string $name, string $detail, string $permission, string $label, string $scope, PermissionCondition $condition): PermissionRule {
        return new PermissionRule($type, $name, $detail, $permission, $label, 'allow', $scope, $condition, 'flzroom:RoomAccessService', 'high');
    }
}
