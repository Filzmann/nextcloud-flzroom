<?php

declare(strict_types=1);

use OCA\AdRoom\Service\RoomAccessService;
use OCA\AdRoom\Service\RoomRetentionPolicyService;

return [
    'providerRegistrations' => [
        'filzmann_data_protection' => [
            OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent::class,
            OCA\FilzmannDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent::class,
            OCA\FilzmannDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent::class,
        ],
        'filzmann_permission_matrix' => [
            OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent::class,
        ],
    ],
    'providerSetup' => static fn(): array => OCP\Server::get(RoomRetentionPolicyService::class)->save([
        'enabled' => true, 'reviewAfterDays' => 365, 'action' => 'REVIEW',
    ]),
    'uiPath' => '/index.php/apps/adroom/',
    'preGrantUiStatuses' => [200],
    'postGrantUiStatuses' => [200],
    'grantService' => OCA\AdRoom\Service\TemporaryAdminAccessService::class,
    'grantManagerGroups' => static fn(): array => [OCA\AdRoom\Service\TemporaryAdminAccessService::GRANT_MANAGER_GROUP],
    'permissionProbe' => static fn(string $uid): bool => OCP\Server::get(RoomAccessService::class)->canManageRooms(),
    'apiSmokes' => [
        ['/index.php/apps/adroom/api/month/2035-01', [200]],
    ],
];
