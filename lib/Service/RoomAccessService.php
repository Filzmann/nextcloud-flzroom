<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use OCA\FlzRoom\Model\Booking;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

/** Zweck: Buendelt die serverseitige Eigen-/Admin-Grenze fuer Raumdaten. */
final class RoomAccessService {
    public function __construct(
        private IUserSession $session,
        private IGroupManager $groups,
        private TemporaryAdminAccessChecker $temporaryAdminAccess,
        private RoomPermissionPolicyService $permissions,
        private ?SecretariatForeignBookingInterventionGuard $foreignIntervention = null,
    ) {}
    public function currentUser(): ?IUser { return $this->session->getUser(); }
    public function currentUid(): string { return $this->currentUser()?->getUID() ?? ''; }
    public function canView(): bool {
        $uid = $this->currentUid();
        return $uid !== '' && ($this->permissions->isGranted($uid, RoomPermissionPolicyService::ROOM_READ) || $this->canManageAllBookings());
    }
    public function canManageAllBookings(): bool {
        $uid = $this->currentUid();
        return $uid !== ''
            && $this->groups->isAdmin($uid)
            && $this->temporaryAdminAccess->hasActiveGrant($uid);
    }
    public function canManageRooms(): bool {
        $uid = $this->currentUid();
        return $uid !== '' && (
            $this->groups->isAdmin($uid)
            || $this->permissions->isGranted($uid, RoomPermissionPolicyService::ROOM_MANAGE)
        );
    }
    public function canCreateOwnBooking(): bool {
        $uid = $this->currentUid();
        return $uid !== '' && $this->permissions->isGranted($uid, RoomPermissionPolicyService::BOOKING_MANAGE_OWN);
    }
    public function canManageBooking(Booking $booking): bool {
        $uid = $this->currentUid();
        return $this->canManageAllBookings() || (
            $uid !== ''
            && hash_equals($booking->userUid(), $uid)
            && $this->permissions->isGranted($uid, RoomPermissionPolicyService::BOOKING_MANAGE_OWN)
        );
    }
    public function isOwnBooking(Booking $booking): bool {
        $uid = $this->currentUid();
        return $uid !== '' && hash_equals($booking->userUid(), $uid);
    }
    public function canInterveneInBooking(Booking $booking): bool {
        return $this->foreignIntervention?->allows($booking) ?? false;
    }
    public function canViewBookingTitle(Booking $booking): bool {
        $uid = $this->currentUid();
        return $uid !== '' && $this->permissions->isGranted($uid, RoomPermissionPolicyService::BOOKING_TITLE_READ);
    }
}
