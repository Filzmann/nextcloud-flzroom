<?php

declare(strict_types=1);

namespace OCP\App {
    interface IAppManager
    {
        public function getEnabledApps(): array;
    }
}

namespace OCP {
    final class Server
    {
        public static mixed $service = null;

        public static function get(string $name): mixed
        {
            return self::$service;
        }
    }
}

namespace Psr\Log {
    interface LoggerInterface
    {
        public function emergency($message, array $context = []): void;
        public function alert($message, array $context = []): void;
        public function critical($message, array $context = []): void;
        public function error($message, array $context = []): void;
        public function warning($message, array $context = []): void;
        public function notice($message, array $context = []): void;
        public function info($message, array $context = []): void;
        public function debug($message, array $context = []): void;
        public function log($level, $message, array $context = []): void;
    }
}

namespace OCA\FlzOrganization\PublicApi\V1 {
    final class Contract
    {
        public static function supports(string $version): bool
        {
            return $version === '1.0';
        }
    }

    final class HolidayCalendarSnapshot
    {
        public function __construct(
            private string $contractVersion = '1.0',
            private bool $usable = true,
        )
        {
        }

        public function contractVersion(): string
        {
            return $this->contractVersion;
        }

        public function isUsable(): bool
        {
            return $this->usable;
        }

        public function toArray(): array
        {
            return [
                'contractVersion' => $this->contractVersion,
                'cacheStatus' => $this->usable ? 'fresh' : 'unavailable',
                'publicHolidays' => $this->usable
                    ? [
                        [
                            'name' => 'Regionaler Feiertag',
                            'startDate' => '2027-03-08',
                            'endDate' => '2027-03-08',
                        ],
                    ]
                    : [],
            ];
        }
    }

    final class HolidayCalendarProvider
    {
        public function __construct(
            private HolidayCalendarSnapshot|\Throwable $result = new HolidayCalendarSnapshot(),
        )
        {
        }

        public function calendar(int $year): HolidayCalendarSnapshot
        {
            if ($this->result instanceof \Throwable)
            {
                throw $this->result;
            }
            return $this->result;
        }
    }
}

namespace {
    use OCA\FlzRoom\Service\OrganizationHolidayCalendarService;
    use OCP\App\IAppManager;
    use OCP\Server;
    use Psr\Log\LoggerInterface;

    final class RoomHolidayApps implements IAppManager
    {
        public function __construct(
            private array $enabled,
            private ?\Throwable $failure = null,
        )
        {
        }

        public function getEnabledApps(): array
        {
            if ($this->failure !== null)
            {
                throw $this->failure;
            }
            return $this->enabled;
        }
    }

    final class RoomHolidayLogger implements LoggerInterface
    {
        public array $warnings = [];
        public function emergency($message, array $context = []): void
        {
        }

        public function alert($message, array $context = []): void
        {
        }

        public function critical($message, array $context = []): void
        {
        }

        public function error($message, array $context = []): void
        {
        }

        public function warning($message, array $context = []): void
        {
            $this->warnings[] = [$message, $context];
        }

        public function notice($message, array $context = []): void
        {
        }

        public function info($message, array $context = []): void
        {
        }

        public function debug($message, array $context = []): void
        {
        }

        public function log($level, $message, array $context = []): void
        {
        }
    }

    $assert = static function (bool $condition, string $message): void
    {
        if (!$condition)
        {
            throw new RuntimeException($message);
        }
    };

    Server::$service = new \OCA\FlzOrganization\PublicApi\V1\HolidayCalendarProvider();
    $logger = new RoomHolidayLogger();
    $service = new OrganizationHolidayCalendarService(new RoomHolidayApps(['flz_organization']), $logger);
    $calendar = $service->forYear(2027);
    $assert(
        ($calendar['publicHolidays'][0]['name'] ?? null) === 'Regionaler Feiertag',
        'Der öffentliche Feiertagssnapshot wird nicht unverändert projiziert.',
    );
    $assert($logger->warnings === [], 'Ein gültiger Snapshot erzeugt eine Warnung.');
    $month = (new \OCA\FlzRoom\Service\HolidayService($service))->forMonth(2027, 3);
    $assert(
        $month === ['2027-03-08' => 'Regionaler Feiertag'],
        'Der öffentliche V1-Snapshot erreicht die Monatsansicht nicht mit Datum und Bezeichnung.',
    );

    $localBaseOnly = new OrganizationHolidayCalendarService(
        new RoomHolidayApps(['localbase']),
        new RoomHolidayLogger(),
    );
    $assert(
        $localBaseOnly->forYear(2027) === null,
        'LocalBase darf den fehlenden Standalone-Provider nicht ersetzen.',
    );
    $assert(
        (new \OCA\FlzRoom\Service\HolidayService($localBaseOnly))->forMonth(2027, 3) === [],
        'Die Monatsansicht darf ohne Standalone-Provider keine Feiertage behaupten.',
    );

    foreach ([
        [new \OCA\FlzOrganization\PublicApi\V1\HolidayCalendarSnapshot('2.0'), 'inkompatibler'],
        [new \OCA\FlzOrganization\PublicApi\V1\HolidayCalendarSnapshot('1.0', false), 'nicht verwendbarer'],
    ] as [$snapshot, $description])
    {
        Server::$service = new \OCA\FlzOrganization\PublicApi\V1\HolidayCalendarProvider($snapshot);
        $consumer = new OrganizationHolidayCalendarService(
            new RoomHolidayApps(['flz_organization']),
            new RoomHolidayLogger(),
        );
        $assert(
            $consumer->forYear(2027) === null,
            "Ein {$description} Feiertagssnapshot darf keine Feiertage freigeben.",
        );
    }

    $providerLogger = new RoomHolidayLogger();
    Server::$service = new \OCA\FlzOrganization\PublicApi\V1\HolidayCalendarProvider(
        new RuntimeException('synthetic provider failure'),
    );
    $providerFailure = new OrganizationHolidayCalendarService(
        new RoomHolidayApps(['flz_organization']),
        $providerLogger,
    );
    $assert($providerFailure->forYear(2027) === null, 'Ein Providerfehler muss fail-closed bleiben.');
    $assert(
        ($providerLogger->warnings[0][1] ?? null) === [
            'app' => 'flzroom',
            'provider' => 'flz_organization',
            'exceptionClass' => RuntimeException::class,
        ],
        'Ein Providerfehler wird nicht datensparsam diagnostiziert.',
    );

    $appLogger = new RoomHolidayLogger();
    $appFailure = new OrganizationHolidayCalendarService(
        new RoomHolidayApps([], new RuntimeException('synthetic app registry failure')),
        $appLogger,
    );
    $assert($appFailure->forYear(2027) === null, 'Ein AppRegistry-Fehler muss fail-closed bleiben.');
    $assert(count($appLogger->warnings) === 1, 'Ein AppRegistry-Fehler bleibt nicht diagnostizierbar.');

    try
    {
        $service->forYear(1999);
        throw new RuntimeException('Ein ungültiges Jahr wurde akzeptiert.');
    }
    catch (InvalidArgumentException)
    {
    }

    echo "Filzmann Raumplaner organization holiday tests passed\n";
}
