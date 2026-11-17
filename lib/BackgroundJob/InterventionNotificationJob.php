<?php

declare(strict_types=1);

namespace OCA\FlzRoom\BackgroundJob;

use DateInterval;
use OCA\FlzRoom\Repository\BookingInterventionAuditRepository;
use OCA\FlzRoom\Repository\InterventionNotificationQueueRepository;
use OCA\FlzRoom\Service\InterventionNotificationDeliveryService;
use OCA\FlzRoom\Service\RoomRetentionPolicyService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/** Delivers due notifications and applies the current DPO retention policy to all records. */
final class InterventionNotificationJob extends TimedJob
{
    private ITimeFactory $clock;

    public function __construct(
        ITimeFactory $time,
        private InterventionNotificationQueueRepository $queue,
        private BookingInterventionAuditRepository $audit,
        private InterventionNotificationDeliveryService $delivery,
        private RoomRetentionPolicyService $policy,
    )
    {
        parent::__construct($time);
        $this->clock = $time;
        $this->setInterval(60);
    }

    protected function run($argument): void
    {
        $now = $this->clock->now();
        foreach ($this->queue->due($now, 25) as $entry) {
            $this->delivery->deliverById($entry['id']);
        }
        $policy = $this->policy->policy();
        $this->audit->deleteOlderThan($now->sub(new DateInterval($policy['interventionAuditDurationPeriod'])));
        $this->queue->deletePermanentlyFailedBefore($now->sub(new DateInterval($policy['failedNotificationDurationPeriod'])));
    }
}
