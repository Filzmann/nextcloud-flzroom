<?php

declare(strict_types=1);

namespace OCP {
    interface IUser { public function getUID(): string; }
    interface IUserSession { public function getUser(): ?IUser; }
    interface IGroupManager { public function isAdmin(string $uid): bool; }
}
namespace {
    $user=new class implements OCP\IUser { public function getUID(): string { return 'anna'; } };
    $session=new class($user) implements OCP\IUserSession { public function __construct(private ?OCP\IUser $user){} public function getUser(): ?OCP\IUser{return $this->user;} };
    $groups=new class implements OCP\IGroupManager { public bool $admin=false; public function isAdmin(string $uid): bool{return $this->admin;} };
    $access=new OCA\AdRoom\Service\RoomAccessService($session,$groups);
    $own=OCA\AdRoom\Model\Booking::get(['roomId'=>1,'userUid'=>'anna','purpose'=>'AT','title'=>'ASN Nordost','startsAt'=>'2026-07-13T08:00:00+00:00','endsAt'=>'2026-07-13T09:00:00+00:00']);
    $foreign=OCA\AdRoom\Model\Booking::get(['roomId'=>1,'userUid'=>'bea','purpose'=>'AT','title'=>'ASN West','startsAt'=>'2026-07-13T08:00:00+00:00','endsAt'=>'2026-07-13T09:00:00+00:00']);
    if (!$access->canView() || !$access->canManageBooking($own) || $access->canManageBooking($foreign) || $access->canManageRooms()) throw new RuntimeException('Eigenrechte sind fehlerhaft.');
    $groups->admin=true;
    if (!$access->canManageBooking($foreign) || !$access->canManageRooms()) throw new RuntimeException('Adminrechte sind fehlerhaft.');
    echo "AD Raumplaner access tests passed\n";
}
