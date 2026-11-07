<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use DateTimeImmutable;
use OCA\FlzOrganization\PublicApi\V1\HolidayCalendarProvider as FlzOrganizationHolidayCalendarProvider;
use OCA\LocalBase\Calendar\HolidayCalendarService as LocalBaseHolidayCalendarService;
use OCP\App\IAppManager;
use OCP\Server;

/**
 * Holiday Calendar Service Adapter für flzroom.
 * 
 * Priorität:
 * 1. flz_organization (neue kanonische Quelle für Kalenderkontext und Feiertage)
 * 2. localbase (historischer Fallback für Kompatibilität)
 * 
 * Diese Klasse implementiert die gleiche Schnittstelle wie LocalBaseHolidayCalendarService
 * für die forYear()-Methode, die ein Array mit publicHolidays erwartet.
 */
class OrganizationHolidayCalendarService extends LocalBaseHolidayCalendarService {
    private const FLZ_ORGANIZATION_CLASS = 'OCA\\FlzOrganization\\PublicApi\\V1\\HolidayCalendarProvider';

    public function __construct(
        IAppManager $apps,
        private ?LocalBaseHolidayCalendarService $fallbackService = null
    ) {
        parent::__construct($apps);
    }

    /**
     * Liefert Feiertage für ein bestimmtes Jahr im LocalBase-Format.
     * 
     * @return array{publicHolidays: list<array{name: string, startDate: string, endDate: string}>}
     */
    public function forYear(int $year): object {
        // 1. Versuche flz_organization (neue kanonische Quelle)
        $flzOrgResult = $this->tryFlzOrganization($year);
        if ($flzOrgResult !== null) {
            return $flzOrgResult;
        }

        // 2. Fallback zu LocalBase (historische Kompatibilität)
        if ($this->fallbackService !== null) {
            return $this->fallbackService->forYear($year);
        }

        // Kein Provider verfügbar - gib leeres Array zurück
        return (object)['publicHolidays' => []];
    }

    /**
     * Versucht, Feiertage von flz_organization zu holen und ins LocalBase-Format zu konvertieren.
     *
     * @return object|null Array im LocalBase-Format oder null bei Fehler
     */
    private function tryFlzOrganization(int $year): ?object {
        if (!in_array('flz_organization', $this->apps->getEnabledApps(), true)) {
            return null;
        }

        if (!class_exists(self::FLZ_ORGANIZATION_CLASS)) {
            return null;
        }

        try {
            $provider = Server::get(self::FLZ_ORGANIZATION_CLASS);
            $snapshot = $provider->calendar($year);
            
            if ($snapshot->contractVersion() !== '1.0' || !$snapshot->isUsable()) {
                return null;
            }

            // Konvertiere flz_organization Format zu LocalBase Format
            // flz_organization: publicHolidayDates() gibt [Datum => Name]
            // LocalBase: publicHolidays gibt [{name, startDate, endDate}]
            $publicHolidays = [];
            $timezone = $snapshot->timezone();
            
            foreach ($snapshot->publicHolidayDates() as $date => $label) {
                // Flz_organization liefert einzelne Tage, wir behandeln sie als Ein-Tages-Perioden
                $publicHolidays[] = [
                    'name' => $label,
                    'startDate' => $date,
                    'endDate' => $date,
                ];
            }

            return (object)['publicHolidays' => $publicHolidays];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Gibt die Zeitzone zurück.
     */
    public function timezone(): string {
        // Try flz_organization first
        if (in_array('flz_organization', $this->apps->getEnabledApps(), true)
            && class_exists(self::FLZ_ORGANIZATION_CLASS)) {
            try {
                $provider = Server::get(self::FLZ_ORGANIZATION_CLASS);
                $snapshot = $provider->calendar(2024); // Any year for timezone
                if ($snapshot->contractVersion() === '1.0' && $snapshot->isUsable()) {
                    return $snapshot->timezone();
                }
            } catch (\Throwable) {
                // Fall through to fallback
            }
        }

        // Fallback to LocalBase or default
        if ($this->fallbackService !== null) {
            return $this->fallbackService->timezone();
        }

        return 'Europe/Berlin';
    }
}
