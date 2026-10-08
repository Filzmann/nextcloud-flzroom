<?php

declare(strict_types=1);

use OCA\FlzRoom\Service\RoomAccessService;
use OCA\FlzRoom\Service\RoomRetentionPolicyService;

return [
    'providerRegistrations' => [
        'flz_data_protection' => [
            OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent::class,
            OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent::class,
            OCA\FlzDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent::class,
        ],
        'flz_permission_matrix' => [
            OCA\FlzPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent::class,
        ],
    ],
    'providerSetup' => static fn(): array => OCP\Server::get(RoomRetentionPolicyService::class)->policy(),
    'uiPath' => '/index.php/apps/flzroom/',
    'preGrantUiStatuses' => [200],
    'postGrantUiStatuses' => [200],
    'grantService' => OCA\FlzRoom\Service\TemporaryAdminAccessService::class,
    'grantManagerGroups' => static fn(): array => [OCA\FlzRoom\Service\TemporaryAdminAccessService::GRANT_MANAGER_GROUP],
    'permissionProbe' => static fn(string $uid): bool => OCP\Server::get(RoomAccessService::class)->canManageRooms(),
    'apiSmokes' => [
        ['/index.php/apps/flzroom/api/month/2035-01', [200]],
    ],
];
