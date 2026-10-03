<?php

declare(strict_types=1);

namespace OCA\AdRoom\Service;

use DateInterval;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use OCA\AdRoom\AppInfo\AppId;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use Throwable;

/** Versionierte, rein vorschauende Policyquelle für beendete Raumbuchungen. */
final class RoomRetentionPolicyService {
    public const CONFIGURATOR_GROUP = 'Datenschutzbeauftragte';
    private const KEY = 'retention_policy_history_v1';
    private const DEFAULT_PERIOD = 'P1Y';
    private const DEFAULT_ADMIN_HISTORY_PERIOD = 'P6M';

    public function __construct(
        private IAppConfig $config,
        private IGroupManager $groups,
        private IUserSession $session,
        private ITimeFactory $clock,
    ) {}

    public function canConfigure(): bool {
        $uid = $this->session->getUser()?->getUID() ?? '';
        if ($uid === '') return false;
        try { return $this->groups->isInGroup($uid, self::CONFIGURATOR_GROUP); }
        catch (Throwable) { return false; }
    }

    public function policy(): array {
        $history = $this->history();
        $entry = $history === [] ? null : $history[array_key_last($history)];
        return [
            'enabled' => true,
            'durationPeriod' => $entry['durationPeriod'] ?? self::DEFAULT_PERIOD,
            'adminHistoryDurationPeriod' => $entry['adminHistoryDurationPeriod'] ?? self::DEFAULT_ADMIN_HISTORY_PERIOD,
            'action' => 'REVIEW',
            'revision' => $entry['revision'] ?? 0,
            'effectiveAt' => $entry['effectiveAt'] ?? null,
            'reviewedAt' => $entry['reviewedAt'] ?? null,
            'changedBy' => $entry['changedBy'] ?? null,
            'reviewDue' => $this->reviewDue(),
        ];
    }

    /** Policy active when the record's retention trigger occurred; later revisions never shorten older records retroactively. */
    public function policyFor(DateTimeImmutable $triggerAt): array {
        $selected=['revision'=>0,'durationPeriod'=>self::DEFAULT_PERIOD,'adminHistoryDurationPeriod'=>self::DEFAULT_ADMIN_HISTORY_PERIOD,'effectiveAt'=>null];
        foreach($this->history() as $entry){
            if(($entry['event']??null)!=='configured'||!is_string($entry['effectiveAt']??null))continue;
            if(new DateTimeImmutable($entry['effectiveAt'])<=$triggerAt)$selected=$entry;
        }
        return$selected;
    }

    public function save(array $policy): array {
        $uid = $this->requireConfigurator();
        $period = $this->validatePeriod($policy['durationPeriod'] ?? null);
        $adminHistoryPeriod = $this->validatePeriod($policy['adminHistoryDurationPeriod'] ?? null);
        if($adminHistoryPeriod!==self::DEFAULT_ADMIN_HISTORY_PERIOD)throw new InvalidArgumentException('Die Adminfreigabehistorie hat die feste Frist P6M.');
        $expected = $this->validateExpectedRevision($policy['expectedRevision'] ?? null);
        $history = $this->history();
        $this->assertRevision($history, $expected);
        $now = $this->clock->now()->format(DATE_ATOM);
        $history[] = [
            'revision'=>$expected+1,
            'event'=>'configured',
            'durationPeriod'=>$period,
            'adminHistoryDurationPeriod'=>$adminHistoryPeriod,
            'effectiveAt'=>$now,
            'reviewedAt'=>$now,
            'changedBy'=>$uid,
        ];
        $this->persist($history);
        return $this->policy();
    }

