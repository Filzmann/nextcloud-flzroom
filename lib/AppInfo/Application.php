<?php

declare(strict_types=1);

namespace OCA\FlzRoom\AppInfo;

use OCA\FlzRoom\Listener\IntegrationCapabilityQueryListener;
use OCA\FlzRoom\Listener\StandaloneNavigationListener;
use OCA\FlzRoom\Notification\Notifier;
use OCA\FlzRoom\Privacy\RoomPrivacyProviderListener;
use OCA\FlzRoom\Privacy\RoomRetentionExecutionProviderListener;
use OCA\FlzRoom\Privacy\RoomPersonalDataProviderListener;
use OCA\FlzRoom\Privacy\RoomProcessingMetadataProviderListener;
use OCA\FlzRoom\Permission\RoomPermissionProviderListener;
use OCA\FlzRoom\Repository\TemporaryAdminAccessRepository;
use OCA\FlzRoom\Repository\TemporaryAdminAccessRepositoryInterface;
use OCA\FlzRoom\Service\TemporaryAdminAccessChecker;
use OCA\FlzRoom\Service\TemporaryAdminAccessService;
use OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V2\RegisterRetentionExecutionProvidersEvent;
use OCA\FlzPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
use OCA\LocalBase\Integration\IntegrationCapabilityQueryEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

/** Zweck: Registriert Raumfähigkeiten und Standalone-Navigation im Nextcloud-Bootstrap. */
final class Application extends App implements IBootstrap {
    public const APP_ID = AppId::VALUE;

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(IntegrationCapabilityQueryEvent::class, IntegrationCapabilityQueryListener::class);
        $context->registerEventListener(RegisterPersonalDataProvidersEvent::class, RoomPersonalDataProviderListener::class);
        $context->registerEventListener(RegisterProcessingMetadataProvidersEvent::class, RoomProcessingMetadataProviderListener::class);
        $context->registerEventListener(RegisterPermissionProvidersEvent::class, RoomPermissionProviderListener::class);
        $context->registerEventListener(RegisterRetentionProvidersEvent::class, RoomPrivacyProviderListener::class);
        $context->registerEventListener(RegisterRetentionExecutionProvidersEvent::class, RoomRetentionExecutionProviderListener::class);
        $context->registerEventListener(LoadAdditionalEntriesEvent::class, StandaloneNavigationListener::class);
        $context->registerNotifierService(Notifier::class);
        $context->registerServiceAlias(TemporaryAdminAccessChecker::class, TemporaryAdminAccessService::class);
        $context->registerServiceAlias(TemporaryAdminAccessRepositoryInterface::class, TemporaryAdminAccessRepository::class);
    }

    public function boot(IBootContext $context): void {
    }
}
