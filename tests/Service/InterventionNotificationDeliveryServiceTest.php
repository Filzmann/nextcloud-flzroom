<?php

declare(strict_types=1);

namespace OCP\AppFramework\Utility {
    interface ITimeFactory { public function now(): \DateTimeImmutable; }
}

namespace OCP\Notification {
    interface INotification {
        public function setApp(string $app): self;
        public function setUser(string $user): self;
        public function setDateTime(\DateTime $dateTime): self;
        public function setObject(string $type, string $id): self;
        public function setSubject(string $subject, array $parameters = []): self;
    }
    interface IManager {
        public function createNotification(): INotification;
        public function notify(INotification $notification): void;
    }
}

namespace Psr\Log {
    interface LoggerInterface { public function warning(string $message, array $context = []): void; }
}

namespace OCA\AdRoom\Repository {
    final class InterventionNotificationQueueRepository {
        public ?array $row = null;
        public array $events = [];
        public function find(int $id): ?array { return $this->row !== null && $this->row['id'] === $id ? $this->row : null; }
        public function remove(int $id): void { $this->events[] = ['remove', $id]; }
        public function scheduleRetry(int $id, int $attemptCount, \DateTimeImmutable $nextAttemptAt, \DateTimeImmutable $now, string $errorCode): void { $this->events[] = ['retry', $id, $attemptCount, $nextAttemptAt, $errorCode]; }
        public function markPermanentlyFailed(int $id, int $attemptCount, \DateTimeImmutable $failedAt, string $errorCode): void { $this->events[] = ['failed', $id, $attemptCount, $failedAt, $errorCode]; }
    }
}

namespace {
    use OCA\AdRoom\Repository\InterventionNotificationQueueRepository;
    use OCA\AdRoom\Service\InterventionNotificationDeliveryService;
    use OCP\AppFramework\Utility\ITimeFactory;
    use OCP\Notification\IManager;
    use OCP\Notification\INotification;

    $assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
    $now = new DateTimeImmutable('2026-09-29T10:00:00+00:00');
    $time = new class($now) implements ITimeFactory {
        public function __construct(private DateTimeImmutable $now) {}
        public function now(): DateTimeImmutable { return $this->now; }
    };
    $notification = new class implements INotification {
        public array $data = [];
        public function setApp(string $app): self { $this->data['app'] = $app; return $this; }
        public function setUser(string $user): self { $this->data['user'] = $user; return $this; }
        public function setDateTime(DateTime $dateTime): self { $this->data['dateTime'] = $dateTime; return $this; }
        public function setObject(string $type, string $id): self { $this->data['object'] = [$type, $id]; return $this; }
        public function setSubject(string $subject, array $parameters = []): self { $this->data['subject'] = [$subject, $parameters]; return $this; }
    };
    $manager = new class($notification) implements IManager {
        public bool $fail = false;
        public int $notifications = 0;
        public function __construct(private INotification $notification) {}
        public function createNotification(): INotification { return $this->notification; }
        public function notify(INotification $notification): void { $this->notifications++; if ($this->fail) throw new RuntimeException('synthetic core notification failure'); }
    };
    $logger = new class implements \Psr\Log\LoggerInterface {
        public array $warnings = [];
        public function warning(string $message, array $context = []): void { $this->warnings[] = [$message, $context]; }
    };
    $queue = new InterventionNotificationQueueRepository();
    $base = [
        'id' => 41, 'recipientUid' => 'booking-owner', 'action' => 'update', 'bookingId' => 17,
        'oldRoom' => 'Raum 4', 'newRoom' => 'Raum 8',
        'oldStartsAt' => new DateTimeImmutable('2026-10-05T08:00:00+00:00'), 'oldEndsAt' => new DateTimeImmutable('2026-10-05T09:00:00+00:00'),
        'newStartsAt' => new DateTimeImmutable('2026-10-05T10:00:00+00:00'), 'newEndsAt' => new DateTimeImmutable('2026-10-05T11:00:00+00:00'),
        'reason' => 'Raumkonflikt wurde organisatorisch abgestimmt.', 'attemptCount' => 0, 'state' => 'pending',
    ];
    $service = new InterventionNotificationDeliveryService($queue, $manager, $time, $logger);

    $queue->row = $base;
    $service->deliverById(41);
    $assert($queue->events === [['remove', 41]], 'Erfolgreich zugestellte Queuezeile wurde nicht sofort entfernt.');
    $assert($notification->data['app'] === 'adroom' && $notification->data['user'] === 'booking-owner', 'Native Benachrichtigung ist falsch adressiert.');
    $parameters = $notification->data['subject'][1];
    $assert(!isset($parameters['title'], $parameters['purpose'], $parameters['actorUid']), 'Benachrichtigung enthält verbotene Buchungs- oder Personendaten.');
    $assert($parameters['oldRoom'] === 'Raum 4' && $parameters['newRoom'] === 'Raum 8' && $parameters['reason'] === $base['reason'], 'Erforderlicher Benachrichtigungsinhalt fehlt.');

    $manager->fail = true;
    foreach ([0 => 'PT5M', 1 => 'PT1H', 2 => 'P1D'] as $attemptCount => $delay) {
        $queue->events = [];
        $queue->row = [...$base, 'attemptCount' => $attemptCount];
        $service->deliverById(41);
        $event = $queue->events[0] ?? null;
        $assert($event[0] === 'retry' && $event[2] === $attemptCount + 1, "Retryzähler für Versuch {$attemptCount} ist falsch.");
        $assert($event[3] == $now->add(new DateInterval($delay)), "Retryabstand {$delay} ist falsch.");
    }
    $queue->events = [];
    $queue->row = [...$base, 'attemptCount' => 3];
    $service->deliverById(41);
    $assert(($queue->events[0][0] ?? null) === 'failed' && $queue->events[0][2] === 4, 'Nach dem Retry bei 24 Stunden wurde kein dauerhafter Fehler markiert.');
    $assert(count($logger->warnings) === 4, 'Zustellfehler bleiben nicht minimal diagnostizierbar.');
    foreach ($logger->warnings as [, $context]) {
        $assert(!isset($context['recipientUid'], $context['reason'], $context['title']), 'Log enthält unnötige personenbezogene Inhalte.');
    }

    echo "Intervention notification delivery tests passed\n";
}