    public function recordReview(int $expectedRevision): array {
        $uid = $this->requireConfigurator();
        $history = $this->history();
        $this->assertRevision($history, $expectedRevision);
        $current = $history === [] ? [
            'durationPeriod'=>self::DEFAULT_PERIOD,
            'adminHistoryDurationPeriod'=>self::DEFAULT_ADMIN_HISTORY_PERIOD,
            'effectiveAt'=>null,
        ] : $history[array_key_last($history)];
        $history[] = [
            'revision'=>$expectedRevision+1,
            'event'=>'reviewed',
            'durationPeriod'=>$current['durationPeriod'],
            'adminHistoryDurationPeriod'=>$current['adminHistoryDurationPeriod'],
            'effectiveAt'=>$current['effectiveAt'],
            'reviewedAt'=>$this->clock->now()->format(DATE_ATOM),
            'changedBy'=>$uid,
        ];
        $this->persist($history);
        return $history[array_key_last($history)];
    }

    public function history(): array {
        $encoded = $this->config->getValueString(AppId::VALUE, self::KEY, '');
        if ($encoded === '') return [];
        try {
            $history = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($history)) throw new InvalidArgumentException();
            $validated = [];
            foreach ($history as $index => $entry) {
                if (!is_array($entry) || ($entry['revision']??null)!==$index+1
                    || !in_array($entry['event']??null,['configured','reviewed'],true)
                    || !is_string($entry['changedBy']??null) || trim($entry['changedBy'])==='') throw new InvalidArgumentException();
                $entry['durationPeriod']=$this->validatePeriod($entry['durationPeriod']??null);
                $entry['adminHistoryDurationPeriod']=$this->validatePeriod($entry['adminHistoryDurationPeriod']??null);
                foreach (['effectiveAt','reviewedAt'] as $field) if ($entry[$field]!==null) new DateTimeImmutable((string)$entry[$field]);
                $validated[]=$entry;
            }
            return $validated;
        } catch (Throwable $error) {
            throw new DomainException('Die gespeicherte Retention-Policyhistorie ist beschädigt.', 0, $error);
        }
    }

    public function reviewDue(): bool {
        $history=$this->history();
        if($history===[]) return true;
        $latest=$history[array_key_last($history)];
        $baseline=$latest['reviewedAt']??$latest['effectiveAt']??null;
        return is_string($baseline) && $this->clock->now()>=(new DateTimeImmutable($baseline))->add(new DateInterval('P1Y'));
    }

    public function retentionCriteria(): string {
        return sprintf('REVIEW %s nach Ende der Buchung; keine automatische Löschung oder Anonymisierung.',$this->policy()['durationPeriod']);
    }

    private function requireConfigurator(): string {
        $uid=$this->session->getUser()?->getUID()??'';
        if($uid===''||!$this->canConfigure()) throw new DomainException('Zugriff verweigert.');
        return $uid;
    }
    private function validatePeriod(mixed $period): string {
        if(!is_string($period)||!preg_match('/^P([1-9][0-9]*)([YMD])$/',$period,$matches)) throw new InvalidArgumentException('Retention-Frist ist ungültig.');
        $amount=(int)$matches[1];
        try{$days=(new DateTimeImmutable('2024-01-01T00:00:00+00:00'))->diff((new DateTimeImmutable('2024-01-01T00:00:00+00:00'))->add(new DateInterval($period)))->days;}
        catch(Throwable){$days=false;}
        if($days===false||$days<30||$days>1096) throw new InvalidArgumentException('Retention-Frist muss zwischen 30 Tagen und 3 Jahren liegen.');
        return $period;
    }
    private function validateExpectedRevision(mixed $revision): int {
        if(!is_int($revision)||$revision<0) throw new InvalidArgumentException('Policyversion ist ungültig.');
        return $revision;
    }
    private function assertRevision(array $history,int $expected): void {
        $actual=$history===[]?0:(int)$history[array_key_last($history)]['revision'];
        if($actual!==$expected) throw new DomainException('Policy wurde zwischenzeitlich geändert.');
    }
    private function persist(array $history): void {
        $this->config->setValueString(AppId::VALUE,self::KEY,json_encode($history,JSON_THROW_ON_ERROR));
    }
}
