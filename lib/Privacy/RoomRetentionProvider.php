<?php

declare(strict_types=1);

namespace OCA\AdRoom\Privacy;

use DateInterval;
use DateTimeImmutable;
use OCA\AdRoom\AppInfo\AppId;
use OCA\AdRoom\Model\Booking;
use OCA\AdRoom\Repository\BookingRepository;
use OCA\AdRoom\Service\RoomRetentionPolicyService;
use OCA\LocalBase\Privacy\RetentionPreviewCandidate;
use OCA\LocalBase\Privacy\RetentionPreviewPage;
use OCA\LocalBase\Privacy\RetentionPreviewRequest;
use OCA\LocalBase\Privacy\RetentionProvider;
use OCP\AppFramework\Utility\ITimeFactory;

final class RoomRetentionProvider implements RetentionProvider {
    public function __construct(
        private BookingRepository $bookings,
        private RoomRetentionPolicyService $policy,
        private ITimeFactory $clock,
    ) {}
    public function appId(): string { return AppId::VALUE; }

    public function preview(RetentionPreviewRequest $request): RetentionPreviewPage {
        $policy = $this->policy->policy();
        if (!$policy['enabled']) return new RetentionPreviewPage([]);
        $cutoff = (new DateTimeImmutable('@' . $this->clock->getTime()))->sub(new DateInterval('P' . $policy['reviewAfterDays'] . 'D'));
        $candidates = array_map(
            static fn(Booking $booking): RetentionPreviewCandidate => new RetentionPreviewCandidate(
                'booking:' . $booking->id(),
                'booking',
                RetentionPreviewCandidate::REVIEW,
                sprintf('Buchung endete vor dem administrativ konfigurierten REVIEW-Stichtag (%d Tage).', $policy['reviewAfterDays']),
            ),
            $this->bookings->findEndedByUserUid($request->subject()->id(), $cutoff, $request->limit()),
        );
        return new RetentionPreviewPage($candidates);
    }
}
