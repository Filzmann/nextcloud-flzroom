<?php

declare(strict_types=1);

namespace OCA\AdRoom\Service;

use DateInterval;
use DateTime;
use OCA\AdRoom\AppInfo\AppId;
use OCA\AdRoom\Repository\InterventionNotificationQueueRepository;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;
use Throwable;

/** Delivers one persisted outbox record and applies the fixed 5m/1h/24h retry contract. */
final class InterventionNotificationDeliveryService {
    private const RETRY_DELAYS = ['PT5M', 'PT1H', 'P1D'];
    private const ERROR_CODE = 'notification_delivery_failed';

    public function __construct(
        private InterventionNotificationQueueRepository $queue,
        private IManager $notifications,
        private ITimeFactory $time,
        private LoggerInterface $logger,
    ) {
    }

    public function deliverById(int $id): void {
        $entry = $this->queue->find($id);
        if ($entry === null || $entry['state'] !== 'pending') return;
        $now = $this->time->now();
        try {
            $notification = $this->notifications->createNotification();
            $notification
                ->setApp(AppId::VALUE)
                ->setUser($entry['recipientUid'])
                ->setDateTime(new DateTime($now->format(DATE_ATOM)))
                ->setObject('booking-intervention', (string)$entry['id'])
                ->setSubject('foreign_booking_intervention', [
                    'action' => $entry['action'],
                    'oldRoom' => $entry['oldRoom'],
                    'newRoom' => $entry['newRoom'],
                    'oldStartsAt' => $entry['oldStartsAt']->format(DATE_ATOM),
                    'oldEndsAt' => $entry['oldEndsAt']->format(DATE_ATOM),
                    'newStartsAt' => $entry['newStartsAt']?->format(DATE_ATOM),
                    'newEndsAt' => $entry['newEndsAt']?->format(DATE_ATOM),
                    'reason' => $entry['reason'],
                ]);
            $this->notifications->notify($notification);
            $this->queue->remove($id);
        } catch (Throwable $error) {
            $attemptCount = (int)$entry['attemptCount'];
            $nextAttemptCount = $attemptCount + 1;
            if (isset(self::RETRY_DELAYS[$attemptCount])) {
                $this->queue->scheduleRetry(
                    $id,
                    $nextAttemptCount,
                    $now->add(new DateInterval(self::RETRY_DELAYS[$attemptCount])),
                    $now,
                    self::ERROR_CODE,
                );
            } else {
                $this->queue->markPermanentlyFailed($id, $nextAttemptCount, $now, self::ERROR_CODE);
            }
            $this->logger->warning('Native Benachrichtigung zum Sekretariatseingriff konnte nicht zugestellt werden.', [
                'attempt' => $nextAttemptCount,
                'permanent' => !isset(self::RETRY_DELAYS[$attemptCount]),
                'errorCode' => self::ERROR_CODE,
                'exception' => $error,
            ]);
        }
    }
}
