<?php

declare(strict_types=1);

namespace OCP {
    interface IUser { public function getDisplayName(): string; }
    interface IUserManager { public function get(string $uid): ?IUser; }
}
namespace OCA\LocalBase\Calendar {
    class Context { public function timezone(): \DateTimeZone { return new \DateTimeZone('Europe/London'); } }
    class CalendarContextSettingsService { public function context(): Context { return new Context(); } }
    class HolidayCalendar { public function toArray(): array { return ['publicHolidays' => [['name' => 'Feiertag', 'startDate' => '2026-05-01', 'endDate' => '2026-05-01']]]; } }
    class HolidayCalendarService { public function forYear(int $year): HolidayCalendar { return new HolidayCalendar(); } }
}

namespace OCA\AdRoom\Repository {
    use DateTimeImmutable;
    use OCA\AdRoom\Model\Booking;

    class BookingRepository {
        /** @var list<Booking> */ public array $bookings = [];
        public ?int $deleted = null;
        public array $lastRange = [];
        public function find(int $id): ?Booking { foreach ($this->bookings as $booking) if ($booking->id() === $id) return $booking; return null; }
        public function findRange(DateTimeImmutable $start, DateTimeImmutable $end): array { $this->lastRange = [$start, $end]; return $this->bookings; }
        public function delete(int $id): void { $this->deleted = $id; }
        public function overlaps(int $roomId, DateTimeImmutable $start, DateTimeImmutable $end, ?int $excludeId = null): bool { return false; }
        public function save(Booking $booking): int { return $booking->id() ?? 7; }
    }
}

namespace OCA\AdRoom\Service {
    use OCA\AdRoom\Model\Booking;
    use OCA\AdRoom\Model\Room;

    class RoomService {
        public function all(): array { return [Room::get(['id' => 2, 'name' => 'Konferenz', 'description' => '', 'sortOrder' => 1])]; }
        public function get(int $id): ?Room { return $id === 2 ? $this->all()[0] : null; }
    }
    class RoomAccessService {
        public function canManageBooking(Booking $booking): bool { return $booking->userUid() === 'anna'; }
        public function canManageRooms(): bool { return true; }
    }
}

namespace {
    use OCA\AdRoom\Model\Booking;
    use OCA\AdRoom\Repository\BookingRepository;
    use OCA\AdRoom\Service\BookingService;
    use OCA\AdRoom\Service\HolidayService;
    use OCA\AdRoom\Service\RoomAccessService;
    use OCA\AdRoom\Service\RoomService;
    use OCP\IUser;
    use OCP\IUserManager;

    $repository = new BookingRepository();
    $repository->bookings = [Booking::get(['id' => 5, 'roomId' => 2, 'userUid' => 'anna', 'purpose' => 'LG', 'title' => 'Leitung', 'startsAt' => '2026-07-13T06:00:00+00:00', 'endsAt' => '2026-07-13T07:00:00+00:00'])];
    $user = new class implements IUser { public function getDisplayName(): string { return 'Anna Beispiel'; } };
    $users = new class($user) implements IUserManager { public function __construct(private IUser $user) {} public function get(string $uid): ?IUser { return $uid === 'anna' ? $this->user : null; } };
    $service = new BookingService($repository, new RoomService(), $users, new HolidayService(new \OCA\LocalBase\Calendar\HolidayCalendarService()), new \OCA\LocalBase\Calendar\CalendarContextSettingsService());
    $month = $service->month('2026-05', new RoomAccessService());
    if ($month['month'] !== '2026-05' || $month['bookings'][0]['userName'] !== 'Anna Beispiel' || !$month['bookings'][0]['canManage'] || !$month['capabilities']['canManageRooms']) {
        throw new RuntimeException('Monatsansicht projiziert Buchungen oder Rechte nicht korrekt.');
    }
    if ($month['bookings'][0]['startsAt'] !== '2026-07-13T07:00:00+01:00' || $month['bookings'][0]['endsAt'] !== '2026-07-13T08:00:00+01:00') {
        throw new RuntimeException('Monatsansicht projiziert Buchungszeiten nicht in die fachliche Organisationszeitzone.');
    }
    if ($repository->lastRange[0]->format(DATE_ATOM) !== '2026-04-30T23:00:00+00:00' || $month['holidays'] === []) throw new RuntimeException('Administrative Monatsgrenzen oder gemeinsame Feiertage fehlen.');
    foreach (['Juli 2026', '2026-00', '2026-13'] as $invalid) {
        try { $service->month($invalid, new RoomAccessService()); throw new RuntimeException('Ungültiger Monat wurde akzeptiert.'); } catch (InvalidArgumentException) {}
    }
    if ($service->existing(5)->title() !== 'Leitung') throw new RuntimeException('Bestehende Buchung wird nicht gefunden.');
    try { $service->existing(404); throw new RuntimeException('Fehlende Buchung wurde akzeptiert.'); } catch (OutOfBoundsException) {}
    $service->delete(5);
    if ($repository->deleted !== 5) throw new RuntimeException('Buchung wurde nicht gelöscht.');

    echo "AD Raumplaner month workflow tests passed\n";
}
