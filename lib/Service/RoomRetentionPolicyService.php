<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use DateInterval;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use OCA\FlzRoom\AppInfo\AppId;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use Throwable;

/** Canonical versioned policy for booking review and app-local retention. */
final class RoomRetentionPolicyService
{
    public const CONFIGURATOR_GROUP = 'Datenschutzbeauftragte';
    private const KEY = 'retention_policy_history_v1';
    private const DEFAULT_PERIOD = 'P1Y';
    private const DEFAULT_ADMIN_HISTORY_PERIOD = 'P6M';
    private const DEFAULT_INTERVENTION_AUDIT_PERIOD = 'P1Y';
    private const DEFAULT_FAILED_NOTIFICATION_PERIOD = 'P30D';

    public function __construct(
        private IAppConfig $config,
        private IGroupManager $groups,
        private IUserSession $session,
        private ITimeFactory $clock,
        private ILockingProvider $locking,
    )
    {
    }

    public function canConfigure(): bool
    {
        $uid = $this->session->getUser()?->getUID() ?? '';
        if ($uid === '')
        {
            return false;
        }
        try
        {
            return $this->groups->isInGroup($uid, self::CONFIGURATOR_GROUP);
        } catch (Throwable)
        {
            return false;
        }
    }

    public function policy(): array
    {
        $history = $this->history();
        $entry = $history === [] ? [] : $history[array_key_last($history)];
        return array_merge($this->defaults(), $entry, [
            'enabled' => true,
            'action' => 'REVIEW',
            'reviewDue' => $this->reviewDue(),
        ]);
    }

    /** Booking revisions remain bound to their original retention trigger. */
    public function policyFor(DateTimeImmutable $triggerAt): array
    {
        $selected = $this->defaults();
        foreach ($this->history() as $entry)
        {
            if ($entry['event'] !== 'configured' || !is_string($entry['effectiveAt']))
            {
                continue;
            }
            if (new DateTimeImmutable($entry['effectiveAt']) <= $triggerAt)
            {
                $selected = $entry;
            }
        }
        return $selected;
    }

    public function save(array $policy): array
    {
        $uid = $this->requireConfigurator();
        return $this->withWriteLock(function () use ($uid, $policy): array
        {
            $history = $this->history();
            $current = $history === [] ? $this->defaults() : $history[array_key_last($history)];
            $period = $this->validatePeriod($policy['durationPeriod'] ?? null);
            $adminPeriod = $this->validatePeriod($policy['adminHistoryDurationPeriod'] ?? null);
            if ($adminPeriod !== self::DEFAULT_ADMIN_HISTORY_PERIOD)
            {
                throw new InvalidArgumentException('Die Adminfreigabehistorie hat die feste Frist P6M.');
            }
            $auditPeriod = $this->validateAuditPeriod(array_key_exists('interventionAuditDurationPeriod', $policy)
                ? $policy['interventionAuditDurationPeriod'] : $current['interventionAuditDurationPeriod']);
            $failurePeriod = $this->validateFailurePeriod(array_key_exists('failedNotificationDurationPeriod', $policy)
                ? $policy['failedNotificationDurationPeriod'] : $current['failedNotificationDurationPeriod']);
            $expected = $this->validateExpectedRevision($policy['expectedRevision'] ?? null);
            $this->assertRevision($history, $expected);
            $now = $this->clock->now()->format(DATE_ATOM);
            $history[] = [
                'revision' => $expected + 1,
                'event' => 'configured',
                'durationPeriod' => $period,
                'adminHistoryDurationPeriod' => $adminPeriod,
                'interventionAuditDurationPeriod' => $auditPeriod,
                'failedNotificationDurationPeriod' => $failurePeriod,
                'effectiveAt' => $now,
                'reviewedAt' => $now,
                'changedBy' => $uid,
            ];
            $this->persist($history);
            return $this->policy();
        });
    }

    public function recordReview(int $expectedRevision): array
    {
        $uid = $this->requireConfigurator();
        return $this->withWriteLock(function () use ($uid, $expectedRevision): array
        {
            $history = $this->history();
            $this->assertRevision($history, $expectedRevision);
            $current = $history === [] ? $this->defaults() : $history[array_key_last($history)];
            $history[] = array_merge($current, [
                'revision' => $expectedRevision + 1,
                'event' => 'reviewed',
                'reviewedAt' => $this->clock->now()->format(DATE_ATOM),
                'changedBy' => $uid,
            ]);
            $this->persist($history);
            return $history[array_key_last($history)];
        });
    }

    private function withWriteLock(callable $write): array
    {
        $key = AppId::VALUE . ':retention-policy';
        $this->locking->acquireLock($key, ILockingProvider::LOCK_EXCLUSIVE);
        try
        {
            return $write();
        } finally
        {
            $this->locking->releaseLock($key, ILockingProvider::LOCK_EXCLUSIVE);
        }
    }

