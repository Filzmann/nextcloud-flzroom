<?php

declare(strict_types=1);

namespace OCP { interface IDBConnection { public function beginTransaction():void; public function commit():void; public function rollBack():void; } }
namespace OCA\FlzRoom\Repository {
    final class BookingRepository {
        public array $rows=[];
        public function findEndedBefore(\DateTimeImmutable $cutoff,int $limit,int $offset=0):array{return array_slice(array_values(array_filter($this->rows,static fn($row)=>$row->endsAt()<=$cutoff)),$offset,$limit);}
        public function findForUpdate(int $id):?\OCA\FlzRoom\Model\Booking{return$this->rows[$id]??null;}
        public function deleteIfEndedAt(int $id,\DateTimeImmutable $endedAt):bool{if(!isset($this->rows[$id])||$this->rows[$id]->endsAt()!=$endedAt)return false;unset($this->rows[$id]);return true;}
    }
    final class TemporaryAdminAccessRepository {
        public array $rows=[];
        public function endedBefore(\DateTimeImmutable $cutoff,int $limit,int $offset=0):array{return array_slice(array_values(array_filter($this->rows,static fn($row)=>(($row['revokedAt']!==null&&$row['revokedAt']<$row['endsAt'])?$row['revokedAt']:$row['endsAt'])<=$cutoff)),$offset,$limit);}
        public function findForUpdate(int $id):?array{return$this->rows[$id]??null;}
        public function deleteIfActualEnd(int $id,\DateTimeImmutable $actualEnd):bool{if(!isset($this->rows[$id]))return false;$row=$this->rows[$id];$end=$row['revokedAt']!==null&&$row['revokedAt']<$row['endsAt']?$row['revokedAt']:$row['endsAt'];if($end!=$actualEnd)return false;unset($this->rows[$id]);return true;}
    }
    final class RetentionHoldRepository { public array $held=[]; public function activeFor(string $policyId,string $recordReference):?array{return isset($this->held[$policyId.':'.$recordReference])?['id'=>1]:null;} }
}
namespace OCA\FlzRoom\Service {
    final class RoomRetentionPolicyService {
        public function policy():array{return['revision'=>1,'durationPeriod'=>'P30D','adminHistoryDurationPeriod'=>'P6M'];}
        public function policyFor(\DateTimeImmutable $triggerAt):array{return$triggerAt<new \DateTimeImmutable('2026-06-01T00:00:00+00:00')?['revision'=>0,'durationPeriod'=>'P1Y','adminHistoryDurationPeriod'=>'P6M']:['revision'=>1,'durationPeriod'=>'P30D','adminHistoryDurationPeriod'=>'P6M'];}
    }
}
namespace {
    use OCA\FlzRoom\Model\Booking;
    use OCA\FlzRoom\Privacy\RoomRetentionExecutionProvider;
    use OCA\FlzRoom\Repository\BookingRepository;
    use OCA\FlzRoom\Repository\RetentionHoldRepository;
    use OCA\FlzRoom\Repository\TemporaryAdminAccessRepository;
    use OCA\FlzRoom\Service\RoomRetentionPolicyService;
    use OCA\FlzDataProtection\PublicApi\V2\RetentionExecutionBatch;
    use OCA\FlzDataProtection\PublicApi\V2\RetentionExecutionRequest;

    $catalog=json_decode((string)file_get_contents(dirname(__DIR__,2).'/resources/privacy-processing.json'),true,flags:JSON_THROW_ON_ERROR);
    $bookingMetadata=$catalog['processings'][0]??null;
    if(($bookingMetadata['legal_basis']['blocking']??null)!==false||($bookingMetadata['backup']['relevance']['blocking']??null)!==false)throw new RuntimeException('Kundenlokale Rechts- oder Backupdokumentation blockiert den technischen DELETE-Provider weiterhin.');

    $bookings=new BookingRepository();
    $bookings->rows=[
        17=>Booking::get(['id'=>17,'roomId'=>1,'userUid'=>'subject-a','purpose'=>'Abstimmung','title'=>'','startsAt'=>new DateTimeImmutable('2025-09-01T09:00:00+00:00'),'endsAt'=>new DateTimeImmutable('2025-09-01T10:00:00+00:00')]),
        18=>Booking::get(['id'=>18,'roomId'=>1,'userUid'=>'subject-b','purpose'=>'Planung','title'=>'','startsAt'=>new DateTimeImmutable('2026-08-01T09:00:00+00:00'),'endsAt'=>new DateTimeImmutable('2026-08-01T10:00:00+00:00')]),
        19=>Booking::get(['id'=>19,'roomId'=>1,'userUid'=>'subject-c','purpose'=>'Austausch','title'=>'','startsAt'=>new DateTimeImmutable('2026-09-20T09:00:00+00:00'),'endsAt'=>new DateTimeImmutable('2026-09-20T10:00:00+00:00')]),
    ];
    $admins=new TemporaryAdminAccessRepository();
    $admins->rows=[7=>['id'=>7,'targetUid'=>'admin-a','grantedBy'=>'dpo','startsAt'=>new DateTimeImmutable('2026-02-28T09:00:00+00:00'),'endsAt'=>new DateTimeImmutable('2026-03-01T09:00:00+00:00'),'revokedAt'=>null,'revokedBy'=>null]];
    $holds=new RetentionHoldRepository();
    $holds->held['room_booking_delete:booking:18']=true;
    $db=new class implements OCP\IDBConnection { public int $transactions=0; public function beginTransaction():void{$this->transactions++;}public function commit():void{}public function rollBack():void{} };
    $provider=new RoomRetentionExecutionProvider($bookings,$admins,$holds,new RoomRetentionPolicyService(),$db);

    $bookingPolicy=$provider->policies()[0];
    $bookingRequest=new RetentionExecutionRequest($bookingPolicy->policyId(),$bookingPolicy->version(),'2026-09-29T10:00:00+00:00',100);
    $bookingPlan=$provider->plan($bookingRequest);
    if(array_map(static fn($c)=>$c->reference(),$bookingPlan->candidates())!==['booking:17']||$bookingPlan->heldReferences()!==['booking:18'])throw new RuntimeException('Nicht-retroaktive Frist oder Hold wird im Dry Run nicht korrekt ausgewertet.');
    $bookingResult=$provider->execute(new RetentionExecutionBatch($bookingRequest,$bookingPlan->candidates()));
    if($bookingResult->deletedReferences()!==['booking:17']||isset($bookings->rows[17])||!isset($bookings->rows[18]))throw new RuntimeException('Freigegebene Buchung wird nicht atomar gelöscht oder Hold wird missachtet.');
    $repeat=$provider->execute(new RetentionExecutionBatch($bookingRequest,$bookingPlan->candidates()));
    if($repeat->staleReferences()!==['booking:17'])throw new RuntimeException('Wiederholung ist nicht idempotent als stale erkennbar.');

    $adminPolicy=$provider->policies()[1];
    $adminRequest=new RetentionExecutionRequest($adminPolicy->policyId(),$adminPolicy->version(),'2026-09-29T10:00:00+00:00',100);
    $adminPlan=$provider->plan($adminRequest);
    $adminResult=$provider->execute(new RetentionExecutionBatch($adminRequest,$adminPlan->candidates()));
    if($adminResult->deletedReferences()!==['admin-grant:7']||isset($admins->rows[7]))throw new RuntimeException('Beendete Adminfreigabehistorie wird nach P6M nicht gelöscht.');

    echo "Filzmann Raumplaner retention execution provider tests passed\n";
}
