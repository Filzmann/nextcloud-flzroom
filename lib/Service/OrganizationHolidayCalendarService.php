<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use OCA\FlzOrganization\PublicApi\V1\Contract;
use OCA\FlzOrganization\PublicApi\V1\HolidayCalendarProvider;
use OCP\App\IAppManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

/** Lazy public-V1 holiday projection from the standalone organization runtime. */
class OrganizationHolidayCalendarService
{
    private const PROVIDER_APP_ID = 'flz_organization';
    private const SUPPORTED_CONTRACT_VERSION = '1.0';

    public function __construct(
        private IAppManager $apps,
        private LoggerInterface $logger,
    )
    {
    }

    /** @return array<string,mixed>|null */
    public function forYear(int $year): ?array
    {
        if ($year < 2000 || $year > 2100)
        {
            throw new \InvalidArgumentException('Jahr ist ungültig.');
        }

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
            || !class_exists(HolidayCalendarProvider::class))
        {
            return null;
        }

        try
        {
            $provider = $this->resolveProvider();
            $snapshot = $provider->calendar($year);
            if ($snapshot->contractVersion() !== self::SUPPORTED_CONTRACT_VERSION || !$snapshot->isUsable())
            {
                return null;
            }
            return $snapshot->toArray();
        }
        catch (\Throwable $error)
        {
            $this->logFailure($error);
            return null;
        }
    }

    protected function resolveProvider(): HolidayCalendarProvider
    {
        $provider = Server::get(HolidayCalendarProvider::class);
        if (!$provider instanceof HolidayCalendarProvider)
        {
            throw new \UnexpectedValueException('Holiday V1 provider is not resolvable.');
        }
        return $provider;
    }

    private function logFailure(\Throwable $error): void
    {
        $this->logger->warning(
            'Raumplaner holiday snapshot unavailable',
            [
                'app' => 'flzroom',
                'provider' => self::PROVIDER_APP_ID,
                'exceptionClass' => $error::class,
            ],
        );
    }
}
