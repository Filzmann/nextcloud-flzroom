<?php

declare(strict_types=1);

namespace OCA\AdRoom\Privacy;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use OCA\AdRoom\AppInfo\AppId;
use OCA\AdRoom\Model\Booking;
use OCA\AdRoom\Repository\BookingRepository;
use OCA\AdRoom\Service\RoomRetentionPolicyService;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionCandidate;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionPolicy;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionPreviewPage;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionPreviewRequest;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionProvider;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionProviderDescriptor;

final class RoomRetentionProvider implements RetentionProvider {
    public const POLICY_ID = 'room_booking_review';

    public function __construct(
        private BookingRepository $bookings,
        private RoomRetentionPolicyService $policy,
    ) {}

    public function descriptor(): RetentionProviderDescriptor {
        return new RetentionProviderDescriptor(AppId::VALUE, 'AD Raumplaner', '1.0', 200);
    }

    public function policies(): array {
        $policy = $this->policy->policy();
        return [new RetentionPolicy(
            self::POLICY_ID,
            'Raumbuchungen',
            'Administrative Prüfung beendeter Raumbuchungen nach der app-eigenen Vorschaufrist',
            'COMPLETED_AT',
            $policy['reviewAfterDays'],
            'REVIEW',
            '1.0',
        )];
    }

    public function isEnabled(): bool { return $this->policy->policy()['enabled']; }

    public function preview(RetentionPreviewRequest $request): RetentionPreviewPage {
        $policy = $this->policy->policy();
        if (!$policy['enabled'] || $request->policyId() !== self::POLICY_ID) return new RetentionPreviewPage('not_applicable');
        $offset = $this->offset($request->cursor());
        $cutoff = (new DateTimeImmutable($request->evaluatedAt()))->sub(new DateInterval('P' . $policy['reviewAfterDays'] . 'D'));
        $rows = $this->bookings->findEndedBefore($cutoff, $request->limit() + 1, $offset);
        $hasMore = count($rows) > $request->limit();
        if ($hasMore) array_pop($rows);
        $candidates = array_map(
            static fn(Booking $booking): RetentionCandidate => new RetentionCandidate(
                self::POLICY_ID,
                'booking:' . $booking->id(),
                $booking->endsAt()->format(DATE_ATOM),
                'REVIEW',
                sprintf('Buchung endete vor dem administrativ konfigurierten REVIEW-Stichtag (%d Tage).', $policy['reviewAfterDays']),
            ),
            $rows,
        );
        return new RetentionPreviewPage($hasMore ? 'partial' : 'complete', $candidates, [], $hasMore ? (string)($offset + $request->limit()) : null);
    }

    private function offset(?string $cursor): int {
        if ($cursor === null) return 0;
        if (!preg_match('/^(?:0|[1-9][0-9]{0,8})$/', $cursor)) throw new InvalidArgumentException('Invalid room retention cursor.');
        return (int)$cursor;
    }
}