    public function history(): array
    {
        $encoded = $this->config->getValueString(AppId::VALUE, self::KEY, '');
        if ($encoded === '')
        {
            return [];
        }
        try
        {
            $history = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($history))
            {
                throw new InvalidArgumentException();
            }
            $validated = [];
            foreach ($history as $index => $entry)
            {
                if (!is_array($entry) || ($entry['revision'] ?? null) !== $index + 1
                    || !in_array($entry['event'] ?? null, ['configured', 'reviewed'], true)
                    || !is_string($entry['changedBy'] ?? null) || trim($entry['changedBy']) === '')
                    {
                    throw new InvalidArgumentException();
                }
                $entry['durationPeriod'] = $this->validatePeriod($entry['durationPeriod'] ?? null);
                $entry['adminHistoryDurationPeriod'] = $this->validatePeriod($entry['adminHistoryDurationPeriod'] ?? null);
                $entry['interventionAuditDurationPeriod'] = $this->validateAuditPeriod(array_key_exists('interventionAuditDurationPeriod', $entry) ? $entry['interventionAuditDurationPeriod'] : self::DEFAULT_INTERVENTION_AUDIT_PERIOD);
                $entry['failedNotificationDurationPeriod'] = $this->validateFailurePeriod(array_key_exists('failedNotificationDurationPeriod', $entry) ? $entry['failedNotificationDurationPeriod'] : self::DEFAULT_FAILED_NOTIFICATION_PERIOD);
                foreach (['effectiveAt', 'reviewedAt'] as $field)
                {
                    if ($entry[$field] !== null)
                    {
                        new DateTimeImmutable((string)$entry[$field]);
                    }
                }
                $validated[] = $entry;
            }
            return $validated;
        } catch (Throwable $error)
        {
            throw new DomainException('Die gespeicherte Retention-Policyhistorie ist beschädigt.', 0, $error);
        }
    }

    public function reviewDue(): bool
    {
        $history = $this->history();
        if ($history === [])
        {
            return true;
        }
        $latest = $history[array_key_last($history)];
        $baseline = $latest['reviewedAt'] ?? $latest['effectiveAt'] ?? null;
        return is_string($baseline) && $this->clock->now() >= (new DateTimeImmutable($baseline))->add(new DateInterval('P1Y'));
    }

    public function retentionCriteria(): string
    {
        return sprintf('REVIEW %s nach Ende der Buchung; keine automatische Löschung oder Anonymisierung.', $this->policy()['durationPeriod']);
    }

    private function defaults(): array
    {
        return [
            'revision' => 0,
            'durationPeriod' => self::DEFAULT_PERIOD,
            'adminHistoryDurationPeriod' => self::DEFAULT_ADMIN_HISTORY_PERIOD,
            'interventionAuditDurationPeriod' => self::DEFAULT_INTERVENTION_AUDIT_PERIOD,
            'failedNotificationDurationPeriod' => self::DEFAULT_FAILED_NOTIFICATION_PERIOD,
            'effectiveAt' => null,
            'reviewedAt' => null,
            'changedBy' => null,
        ];
    }

    private function requireConfigurator(): string
    {
        $uid = $this->session->getUser()?->getUID() ?? '';
        if ($uid === '' || !$this->canConfigure())
        {
            throw new DomainException('Zugriff verweigert.');
        }
        return $uid;
    }

    private function validatePeriod(mixed $period): string
    {
        if (!is_string($period) || !preg_match('/^P([1-9][0-9]{0,5})([YMD])$/', $period))
        {
            throw new InvalidArgumentException('Retention-Frist ist ungültig.');
        }
        try
        {
            $start = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
            $days = $start->diff($start->add(new DateInterval($period)))->days;
        } catch (Throwable)
        {
            $days = false;
        }
        if ($days === false || $days < 30 || $days > 1096)
        {
            throw new InvalidArgumentException('Retention-Frist muss zwischen 30 Tagen und 3 Jahren liegen.');
        }
        return $period;
    }

    private function validateAuditPeriod(mixed $period): string
    {
        if (!is_string($period) || !preg_match('/^P([1-9][0-9]{0,3})([YD])$/', $period, $matches))
        {
            throw new InvalidArgumentException('Die Auditfrist benötigt ganze Tage oder Kalenderjahre.');
        }
        $amount = (int)$matches[1];
        if (($matches[2] === 'Y' && $amount <= 3)
            || ($matches[2] === 'D' && $amount >= 30 && $amount <= 1095))
            {
            return $period;
        }
        throw new InvalidArgumentException('Die Auditfrist muss zwischen 30 Tagen und drei Kalenderjahren liegen.');
    }

    private function validateFailurePeriod(mixed $period): string
    {
        if (is_string($period) && preg_match('/^P([1-9][0-9]{0,2})D$/', $period, $matches)
            && (int)$matches[1] <= 365)
            {
            return $period;
        }
        throw new InvalidArgumentException('Die Fehlerfrist muss zwischen 1 und 365 ganzen Tagen liegen.');
    }

    private function validateExpectedRevision(mixed $revision): int
    {
        if (!is_int($revision) || $revision < 0)
        {
            throw new InvalidArgumentException('Policyversion ist ungültig.');
        }
        return $revision;
    }

    private function assertRevision(array $history, int $expected): void
    {
        $actual = $history === [] ? 0 : (int)$history[array_key_last($history)]['revision'];
        if ($actual !== $expected)
        {
            throw new DomainException('Policy wurde zwischenzeitlich geändert.');
        }
    }

    private function persist(array $history): void
    {
        $this->config->setValueString(AppId::VALUE, self::KEY, json_encode($history, JSON_THROW_ON_ERROR));
    }
}
