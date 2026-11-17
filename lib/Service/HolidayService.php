<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use DateTimeImmutable;

/** Zweck: Projiziert die gemeinsamen regionalen Feiertage auf den angefragten Monat. */
final class HolidayService
{
    public function __construct(private OrganizationHolidayCalendarService $calendars)
    {
    }

    /** @return array<string,string> */
    public function forMonth(int $year, int $month): array
    {
        if ($month < 1 || $month > 12)
        {
            throw new \InvalidArgumentException('Monat ist ungültig.');
        }

        $monthPrefix = sprintf('%04d-%02d-', $year, $month);
        $dates = [];

        $calendar = $this->calendars->forYear($year);
        foreach ($calendar['publicHolidays'] ?? [] as $period)
        {
            if (!is_array($period))
            {
                continue;
            }

            $name = trim((string)($period['name'] ?? ''));
            $start = $this->parseDate($period['startDate'] ?? null);
            $end = $this->parseDate($period['endDate'] ?? null);
            if ($name === '' || $start === null || $end === null || $end < $start)
            {
                continue;
            }

            for ($date = $start; $date <= $end; $date = $date->modify('+1 day'))
            {
                $key = $date->format('Y-m-d');
                if (str_starts_with($key, $monthPrefix))
                {
                    $dates[$key] = $name;
                }
            }
        }

        ksort($dates);
        return $dates;
    }

    private function parseDate(mixed $dateString): ?DateTimeImmutable
    {
        if (!is_string($dateString) || trim($dateString) === '')
        {
            return null;
        }
        try
        {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateString);
            return $date !== false && $date->format('Y-m-d') === $dateString ? $date : null;
        }
        catch (\Throwable)
        {
            return null;
        }
    }
}
