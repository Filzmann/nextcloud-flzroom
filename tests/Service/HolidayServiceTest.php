<?php

declare(strict_types=1);

namespace OCA\LocalBase\Calendar {
    final class HolidayCalendar { public function toArray(): array { return ['publicHolidays' => [
        ['name' => 'Regionaler Feiertag', 'startDate' => '2026-03-08', 'endDate' => '2026-03-08'],
        ['name' => 'Zweitägiger Feiertag', 'startDate' => '2026-04-30', 'endDate' => '2026-05-01'],
    ]]; } }
    final class HolidayCalendarService {
        public array $calls = [];
        public function forYear(int $year): HolidayCalendar { $this->calls[] = $year; return new HolidayCalendar(); }
    }
}

namespace {
    $shared = new OCA\LocalBase\Calendar\HolidayCalendarService();
    $service = new OCA\AdRoom\Service\HolidayService($shared);
    if (($service->forMonth(2026, 3)['2026-03-08'] ?? '') !== 'Regionaler Feiertag') throw new RuntimeException('Gemeinsamer regionaler Feiertag fehlt.');
    $may = $service->forMonth(2026, 5);
    if (($may['2026-05-01'] ?? '') !== 'Zweitägiger Feiertag' || isset($may['2026-04-30'])) throw new RuntimeException('Mehrtagiger Feiertag wird nicht auf den angefragten Monat begrenzt.');
    if ($shared->calls !== [2026, 2026]) throw new RuntimeException('AD Raumplaner liest nicht den gemeinsamen Jahresvertrag.');
    echo "AD Raumplaner holiday tests passed\n";
}
