<?php

declare(strict_types=1);

namespace {
    $shared = new class extends OCA\FlzRoom\Service\OrganizationHolidayCalendarService {
        public array $calls = [];
        public function __construct() {}
        public function forYear(int $year): ?array {
            $this->calls[] = $year;
            return ['publicHolidays' => [
                ['name' => 'Regionaler Feiertag', 'startDate' => '2026-03-08', 'endDate' => '2026-03-08'],
                ['name' => 'Zweitägiger Feiertag', 'startDate' => '2026-04-30', 'endDate' => '2026-05-01'],
            ]];
        }
    };
    $service = new OCA\FlzRoom\Service\HolidayService($shared);
    if (($service->forMonth(2026, 3)['2026-03-08'] ?? '') !== 'Regionaler Feiertag') throw new RuntimeException('Gemeinsamer regionaler Feiertag fehlt.');
    $may = $service->forMonth(2026, 5);
    if (($may['2026-05-01'] ?? '') !== 'Zweitägiger Feiertag' || isset($may['2026-04-30'])) throw new RuntimeException('Mehrtagiger Feiertag wird nicht auf den angefragten Monat begrenzt.');
    if ($shared->calls !== [2026, 2026]) throw new RuntimeException('Filzmann Raumplaner liest nicht den gemeinsamen Jahresvertrag.');
    echo "Filzmann Raumplaner holiday tests passed\n";
}
