<?php

declare(strict_types=1);

use OCA\AdRoom\Service\RoomAccessService;

return [
    'uiPath' => '/index.php/apps/adroom/',
    'preGrantUiStatuses' => [200],
    'postGrantUiStatuses' => [200],
    'grantService' => OCA\AdRoom\Service\TemporaryAdminAccessService::class,
    'permissionProbe' => static fn(string $uid): bool => OCP\Server::get(RoomAccessService::class)->canManageRooms(),
    'apiSmokes' => [
        ['/index.php/apps/adroom/api/month/2035-01', [200]],
    ],
];
