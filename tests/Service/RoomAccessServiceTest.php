<?php

declare(strict_types=1);

namespace OCP {
    interface IUser { public function getUID(): string; }
    interface IUserSession { public function getUser(): ?IUser; }
    interface IGroupManager { public function isAdmin(string $uid): bool; }
}
namespace OCA\FlzRoom\Service {
    interface TemporaryAdminAccessChecker { public function hasActiveGrant(string $uid): bool; }
    class RoomPermissionPolicyService {
        public const ROOM_READ='room.read';
        public const BOOKING_MANAGE_OWN='booking.manage-own';
        public const BOOKING_TITLE_READ='booking.title.read';
        public const ROOM_MANAGE='room.manage';
        public array $grants=[];
        public function isGranted(string $uid,string $permission):bool{return$uid==='anna'&&($this->grants[$permission]??false);}
    }
    final class SecretariatForeignBookingInterventionGuard { public bool $allowed=false; public function allows(\OCA\FlzRoom\Model\Booking $booking):bool{return $this->allowed&&$booking->userUid()!=='anna';} }
}
namespace {
    $user=new class implements OCP\IUser { public function getUID(): string { return 'anna'; } };
    $session=new class($user) implements OCP\IUserSession { public function __construct(private ?OCP\IUser $user){} public function getUser(): ?OCP\IUser{return $this->user;} };
    $groups=new class implements OCP\IGroupManager { public bool $admin=false; public function isAdmin(string $uid): bool{return $this->admin;} };
    $grants=new class implements OCA\FlzRoom\Service\TemporaryAdminAccessChecker {
        public bool $active=false;
        public function hasActiveGrant(string $uid): bool { return $this->active && $uid === 'anna'; }
    };
    $permissions=new OCA\FlzRoom\Service\RoomPermissionPolicyService();
    $foreignGuard=new OCA\FlzRoom\Service\SecretariatForeignBookingInterventionGuard();
    $access=new OCA\FlzRoom\Service\RoomAccessService($session,$groups,$grants,$permissions,$foreignGuard);
    $own=OCA\FlzRoom\Model\Booking::get(['roomId'=>1,'userUid'=>'anna','purpose'=>'AT','title'=>'ASN Nordost','startsAt'=>'2026-07-13T08:00:00+00:00','endsAt'=>'2026-07-13T09:00:00+00:00']);
    $foreign=OCA\FlzRoom\Model\Booking::get(['roomId'=>1,'userUid'=>'bea','purpose'=>'AT','title'=>'ASN West','startsAt'=>'2026-07-13T08:00:00+00:00','endsAt'=>'2026-07-13T09:00:00+00:00']);
    if ($access->canView() || $access->canCreateOwnBooking() || $access->canManageBooking($own) || $access->canManageBooking($foreign) || $access->canManageRooms() || $access->canManageAllBookings()) throw new RuntimeException('Nicht konfigurierte Konten müssen vollständig ausgeschlossen bleiben.');
    $permissions->grants[OCA\FlzRoom\Service\RoomPermissionPolicyService::ROOM_READ]=true;
    $permissions->grants[OCA\FlzRoom\Service\RoomPermissionPolicyService::BOOKING_MANAGE_OWN]=true;
    $permissions->grants[OCA\FlzRoom\Service\RoomPermissionPolicyService::BOOKING_TITLE_READ]=true;
    if (!$access->canView() || !$access->canManageBooking($own) || $access->canManageBooking($foreign) || $access->canManageRooms()) throw new RuntimeException('Eigenrechte einer Organisationskraft sind fehlerhaft.');
    if (!$access->canCreateOwnBooking() || !$access->canViewBookingTitle($own) || !$access->canViewBookingTitle($foreign)) throw new RuntimeException('Getrennte Eigenbuchungs- oder Freititelrechte fehlen.');
    $permissions->grants[OCA\FlzRoom\Service\RoomPermissionPolicyService::ROOM_MANAGE]=true;
    if (!$access->canManageRooms() || $access->canManageAllBookings() || $access->canManageBooking($foreign)) throw new RuntimeException('Raumverwaltung erteilt unzulässig Vollzugriff auf fremde Buchungen.');
    $foreignGuard->allowed=true;
    if (!$access->canInterveneInBooking($foreign) || $access->canInterveneInBooking($own)) throw new RuntimeException('Die UI-Capability bildet den serverseitigen Fremdeingriffs-Guard nicht korrekt ab.');
    $foreignGuard->allowed=false;
    $permissions->grants=[];
    $groups->admin=true;
    if ($access->canManageBooking($foreign) || !$access->canManageRooms() || $access->canManageAllBookings()) throw new RuntimeException('Native Nextcloud-Administration darf Räume, aber ohne app-lokale Freigabe keine fremden Buchungen verwalten.');
    $grants->active=true;
    if (!$access->canView() || !$access->canManageBooking($foreign) || !$access->canManageRooms() || !$access->canManageAllBookings()) throw new RuntimeException('Zeitweise freigegebene Nextcloud-Administration muss app-lokalen Vollzugriff erhalten.');
    if ($access->canViewBookingTitle($foreign)) throw new RuntimeException('Technischer Admin-Vollzugriff darf den Freititel nicht ohne eigenständiges Freititelrecht offenlegen.');
    $groups->admin=false;
    if ($access->canManageBooking($foreign) || $access->canManageRooms() || $access->canManageAllBookings()) throw new RuntimeException('Eine gespeicherte Freigabe darf ohne aktuellen Nextcloud-Adminstatus keinen Vollzugriff erteilen.');
    echo "Filzmann Raumplaner access tests passed\n";
}
