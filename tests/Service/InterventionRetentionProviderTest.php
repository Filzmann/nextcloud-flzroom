<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Repository {
    final class BookingRepository { public function findEndedBefore(\DateTimeImmutable $cutoff,int $limit,int $offset=0):array{return [];} }
    final class TemporaryAdminAccessRepository { public function endedBefore(\DateTimeImmutable $cutoff,int $limit,int $offset=0):array{return [];} }
    final class BookingInterventionAuditRepository {
        public int $deletes=0;
        public function olderThan(\DateTimeImmutable $cutoff,int $limit,int $offset=0):array{return [['id'=>7,'occurredAt'=>new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),'actorUid'=>'hidden','reason'=>'hidden']];}
    }
    final class InterventionNotificationQueueRepository {
        public int $deletes=0;
        public array $cutoffs=[];
        public function permanentlyFailedBefore(\DateTimeImmutable $cutoff,int $limit,int $offset=0):array{$this->cutoffs[]=$cutoff;return [['id'=>8,'failedAt'=>new \DateTimeImmutable('2026-01-01T10:00:00+00:00'),'recipientUid'=>'hidden','reason'=>'hidden']];}
    }
}
namespace OCA\FlzRoom\Service { final class RoomRetentionPolicyService { public function policy():array{return ['enabled'=>true,'revision'=>1,'durationPeriod'=>'P1Y','adminHistoryDurationPeriod'=>'P6M'];} } }
namespace {
    use OCA\FlzRoom\Privacy\RoomRetentionProvider;
    use OCA\FlzRoom\Repository\BookingInterventionAuditRepository;
    use OCA\FlzRoom\Repository\BookingRepository;
    use OCA\FlzRoom\Repository\InterventionNotificationQueueRepository;
    use OCA\FlzRoom\Repository\TemporaryAdminAccessRepository;
    use OCA\FlzRoom\Service\RoomRetentionPolicyService;
    use OCA\FlzDataProtection\PublicApi\V1\RetentionPreviewRequest;
    $audit=new BookingInterventionAuditRepository();$queue=new InterventionNotificationQueueRepository();
    $provider=new RoomRetentionProvider(new BookingRepository(),new TemporaryAdminAccessRepository(),new RoomRetentionPolicyService(),$audit,$queue);
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
    echo "Intervention retention provider tests passed\n";
}
