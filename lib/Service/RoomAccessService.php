<?php

declare(strict_types=1);

namespace OCA\AdRoom\Service;

use OCA\AdRoom\Model\Booking;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

/** Zweck: Buendelt die serverseitige Eigen-/Admin-Grenze fuer Raumdaten. */
final class RoomAccessService {
    public function __construct(
        private IUserSession $session,
        private IGroupManager $groups,
        private TemporaryAdminAccessChecker $temporaryAdminAccess,
    ) {}
    public function currentUser(): ?IUser { return $this->session->getUser(); }
    public function currentUid(): string { return $this->currentUser()?->getUID() ?? ''; }
    public function canView(): bool { return $this->currentUser() !== null; }
    public function canManageRooms(): bool {
        $uid = $this->currentUid();
        return $uid !== ''
            && $this->groups->isAdmin($uid)
            && $this->temporaryAdminAccess->hasActiveGrant($uid);
    }
    public function canManageBooking(Booking $booking): bool { return $this->canManageRooms() || ($this->currentUid() !== '' && hash_equals($booking->userUid(),$this->currentUid())); }
}
