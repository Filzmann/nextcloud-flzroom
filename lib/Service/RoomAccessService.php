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
        private OrganizationGroupPolicyService $organizationGroups,
    ) {}
    public function currentUser(): ?IUser { return $this->session->getUser(); }
    public function currentUid(): string { return $this->currentUser()?->getUID() ?? ''; }
    public function canView(): bool {
        $uid = $this->currentUid();
        return $uid !== '' && ($this->organizationGroups->isOrganizationMember($uid) || $this->canManageRooms());
    }
    public function canManageRooms(): bool {
        $uid = $this->currentUid();
        return $uid !== ''
            && $this->groups->isAdmin($uid)
            && $this->temporaryAdminAccess->hasActiveGrant($uid);
    }
    public function canManageBooking(Booking $booking): bool {
        $uid = $this->currentUid();
        return $this->canManageRooms() || ($this->canView() && $uid !== '' && hash_equals($booking->userUid(), $uid));
    }
    public function canViewBookingTitle(Booking $booking): bool {
        $uid = $this->currentUid();
        return $uid !== '' && (
            hash_equals($booking->userUid(), $uid)
            || $this->organizationGroups->isSecretariat($uid)
        );
    }
}
