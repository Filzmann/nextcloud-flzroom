<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use DateTimeImmutable;
use OCA\FlzRoom\Exception\BookingConflictException;
use OCA\LocalBase\Calendar\CalendarContextSettingsService;
use OCA\LocalBase\Service\DemoAccountProvisioningService;

/** Zweck: Installiert neutrale Räume und Beispielbuchungen unter einem registrierten lokalen Demokonto. */
final class RoomDemoPackService {
    public function __construct(
        private DemoAccountProvisioningService $accounts,
        private RoomService $rooms,
        private BookingService $bookings,
        private CalendarContextSettingsService $contexts,
    ) {}

    /** @return array{accounts:array,rooms:int,createdBookings:int,skippedBookings:int} */
    public function install(): array {
        $accounts = $this->accounts->provision('flz-full-suite-demo', [[
            'uid' => 'flz-demo-room', 'displayName' => 'Romy Baum (Raumplaner-Demo)', 'groups' => [],
        ]]);
        $definitions = [
            ['name' => 'Besprechungsraum Nord', 'description' => 'Kleiner Besprechungsraum', 'sortOrder' => 10],
            ['name' => 'Besprechungsraum Süd', 'description' => 'Besprechungsraum für Teams', 'sortOrder' => 20],
            ['name' => 'Konferenzraum', 'description' => 'Großer Raum für Sitzungen und Fortbildungen', 'sortOrder' => 30],
        ];
        $existing = [];
        foreach ($this->rooms->all() as $room) $existing[$room->name()] = $room->id();
        foreach ($definitions as $definition) {
            if (!isset($existing[$definition['name']])) $existing[$definition['name']] = $this->rooms->save(null, $definition['name'], $definition['description'], $definition['sortOrder']);
        }

        $day = new DateTimeImmutable('next monday', $this->contexts->context()->timezone());
        $samples = [
            ['Besprechungsraum Nord', '10:00', '11:00', 'AT', 'ASN Team A'],
            ['Besprechungsraum Süd', '12:00', '13:30', 'Sitzung', 'Büroteam Süd'],
            ['Konferenzraum', '14:00', '16:00', 'Fortbildung', 'Arbeitsschutz'],
        ];
        $createdBookings = 0;
        $skippedBookings = 0;
        foreach ($samples as [$name, $start, $end, $purpose, $title]) {
            try {
                $this->bookings->create((int)$existing[$name], $day->format('Y-m-d') . 'T' . $start, $day->format('Y-m-d') . 'T' . $end, $purpose, $title, 'flz-demo-room');
                $createdBookings++;
            } catch (BookingConflictException) {
                $skippedBookings++;
            }
        }
        return ['accounts' => $accounts, 'rooms' => count($definitions), 'createdBookings' => $createdBookings, 'skippedBookings' => $skippedBookings];
    }
}
