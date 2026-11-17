<?php

declare(strict_types=1);

namespace OCP {
    interface IAppConfig {
        public function getValueString(string $appId, string $key, string $default = ''): string;
        public function setValueString(string $appId, string $key, string $value): void;
    }
    interface IUser { public function getUID(): string; }
    interface IUserSession { public function getUser(): ?IUser; }
    interface IGroupManager { public function isInGroup(string $uid, string $gid): bool; }
}
namespace OCP\AppFramework\Utility { interface ITimeFactory { public function now(): \DateTimeImmutable; } }
namespace OCP\Lock {
    interface ILockingProvider {
        public const LOCK_EXCLUSIVE = 2;
        public function acquireLock(string $path, int $type): void;
        public function releaseLock(string $path, int $type): void;
    }
}

namespace {
    use OCA\FlzRoom\Service\RoomRetentionPolicyService;

    $config = new class implements OCP\IAppConfig {
        public array $values = [];
        public function getValueString(string $appId, string $key, string $default = ''): string { return $this->values[$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->values[$key] = $value; }
    };
    $groups = new class implements OCP\IGroupManager {
        public array $members = ['dpo'];
        public function isInGroup(string $uid, string $gid): bool { return $gid === 'Datenschutzbeauftragte' && in_array($uid, $this->members, true); }
    };
    $session = new class implements OCP\IUserSession {
        public string $uid = 'dpo';
        public function getUser(): ?OCP\IUser { return new class($this->uid) implements OCP\IUser { public function __construct(private string $uid) {} public function getUID(): string { return $this->uid; } }; }
    };
    $clock = new class implements OCP\AppFramework\Utility\ITimeFactory {
        public DateTimeImmutable $value;
        public function __construct() { $this->value = new DateTimeImmutable('2026-09-25T10:00:00+00:00'); }
        public function now(): DateTimeImmutable { return $this->value; }
    };

    $locking = new class implements OCP\Lock\ILockingProvider {
        public int $acquired = 0;
        public int $released = 0;
        public function acquireLock(string $path, int $type): void { $this->acquired++; }
        public function releaseLock(string $path, int $type): void { $this->released++; }
    };
    $service = new RoomRetentionPolicyService($config, $groups, $session, $clock, $locking);
    $default = $service->policy();
    if ($default['durationPeriod'] !== 'P1Y'
        || $default['adminHistoryDurationPeriod'] !== 'P6M'
        || ($default['interventionAuditDurationPeriod'] ?? null) !== 'P1Y'
        || ($default['failedNotificationDurationPeriod'] ?? null) !== 'P30D'
        || $default['action'] !== 'REVIEW'
        || $default['revision'] !== 0
        || !$default['reviewDue']) {
        throw new RuntimeException('Die beschlossene Jahresfrist ist nicht der sichere versionierte Standard.');
    }
    if (!$service->canConfigure()) throw new RuntimeException('Datenschutzbeauftragte können die Policy nicht konfigurieren.');

    $saved = $service->save([
        'durationPeriod' => 'P18M',
        'adminHistoryDurationPeriod' => 'P6M',
        'interventionAuditDurationPeriod' => 'P90D',
        'failedNotificationDurationPeriod' => 'P7D',
        'expectedRevision' => 0,
    ]);
    if ($saved['revision'] !== 1 || $saved['durationPeriod'] !== 'P18M' || $saved['adminHistoryDurationPeriod'] !== 'P6M' || $saved['changedBy'] !== 'dpo') {
        throw new RuntimeException('Eine gültige Policyänderung wird nicht versioniert und akteursgebunden gespeichert.');
    }
    if (count($service->history()) !== 1 || $service->reviewDue()) {
        throw new RuntimeException('Policyhistorie oder jährlicher Reviewstatus ist falsch.');
    }
    if ($locking->acquired !== 1 || $locking->released !== 1) {
        throw new RuntimeException('Die gemeinsame Policyrevision wird nicht gegen gleichzeitige Änderungen gesperrt.');
    }
    if ($saved['interventionAuditDurationPeriod'] !== 'P90D'
        || $saved['failedNotificationDurationPeriod'] !== 'P7D') {
        throw new RuntimeException('Die bearbeitbaren Eingriffsfristen werden nicht gespeichert.');
    }

    $before = $config->values;
    foreach ([
        ['P29D', 'P7D'],
        ['P4Y', 'P7D'],
        ['P1096D', 'P7D'],
        ['P90D', 'P0D'],
        ['P90D', 'P366D'],
        ['P90D', 'unbegrenzt'],
        [null, 'P7D'],
    ] as [$auditPeriod, $failurePeriod]) {
        try {
            $service->save([
                'durationPeriod' => 'P18M',
                'adminHistoryDurationPeriod' => 'P6M',
                'interventionAuditDurationPeriod' => $auditPeriod,
                'failedNotificationDurationPeriod' => $failurePeriod,
                'expectedRevision' => 1,
            ]);
            throw new RuntimeException('Eine unzulässige Eingriffsfrist wurde angenommen.');
        } catch (InvalidArgumentException) {
        }
        if ($config->values !== $before) {
            throw new RuntimeException('Eine ungültige Frist hat die Policyhistorie verändert.');
        }
    }

    $before = $config->values;
    $session->uid = 'ordinary';
    foreach ([
        ['durationPeriod' => 'P2Y', 'adminHistoryDurationPeriod' => 'P6M', 'expectedRevision' => 1],
        ['durationPeriod' => 'P2Y', 'adminHistoryDurationPeriod' => 'P6M', 'expectedRevision' => 0],
        ['durationPeriod' => 'P0D', 'adminHistoryDurationPeriod' => 'P6M', 'expectedRevision' => 1],
        ['durationPeriod' => 'P2Y', 'adminHistoryDurationPeriod' => 'P0D', 'expectedRevision' => 1],
    ] as $attempt) {
        try { $service->save($attempt); throw new RuntimeException('Unzulässige Policyänderung wurde akzeptiert.'); }
        catch (DomainException|InvalidArgumentException) {}
        if ($config->values !== $before) throw new RuntimeException('Abgewiesene Policyänderung hat Nebenwirkungen.');
    }

    $session->uid = 'dpo';
    $clock->value = new DateTimeImmutable('2027-09-26T10:00:00+00:00');
    if (!$service->reviewDue()) throw new RuntimeException('Der fällige jährliche Review bleibt unsichtbar.');
    $reviewed = $service->recordReview(1);
    if ($reviewed['revision'] !== 2 || $reviewed['event'] !== 'reviewed' || $service->reviewDue()) {
        throw new RuntimeException('Der jährliche Review wird nicht versioniert protokolliert.');
    }
    if ($service->policy()['interventionAuditDurationPeriod'] !== 'P90D'
        || $service->policy()['failedNotificationDurationPeriod'] !== 'P7D') {
        throw new RuntimeException('Eine jährliche Prüfung verändert die Eingriffsfristen.');
    }
    foreach ([['P30D', 'P1D'], ['P3Y', 'P365D'], ['P1095D', 'P30D']] as [$auditPeriod, $failurePeriod]) {
        $current = $service->policy();
        $service->save([
            'durationPeriod' => $current['durationPeriod'],
            'adminHistoryDurationPeriod' => 'P6M',
            'interventionAuditDurationPeriod' => $auditPeriod,
            'failedNotificationDurationPeriod' => $failurePeriod,
            'expectedRevision' => $current['revision'],
        ]);
        if ($service->policy()['interventionAuditDurationPeriod'] !== $auditPeriod
            || $service->policy()['failedNotificationDurationPeriod'] !== $failurePeriod) {
            throw new RuntimeException('Eine erlaubte Fristgrenze wird nicht gespeichert.');
        }
    }
    $before = $config->values;
    try {
        $service->save(['durationPeriod' => 'P1Y', 'adminHistoryDurationPeriod' => 'P6M', 'expectedRevision' => 0]);
        throw new RuntimeException('Eine veraltete DPO-Revision überschreibt neuere Fristen.');
    } catch (DomainException) {
    }
    if ($config->values !== $before || $locking->acquired !== $locking->released) {
        throw new RuntimeException('Ein Revisionskonflikt verändert Daten oder lässt die Schreibsperre bestehen.');
    }

    $config->values['retention_policy_history_v1'] = '{broken';
    $before = $config->values;
    try { $service->save(['durationPeriod' => 'P1Y', 'adminHistoryDurationPeriod' => 'P6M', 'expectedRevision' => 0]); throw new RuntimeException('Beschädigte Policyhistorie wurde überschrieben.'); }
    catch (DomainException) {}
    if ($config->values !== $before) throw new RuntimeException('Beschädigte Policyhistorie wurde verändert.');

    echo "Filzmann Raumplaner versioned retention policy test passed\n";
}
