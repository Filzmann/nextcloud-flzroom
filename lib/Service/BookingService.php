<?php

declare(strict_types=1);

namespace OCA\AdRoom\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\AdRoom\Exception\BookingConflictException;
use OCA\AdRoom\Model\Booking;
use OCA\AdRoom\Model\Room;
use OCA\AdRoom\Repository\BookingRepository;
use OCA\LocalBase\Calendar\CalendarContextSettingsService;
use OCP\IUserManager;

/**
 * Zweck: Validiert Buchungszeiten, erzwingt Kollisionsfreiheit und baut die Monatsansicht.
 * Zusammenspiel: ApiController -> BookingService -> BookingRepository; RoomAccessService prüft Zielbuchungen davor.
 */
final class BookingService {
    private DateTimeZone $localTimezone;
    private DateTimeZone $utc;

    public function __construct(
        private BookingRepository $bookings,
        private RoomService $rooms,
        private IUserManager $users,
        private HolidayService $holidays,
        CalendarContextSettingsService $contexts,
    ) {
        $this->localTimezone = $contexts->context()->timezone();
        $this->utc = new DateTimeZone('UTC');
    }

    public function existing(int $id): Booking {
        return $this->bookings->find($id) ?? throw new \OutOfBoundsException('Buchung nicht gefunden.');
    }

    public function month(string $month, RoomAccessService $access): array {
        if (
            !preg_match('/^(\d{4})-(\d{2})$/', $month, $matches)
            || (int)$matches[2] < 1
            || (int)$matches[2] > 12
        ) {
            throw new \InvalidArgumentException('Monat ist ungültig.');
        }

        $start = new DateTimeImmutable($month . '-01 00:00:00', $this->localTimezone);
        $end = $start->modify('+1 month');
        $roomItems = array_map(
            static fn(Room $room): array => $room->toArray(),
            $this->rooms->all(),
        );
        $bookingItems = [];
        foreach ($this->bookings->findRange($start->setTimezone($this->utc), $end->setTimezone($this->utc)) as $booking) {
            $item = $booking->toArray();
            $item['startsAt'] = $booking->startsAt()->setTimezone($this->localTimezone)->format(DATE_ATOM);
            $item['endsAt'] = $booking->endsAt()->setTimezone($this->localTimezone)->format(DATE_ATOM);
            $item['userName'] = $this->users->get($booking->userUid())?->getDisplayName() ?: $booking->userUid();
            $item['canManage'] = $access->canManageBooking($booking);
            $bookingItems[] = $item;
        }

        return [
            'month' => $month,
            'rooms' => $roomItems,
            'bookings' => $bookingItems,
            'holidays' => $this->holidays->forMonth((int)$matches[1], (int)$matches[2]),
            'capabilities' => ['canManageRooms' => $access->canManageRooms()],
        ];
    }

    public function create(int $roomId, string $start, string $end, string $purpose, string $title, string $actorUid): int {
        return $this->save(null, $roomId, $start, $end, $purpose, $title, $actorUid);
    }

    public function update(Booking $existing, int $roomId, string $start, string $end, string $purpose, string $title): int {
        return $this->save($existing->id(), $roomId, $start, $end, $purpose, $title, $existing->userUid());
    }

    public function delete(int $id): void {
        $this->bookings->delete($id);
    }

    private function save(?int $id, int $roomId, string $start, string $end, string $purpose, string $title, string $userUid): int {
        if ($this->rooms->get($roomId) === null) throw new \OutOfBoundsException('Raum nicht gefunden.');

        $purpose = trim($purpose);
        $title = trim($title);
        if ($purpose === '' || $this->length($purpose) > 255) throw new \InvalidArgumentException('Zweck ist erforderlich.');
        if ($title === '' || $this->length($title) > 255) throw new \InvalidArgumentException('Titel ist erforderlich.');
        if ($userUid === '') throw new \InvalidArgumentException('Buchende Person ist erforderlich.');

        $startsAt = $this->parseLocal($start);
        $endsAt = $this->parseLocal($end);
        if ($startsAt->format('Y-m-d') !== $endsAt->format('Y-m-d') || $startsAt >= $endsAt) {
            throw new \InvalidArgumentException('Beginn und Ende müssen am selben Tag in richtiger Reihenfolge liegen.');
        }
        if ((int)$startsAt->format('i') % 5 !== 0 || (int)$endsAt->format('i') % 5 !== 0) {
            throw new \InvalidArgumentException('Buchungen verwenden 5-Minuten-Schritte.');
        }

        $startUtc = $startsAt->setTimezone($this->utc);
        $endUtc = $endsAt->setTimezone($this->utc);
        if ($this->bookings->overlaps($roomId, $startUtc, $endUtc, $id)) {
            throw new BookingConflictException('Der Raum ist in diesem Zeitraum bereits belegt.');
        }

        return $this->bookings->save(Booking::get([
            'id' => $id,
            'roomId' => $roomId,
            'userUid' => $userUid,
            'purpose' => $purpose,
            'title' => $title,
            'startsAt' => $startUtc,
            'endsAt' => $endUtc,
        ]));
    }

    private function parseLocal(string $value): DateTimeImmutable {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $this->localTimezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d\TH:i') !== $value
        ) {
            throw new \InvalidArgumentException('Datum oder Uhrzeit ist ungültig.');
        }
        return $date;
    }

    private function length(string $value): int {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
