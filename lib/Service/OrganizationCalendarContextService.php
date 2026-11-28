<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use DateTimeZone;
use OCA\FlzOrganization\PublicApi\V1\CalendarContextProvider;
use OCA\FlzOrganization\PublicApi\V1\CalendarContextSnapshot;
use OCA\FlzOrganization\PublicApi\V1\Contract;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * Lazy public-V1 calendar context from the standalone organization runtime.
 * Without an enabled, contract-compatible flz_organization the system timezone
 * is the fachliche Zeitzone (Fallback UTC) — ein gueltiger Standalone-Zustand.
 */
class OrganizationCalendarContextService
{
    private const PROVIDER_APP_ID = 'flz_organization';
    private const SUPPORTED_CONTRACT_VERSION = '1.0';

    public function __construct(
        private IAppManager $apps,
        private IConfig $systemConfig,
        private LoggerInterface $logger,
    )
    {
    }

    public function timezone(): DateTimeZone
    {
        $snapshot = $this->snapshot();
        if ($snapshot !== null)
        {
            return new DateTimeZone($snapshot->timezone());
        }
        return $this->systemTimezone();
    }

    public function snapshot(): ?CalendarContextSnapshot
    {
        try
        {
            if (!in_array(self::PROVIDER_APP_ID, $this->apps->getEnabledApps(), true))
            {
                return null;
            }
        }
        catch (\Throwable $error)
        {
            $this->logFailure($error);
            return null;
        }

        if (!class_exists(Contract::class)
            || !Contract::supports(self::SUPPORTED_CONTRACT_VERSION)
            || !class_exists(CalendarContextProvider::class))
        {
            return null;
        }

        try
        {
            $provider = Server::get(CalendarContextProvider::class);
            if (!$provider instanceof CalendarContextProvider)
            {
                return null;
            }
            $snapshot = $provider->context();
            if ($snapshot->contractVersion() !== self::SUPPORTED_CONTRACT_VERSION)
            {
                return null;
            }
            return $snapshot;
        }
        catch (\Throwable $error)
        {
            $this->logFailure($error);
            return null;
        }
    }

    private function systemTimezone(): DateTimeZone
    {
        $name = trim($this->systemConfig->getSystemValueString('timezone', 'UTC'));
        if ($name === '' || !in_array($name, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true))
        {
            return new DateTimeZone('UTC');
        }
        return new DateTimeZone($name);
    }

    private function logFailure(\Throwable $error): void
    {
        $this->logger->warning(
            'Raumplaner calendar context unavailable',
            [
                'app' => 'flzroom',
                'provider' => self::PROVIDER_APP_ID,
                'exceptionClass' => $error::class,
            ],
        );
    }
}
