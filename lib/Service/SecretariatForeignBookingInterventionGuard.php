<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use OCA\FlzRoom\AppInfo\Application;
use OCA\FlzRoom\Model\Booking;
use OCA\FlzDataProtection\PublicApi\V1\ScopeAuthorizationQueryEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

/** Server-side guard for the future, separately audited foreign-booking workflow. */
final class SecretariatForeignBookingInterventionGuard {
    public function __construct(
        private IUserSession $session,
        private OrganizationGroupPolicyService $organizationGroups,
        private IEventDispatcher $events,
        private LoggerInterface $logger,
    ) {
    }

    public function allows(Booking $booking): bool {
        return $this->authorizedActorUid($booking) !== null;
    }

    public function authorizedActorUid(Booking $booking): ?string {
        $uid = $this->session->getUser()?->getUID() ?? '';
        if ($uid === '' || hash_equals($booking->userUid(), $uid)) {
            return null;
        }
        try {
            if (!$this->organizationGroups->isSecretariat($uid)
                || !class_exists(ScopeAuthorizationQueryEvent::class)) {
                return null;
            }

            $event = new ScopeAuthorizationQueryEvent(
                Application::APP_ID,
                ScopeAuthorizationQueryEvent::FLZROOM_SECRETARIAT_FOREIGN_BOOKING_INTERVENTION,
                ScopeAuthorizationQueryEvent::CONTRACT_VERSION,
            );
            $this->events->dispatchTyped($event);
            return $event->isAuthorized() ? $uid : null;
        } catch (Throwable $error) {
            $this->logger->warning('Risikoscope für einen Sekretariatseingriff konnte nicht geprüft werden.', [
                'scope' => ScopeAuthorizationQueryEvent::FLZROOM_SECRETARIAT_FOREIGN_BOOKING_INTERVENTION,
                'exception' => $error,
            ]);
            return null;
        }
    }
}
