<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Repository {
    final class BookingRepository { public function findEndedBefore(\DateTimeImmutable $cutoff,int $limit,int $offset=0):array{return [];} }
    final class TemporaryAdminAccessRepository { public function endedBefore(\DateTimeImmutable $cutoff,int $limit,int $offset=0):array{return [];} }
    final class BookingInterventionAuditRepository {
        public int $deletes=0;
        public array $cutoffs=[];
        public function olderThan(\DateTimeImmutable $cutoff,int $limit,int $offset=0):array{$this->cutoffs[]=$cutoff;return [['id'=>7,'occurredAt'=>new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),'actorUid'=>'hidden','reason'=>'hidden']];}
    }
    final class InterventionNotificationQueueRepository {
        public int $deletes=0;
        public array $cutoffs=[];
        public function permanentlyFailedBefore(\DateTimeImmutable $cutoff,int $limit,int $offset=0):array{$this->cutoffs[]=$cutoff;return [['id'=>8,'failedAt'=>new \DateTimeImmutable('2026-01-01T10:00:00+00:00'),'recipientUid'=>'hidden','reason'=>'hidden']];}
    }
}
namespace OCA\FlzRoom\Service { final class RoomRetentionPolicyService { public array $periods=['interventionAuditDurationPeriod'=>'P1Y','failedNotificationDurationPeriod'=>'P30D']; public function policy():array{return array_merge(['enabled'=>true,'revision'=>1,'durationPeriod'=>'P1Y','adminHistoryDurationPeriod'=>'P6M'],$this->periods);} } }
namespace {
    use OCA\FlzRoom\Privacy\RoomRetentionProvider;
    use OCA\FlzRoom\Repository\BookingInterventionAuditRepository;
    use OCA\FlzRoom\Repository\BookingRepository;
    use OCA\FlzRoom\Repository\InterventionNotificationQueueRepository;
    use OCA\FlzRoom\Repository\TemporaryAdminAccessRepository;
    use OCA\FlzRoom\Service\RoomRetentionPolicyService;
    use OCA\FlzDataProtection\PublicApi\V1\RetentionPreviewRequest;
    $audit=new BookingInterventionAuditRepository();$queue=new InterventionNotificationQueueRepository();
    $policy = new RoomRetentionPolicyService();
    $provider=new RoomRetentionProvider(new BookingRepository(),new TemporaryAdminAccessRepository(),$policy,$audit,$queue);
    $policies=$provider->policies();
    $ids=array_map(static fn($policy)=>$policy->policyId(),$policies);
    if($ids!==['room_booking_review','temporary_admin_access_history_review','secretariat_intervention_audit_12_months','failed_intervention_notification_30_days'])throw new RuntimeException('Feste Eingriffs-Retention-Policies fehlen.');
    $failedNotificationPolicy=$policies[3]->toArray();
    if(($failedNotificationPolicy['trigger']??null)!=='COMPLETED_AT'||($failedNotificationPolicy['durationPeriod']??null)!=='P30D')throw new RuntimeException('Die fehlgeschlagene Zustellung erfüllt den öffentlichen Retention-Policy-Vertrag nicht.');
    $auditPage=$provider->preview(new RetentionPreviewRequest('secretariat_intervention_audit_12_months','2026-09-29T10:00:00+00:00',20));
    $queuePage=$provider->preview(new RetentionPreviewRequest('failed_intervention_notification_30_days','2026-09-29T10:00:00+00:00',20));
    $encoded=json_encode([$auditPage->candidates()[0]->toArray(),$queuePage->candidates()[0]->toArray()],JSON_THROW_ON_ERROR);
    if(str_contains($encoded,'hidden')||$audit->deletes!==0||$queue->deletes!==0)throw new RuntimeException('Retention-Vorschau legt Personeninhalte offen oder verändert Daten.');
    if(($queue->cutoffs[0]??null)!=new \DateTimeImmutable('2026-08-30T10:00:00+00:00')||($queuePage->candidates()[0]->toArray()['occurredAt']??null)!=='2026-01-01T10:00:00+00:00')throw new RuntimeException('Die bestehende failedAt-basierte Dreißigtagesfrist wurde verändert.');
    $policy->periods = ['interventionAuditDurationPeriod' => 'P90D', 'failedNotificationDurationPeriod' => 'P7D'];
    $policies = $provider->policies();
    $provider->preview(new RetentionPreviewRequest(RoomRetentionProvider::INTERVENTION_AUDIT_POLICY_ID, '2026-09-29T10:00:00+00:00', 20));
    $provider->preview(new RetentionPreviewRequest(RoomRetentionProvider::FAILED_NOTIFICATION_POLICY_ID, '2026-09-29T10:00:00+00:00', 20));
    if ($policies[2]->toArray()['durationPeriod'] !== 'P90D'
        || $policies[3]->toArray()['durationPeriod'] !== 'P7D'
        || ($audit->cutoffs[1] ?? null) != new DateTimeImmutable('2026-07-01T10:00:00+00:00')
        || ($queue->cutoffs[1] ?? null) != new DateTimeImmutable('2026-09-22T10:00:00+00:00')) {
        throw new RuntimeException('Die Retention-Vorschau ignoriert geänderte Eingriffsfristen.');
    }
    echo "Intervention retention provider tests passed\n";
}
