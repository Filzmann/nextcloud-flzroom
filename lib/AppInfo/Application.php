<?php

declare(strict_types=1);

namespace OCA\FlzRoom\AppInfo;

use OCA\FlzRoom\Listener\IntegrationCapabilityQueryListener;
use OCA\FlzRoom\Listener\StandaloneNavigationListener;use OCA\FlzRoom\Listener\UserDeletedLayoutListener;

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
use OCA\FlzOrganization\PublicApi\V1\IntegrationCapabilityQueryEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;
use OCP\User\Events\UserDeletedEvent;

/** Zweck: Registriert Raumfähigkeiten und Standalone-Navigation im Nextcloud-Bootstrap. */
final class Application extends App implements IBootstrap
{
    public const APP_ID = AppId::VALUE;

    public function __construct(array $urlParams = [])
    {
        require_once dirname(__DIR__, 2) . '/vendor/flz-shared/php/vendor/autoload.php';
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerEventListener(IntegrationCapabilityQueryEvent::class, IntegrationCapabilityQueryListener::class);
        $context->registerEventListener(RegisterPersonalDataProvidersEvent::class, RoomPersonalDataProviderListener::class);
        $context->registerEventListener(RegisterProcessingMetadataProvidersEvent::class, RoomProcessingMetadataProviderListener::class);
        $context->registerEventListener(RegisterPermissionProvidersEvent::class, RoomPermissionProviderListener::class);
        $context->registerEventListener(RegisterRetentionProvidersEvent::class, RoomPrivacyProviderListener::class);
        $context->registerEventListener(RegisterRetentionExecutionProvidersEvent::class, RoomRetentionExecutionProviderListener::class);
        $context->registerEventListener(LoadAdditionalEntriesEvent::class, StandaloneNavigationListener::class);
        $context->registerEventListener(UserDeletedEvent::class, UserDeletedLayoutListener::class);
        $context->registerNotifierService(Notifier::class);
        $context->registerServiceAlias(TemporaryAdminAccessChecker::class, TemporaryAdminAccessService::class);
        $context->registerServiceAlias(TemporaryAdminAccessRepositoryInterface::class, TemporaryAdminAccessRepository::class);
    }

    public function boot(IBootContext $context): void
    {
    }
}
