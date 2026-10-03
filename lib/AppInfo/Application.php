<?php

declare(strict_types=1);

namespace OCA\AdRoom\AppInfo;

use OCA\AdRoom\Listener\IntegrationCapabilityQueryListener;
use OCA\AdRoom\Listener\StandaloneNavigationListener;
use OCA\AdRoom\Notification\Notifier;
use OCA\AdRoom\Privacy\RoomPrivacyProviderListener;
use OCA\AdRoom\Privacy\RoomRetentionExecutionProviderListener;
use OCA\AdRoom\Privacy\RoomPersonalDataProviderListener;
use OCA\AdRoom\Privacy\RoomProcessingMetadataProviderListener;
use OCA\AdRoom\Permission\RoomPermissionProviderListener;
use OCA\AdRoom\Repository\TemporaryAdminAccessRepository;
use OCA\AdRoom\Repository\TemporaryAdminAccessRepositoryInterface;
use OCA\AdRoom\Service\TemporaryAdminAccessChecker;
use OCA\AdRoom\Service\TemporaryAdminAccessService;
use OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCA\FilzmannDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
use OCA\FilzmannDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent;
use OCA\FilzmannDataProtection\PublicApi\V2\RegisterRetentionExecutionProvidersEvent;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
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
