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

    $service = new RoomRetentionPolicyService($config, $groups, $session, $clock);
    $default = $service->policy();
    if ($default['durationPeriod'] !== 'P1Y'
        || $default['adminHistoryDurationPeriod'] !== 'P6M'
        || $default['action'] !== 'REVIEW'
        || $default['revision'] !== 0
        || !$default['reviewDue']) {
        throw new RuntimeException('Die beschlossene Jahresfrist ist nicht der sichere versionierte Standard.');
    }
    if (!$service->canConfigure()) throw new RuntimeException('Datenschutzbeauftragte können die Policy nicht konfigurieren.');

    $saved = $service->save(['durationPeriod' => 'P18M', 'adminHistoryDurationPeriod' => 'P6M', 'expectedRevision' => 0]);
    if ($saved['revision'] !== 1 || $saved['durationPeriod'] !== 'P18M' || $saved['adminHistoryDurationPeriod'] !== 'P6M' || $saved['changedBy'] !== 'dpo') {
        throw new RuntimeException('Eine gültige Policyänderung wird nicht versioniert und akteursgebunden gespeichert.');
    }
    if (count($service->history()) !== 1 || $service->reviewDue()) {
        throw new RuntimeException('Policyhistorie oder jährlicher Reviewstatus ist falsch.');
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

    $config->values['retention_policy_history_v1'] = '{broken';
    $before = $config->values;
    try { $service->save(['durationPeriod' => 'P1Y', 'adminHistoryDurationPeriod' => 'P6M', 'expectedRevision' => 0]); throw new RuntimeException('Beschädigte Policyhistorie wurde überschrieben.'); }
    catch (DomainException) {}
    if ($config->values !== $before) throw new RuntimeException('Beschädigte Policyhistorie wurde verändert.');

    echo "Filzmann Raumplaner versioned retention policy test passed\n";
}
