<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    class Event { public function __construct() {} }
    interface IEventDispatcher { public function dispatchTyped(Event $event): Event; }
}

namespace OCP {
    interface IUser { public function getUID(): string; }
    interface IUserSession { public function getUser(): ?IUser; }
}

namespace Psr\Log {
    interface LoggerInterface { public function warning(string $message, array $context = []): void; }
}

namespace OCA\FlzRoom\AppInfo {
    final class Application { public const APP_ID = 'flzroom'; }
}

namespace OCA\FlzRoom\Service {
    final class OrganizationGroupPolicyService {
        public bool $secretariat = false;
        public function isSecretariat(string $uid): bool { return $this->secretariat && $uid === 'secretariat-user'; }
    }
}

namespace {
    use OCA\FlzRoom\Model\Booking;
    use OCA\FlzRoom\Service\OrganizationGroupPolicyService;
    use OCA\FlzRoom\Service\SecretariatForeignBookingInterventionGuard;
    use OCA\FlzDataProtection\PublicApi\V1\ScopeAuthorizationQueryEvent;
    use OCP\EventDispatcher\Event;
    use OCP\EventDispatcher\IEventDispatcher;
    use OCP\IUser;
    use OCP\IUserSession;

    $assertSame = static function (mixed $expected, mixed $actual, string $message): void {
        if ($expected !== $actual) throw new RuntimeException($message);
    };

    $session = new class implements IUserSession {
        public string $uid = 'secretariat-user';
        public function getUser(): ?IUser {
            return new class($this->uid) implements IUser {
                public function __construct(private string $uid) {}
                public function getUID(): string { return $this->uid; }
            };
        }
    };
    $groups = new OrganizationGroupPolicyService();
    $events = new class implements IEventDispatcher {
        public string $mode = 'unanswered';
        public int $calls = 0;
        public function dispatchTyped(Event $event): Event {
            $this->calls++;
            if (!$event instanceof ScopeAuthorizationQueryEvent) return $event;
            if ($this->mode === 'authorized') $event->respond(true, ScopeAuthorizationQueryEvent::CONTRACT_VERSION);
            if ($this->mode === 'denied') $event->respond(false, ScopeAuthorizationQueryEvent::CONTRACT_VERSION);
            if ($this->mode === 'incompatible') $event->respond(true, '2.0');
            if ($this->mode === 'failure') throw new RuntimeException('synthetic provider failure');
            return $event;
        }
    };
    $logger = new class implements \Psr\Log\LoggerInterface {
        public array $warnings = [];
        public function warning(string $message, array $context = []): void { $this->warnings[] = [$message, $context]; }
    };
    $guard = new SecretariatForeignBookingInterventionGuard($session, $groups, $events, $logger);
    $foreign = Booking::get([
        'id' => 17,
        'roomId' => 4,
        'userUid' => 'booking-owner',
        'purpose' => 'Besprechung',
        'title' => 'Interne Abstimmung',
        'startsAt' => '2026-10-05T08:00:00+00:00',
        'endsAt' => '2026-10-05T09:00:00+00:00',
    ]);
    $own = Booking::get([
        'id' => 18,
        'roomId' => 4,
        'userUid' => 'secretariat-user',
        'purpose' => 'Besprechung',
        'title' => 'Interne Abstimmung',
        'startsAt' => '2026-10-05T09:00:00+00:00',
        'endsAt' => '2026-10-05T10:00:00+00:00',
    ]);

    $assertSame(false, $guard->allows($foreign), 'Ohne Sekretariatsrolle wurde der Fremdeingriff freigegeben.');
    $assertSame(0, $events->calls, 'Ohne Fachrolle wurde unnötig eine Scope-Entscheidung abgefragt.');

    $groups->secretariat = true;
    $assertSame(false, $guard->allows($own), 'Der Fremdeingriffs-Guard darf nicht für eigene Buchungen verwendet werden.');
    $assertSame(0, $events->calls, 'Für eine eigene Buchung wurde unnötig eine Scope-Entscheidung abgefragt.');

    $assertSame(false, $guard->allows($foreign), 'Ein fehlender oder deaktivierter Provider wurde nicht fail-closed behandelt.');
    $events->mode = 'denied';
    $assertSame(false, $guard->allows($foreign), 'Ein verweigerter oder abgelaufener Scope wurde freigegeben.');
    $events->mode = 'incompatible';
    $assertSame(false, $guard->allows($foreign), 'Ein inkompatibler Provider wurde freigegeben.');
    $events->mode = 'failure';
    $assertSame(false, $guard->allows($foreign), 'Ein fehlerhafter Provider wurde nicht fail-closed behandelt.');
    $assertSame(1, count($logger->warnings), 'Ein Providerfehler bleibt nicht datensparsam diagnostizierbar: ' . var_export($logger->warnings, true));
    $events->mode = 'authorized';
    $assertSame(true, $guard->allows($foreign), 'Fachrolle und wirksamer Scope wurden nicht gemeinsam freigegeben.');

    $session->uid = '';
    $assertSame(false, $guard->allows($foreign), 'Eine leere Session-UID wurde freigegeben.');

    echo "Filzmann Raumplaner risk-scope consumer contract passed\n";
}
