<?php

declare(strict_types=1);

namespace OCA\AdRoom\BackgroundJob;

use DateInterval;
use OCA\AdRoom\Repository\BookingInterventionAuditRepository;
use OCA\AdRoom\Repository\InterventionNotificationQueueRepository;
use OCA\AdRoom\Service\InterventionNotificationDeliveryService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/** Delivers due notifications and enforces the fixed 12-month/30-day retention limits. */
final class InterventionNotificationJob extends TimedJob {
    private ITimeFactory $clock;

    public function __construct(
        ITimeFactory $time,
        private InterventionNotificationQueueRepository $queue,
        private BookingInterventionAuditRepository $audit,
        private InterventionNotificationDeliveryService $delivery,
    ) {
        parent::__construct($time);
        $this->clock = $time;
        $this->setInterval(60);
    }

    protected function run($argument): void {
        $now = $this->clock->now();
        foreach ($this->queue->due($now, 25) as $entry) {
            $this->delivery->deliverById($entry['id']);
        }
        $this->audit->deleteOlderThan($now->sub(new DateInterval('P1Y')));
        $this->queue->deletePermanentlyFailedBefore($now->sub(new DateInterval('P30D')));
    }
}
