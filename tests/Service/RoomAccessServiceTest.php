<?php

declare(strict_types=1);

namespace OCP {
    interface IUser { public function getUID(): string; }
    interface IUserSession { public function getUser(): ?IUser; }
    interface IGroupManager { public function isAdmin(string $uid): bool; }
}
namespace OCA\AdRoom\Service {
    interface TemporaryAdminAccessChecker { public function hasActiveGrant(string $uid): bool; }
    class OrganizationGroupPolicyService {
        public bool $organizationMember=false;
        public bool $secretariat=false;
        public function isOrganizationMember(string $uid): bool { return $this->organizationMember && $uid === 'anna'; }
        public function isSecretariat(string $uid): bool { return $this->secretariat && $uid === 'anna'; }
    }
    final class SecretariatForeignBookingInterventionGuard { public bool $allowed=false; public function allows(\OCA\AdRoom\Model\Booking $booking):bool{return $this->allowed&&$booking->userUid()!=='anna';} }
}
namespace {
    $user=new class implements OCP\IUser { public function getUID(): string { return 'anna'; } };
    $session=new class($user) implements OCP\IUserSession { public function __construct(private ?OCP\IUser $user){} public function getUser(): ?OCP\IUser{return $this->user;} };
    $groups=new class implements OCP\IGroupManager { public bool $admin=false; public function isAdmin(string $uid): bool{return $this->admin;} };
    $grants=new class implements OCA\AdRoom\Service\TemporaryAdminAccessChecker {
        public bool $active=false;
        public function hasActiveGrant(string $uid): bool { return $this->active && $uid === 'anna'; }
    };
    $organizationGroups=new OCA\AdRoom\Service\OrganizationGroupPolicyService();
    $foreignGuard=new OCA\AdRoom\Service\SecretariatForeignBookingInterventionGuard();
    $access=new OCA\AdRoom\Service\RoomAccessService($session,$groups,$grants,$organizationGroups,$foreignGuard);
    $own=OCA\AdRoom\Model\Booking::get(['roomId'=>1,'userUid'=>'anna','purpose'=>'AT','title'=>'ASN Nordost','startsAt'=>'2026-07-13T08:00:00+00:00','endsAt'=>'2026-07-13T09:00:00+00:00']);
    $foreign=OCA\AdRoom\Model\Booking::get(['roomId'=>1,'userUid'=>'bea','purpose'=>'AT','title'=>'ASN West','startsAt'=>'2026-07-13T08:00:00+00:00','endsAt'=>'2026-07-13T09:00:00+00:00']);
    if ($access->canView() || $access->canManageBooking($own) || $access->canManageBooking($foreign) || $access->canManageRooms()) throw new RuntimeException('Nicht konfigurierte Konten müssen vollständig ausgeschlossen bleiben.');
    $organizationGroups->organizationMember=true;
    if (!$access->canView() || !$access->canManageBooking($own) || $access->canManageBooking($foreign) || $access->canManageRooms()) throw new RuntimeException('Eigenrechte einer Organisationskraft sind fehlerhaft.');
    if (!$access->canViewBookingTitle($own) || $access->canViewBookingTitle($foreign)) throw new RuntimeException('Der Freititel ist nicht auf die buchende Person begrenzt.');
    $organizationGroups->secretariat=true;
    if (!$access->canViewBookingTitle($foreign)) throw new RuntimeException('Das Sekretariat kann fremde Freititel nicht sehen.');
    $foreignGuard->allowed=true;
    if (!$access->canInterveneInBooking($foreign) || $access->canInterveneInBooking($own)) throw new RuntimeException('Die UI-Capability bildet den serverseitigen Fremdeingriffs-Guard nicht korrekt ab.');
    $foreignGuard->allowed=false;
    $organizationGroups->secretariat=false;
    $groups->admin=true;
    if ($access->canManageBooking($foreign) || $access->canManageRooms()) throw new RuntimeException('Native Nextcloud-Administration darf ohne app-lokale Freigabe keinen Vollzugriff erhalten.');
    $grants->active=true;
    $organizationGroups->organizationMember=false;
    if (!$access->canView() || !$access->canManageBooking($foreign) || !$access->canManageRooms()) throw new RuntimeException('Zeitweise freigegebene Nextcloud-Administration muss app-lokalen Vollzugriff erhalten.');
    if ($access->canViewBookingTitle($foreign)) throw new RuntimeException('Technischer Admin-Vollzugriff darf den Freititel nicht ohne Besitzer- oder Sekretariatsrolle offenlegen.');
    $groups->admin=false;
    if ($access->canManageBooking($foreign) || $access->canManageRooms()) throw new RuntimeException('Eine gespeicherte Freigabe darf ohne aktuellen Nextcloud-Adminstatus keinen Vollzugriff erteilen.');
    echo "AD Raumplaner access tests passed\n";
}
