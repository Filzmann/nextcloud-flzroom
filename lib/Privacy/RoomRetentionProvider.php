<?php

declare(strict_types=1);

namespace OCA\AdRoom\Privacy;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use OCA\AdRoom\AppInfo\AppId;
use OCA\AdRoom\Model\Booking;
use OCA\AdRoom\Repository\BookingRepository;
use OCA\AdRoom\Repository\TemporaryAdminAccessRepository;
use OCA\AdRoom\Service\RoomRetentionPolicyService;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionCandidate;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionPolicy;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionPreviewPage;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionPreviewRequest;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionProvider;
use OCA\FilzmannDataProtection\PublicApi\V1\RetentionProviderDescriptor;

final class RoomRetentionProvider implements RetentionProvider {
    public const POLICY_ID = 'room_booking_review';
    public const ADMIN_HISTORY_POLICY_ID = 'temporary_admin_access_history_review';

    public function __construct(
        private BookingRepository $bookings,
        private TemporaryAdminAccessRepository $adminHistory,
        private RoomRetentionPolicyService $policy,
    ) {}

    public function descriptor(): RetentionProviderDescriptor {
        return new RetentionProviderDescriptor(AppId::VALUE, 'AD Raumplaner', '1.0', 200);
    }

    public function policies(): array {
        $policy = $this->policy->policy();
        $version = '1.' . $policy['revision'];
        return [
            new RetentionPolicy(
                self::POLICY_ID,
                'Raumbuchungen',
                'Administrative Prüfung beendeter Raumbuchungen nach der app-eigenen Vorschaufrist',
                'COMPLETED_AT',
                $policy['durationPeriod'],
                'REVIEW',
                $version,
            ),
            new RetentionPolicy(
                self::ADMIN_HISTORY_POLICY_ID,
                'Adminfreigabehistorie',
                'Prüfung beendeter app-lokaler Adminfreigaben',
                'COMPLETED_AT',
                $policy['adminHistoryDurationPeriod'],
                'REVIEW',
                $version,
            ),
        ];
    }

    public function isEnabled(): bool { return $this->policy->policy()['enabled']; }

    public function preview(RetentionPreviewRequest $request): RetentionPreviewPage {
        $policy = $this->policy->policy();
        if (!$policy['enabled']) return new RetentionPreviewPage('not_applicable');
        if ($request->policyId() === self::ADMIN_HISTORY_POLICY_ID) {
            return $this->previewAdminHistory($request, $policy['adminHistoryDurationPeriod']);
        }
        if ($request->policyId() !== self::POLICY_ID) return new RetentionPreviewPage('not_applicable');
        $offset = $this->offset($request->cursor());
        $cutoff = (new DateTimeImmutable($request->evaluatedAt()))->sub(new DateInterval($policy['durationPeriod']));
        $rows = $this->bookings->findEndedBefore($cutoff, $request->limit() + 1, $offset);
        $hasMore = count($rows) > $request->limit();
        if ($hasMore) array_pop($rows);
        $candidates = array_map(
            static fn(Booking $booking): RetentionCandidate => new RetentionCandidate(
                self::POLICY_ID,
                'booking:' . $booking->id(),
                $booking->endsAt()->format(DATE_ATOM),
                'REVIEW',
                sprintf('Buchung endete vor dem administrativ konfigurierten REVIEW-Stichtag (%s).', $policy['durationPeriod']),
            ),
            $rows,
        );
        return new RetentionPreviewPage($hasMore ? 'partial' : 'complete', $candidates, [], $hasMore ? (string)($offset + $request->limit()) : null);
    }

    private function previewAdminHistory(RetentionPreviewRequest $request, string $durationPeriod): RetentionPreviewPage {
        $offset = $this->offset($request->cursor());
        $cutoff = (new DateTimeImmutable($request->evaluatedAt()))->sub(new DateInterval($durationPeriod));
        $rows = $this->adminHistory->endedBefore($cutoff, $request->limit() + 1, $offset);
        $hasMore = count($rows) > $request->limit();
        if ($hasMore) array_pop($rows);
        $candidates = array_map(
            static function (array $grant): RetentionCandidate {
                $actualEnd = $grant['revokedAt'] !== null && $grant['revokedAt'] < $grant['endsAt']
                    ? $grant['revokedAt']
                    : $grant['endsAt'];
                return new RetentionCandidate(
                    self::ADMIN_HISTORY_POLICY_ID,
                    'admin-grant:' . $grant['id'],
                    $actualEnd->format(DATE_ATOM),
                    'REVIEW',
                    'Adminfreigabe endete vor dem konfigurierten REVIEW-Stichtag.',
                );
            },
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
