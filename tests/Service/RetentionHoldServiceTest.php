<?php

declare(strict_types=1);

namespace OCP {
    interface IDBConnection { public function beginTransaction():void; public function commit():void; public function rollBack():void; }
    interface IUser{public function getUID():string;} interface IUserSession{public function getUser():?IUser;} interface IGroupManager{public function isInGroup(string $uid,string $gid):bool;}
}
namespace OCP\AppFramework\Utility{interface ITimeFactory{public function now():\DateTimeImmutable;}}
namespace OCA\AdRoom\Repository {
    final class BookingRepository { public bool $exists=true;public function findForUpdate(int $id):?object{return$this->exists?(object)['id'=>$id]:null;} }
    final class TemporaryAdminAccessRepository { public bool $exists=true;public function findForUpdate(int $id):?array{return$this->exists?['id'=>$id]:null;} }
    final class RetentionHoldRepository {
        public array $rows=[];
        public function activeFor(string $policyId,string $reference):?array{foreach(array_reverse($this->rows)as$row)if($row['policyId']===$policyId&&$row['recordReference']===$reference&&$row['releasedAt']===null)return$row;return null;}
        public function place(string $policyId,string $reference,string $reason,string $evidence,string $by,\DateTimeImmutable $at,\DateTimeImmutable $due):int{$this->rows[]=['id'=>count($this->rows)+1,'policyId'=>$policyId,'recordReference'=>$reference,'reasonCode'=>$reason,'evidenceReference'=>$evidence,'placedBy'=>$by,'placedAt'=>$at,'reviewDueAt'=>$due,'releasedAt'=>null];return count($this->rows);}
        public function releaseActive(string $policyId,string $reference,string $by,\DateTimeImmutable $at):bool{foreach($this->rows as &$row)if($row['policyId']===$policyId&&$row['recordReference']===$reference&&$row['releasedAt']===null){$row['releasedAt']=$at;$row['releasedBy']=$by;return true;}return false;}
    }
}
namespace {
    use OCA\AdRoom\Privacy\RoomRetentionExecutionProvider;
    use OCA\AdRoom\Repository\BookingRepository;
    use OCA\AdRoom\Repository\RetentionHoldRepository;
    use OCA\AdRoom\Repository\TemporaryAdminAccessRepository;
    use OCA\AdRoom\Service\RetentionHoldService;
    $session=new class implements OCP\IUserSession{public string $uid='dpo';public function getUser():?OCP\IUser{return new class($this->uid)implements OCP\IUser{public function __construct(private string $uid){}public function getUID():string{return$this->uid;}};}};
    $groups=new class implements OCP\IGroupManager{public function isInGroup(string $uid,string $gid):bool{return$uid==='dpo'&&$gid==='Datenschutzbeauftragte';}};
    $clock=new class implements OCP\AppFramework\Utility\ITimeFactory{public function now():DateTimeImmutable{return new DateTimeImmutable('2026-09-29T10:00:00+00:00');}};
    $db=new class implements OCP\IDBConnection{public int $commits=0;public function beginTransaction():void{}public function commit():void{$this->commits++;}public function rollBack():void{}};
    $holds=new RetentionHoldRepository();$bookings=new BookingRepository();$admins=new TemporaryAdminAccessRepository();
    $service=new RetentionHoldService($holds,$bookings,$admins,$groups,$session,$clock,$db);
    $hold=$service->place(RoomRetentionExecutionProvider::BOOKING_POLICY_ID,'booking:17','legal_claim','CASE-REF-1');
    if($hold['reviewDueAt']!=='2026-12-28T10:00:00+00:00'||$db->commits!==1)throw new RuntimeException('Hold wird nicht DPO-gebunden mit 90-Tage-Prüftermin angelegt.');
    $session->uid='ordinary';$before=$holds->rows;
    try{$service->release(RoomRetentionExecutionProvider::BOOKING_POLICY_ID,'booking:17');throw new RuntimeException('Nicht-DPO konnte Hold aufheben.');}catch(DomainException){}
    if($holds->rows!==$before)throw new RuntimeException('Verweigerte Hold-Aufhebung hatte Nebenwirkungen.');
    $session->uid='dpo';$service->release(RoomRetentionExecutionProvider::BOOKING_POLICY_ID,'booking:17');
    if($holds->activeFor(RoomRetentionExecutionProvider::BOOKING_POLICY_ID,'booking:17')!==null)throw new RuntimeException('DPO konnte Hold nicht explizit aufheben.');
    $bookings->exists=false;$before=$holds->rows;
    try{$service->place(RoomRetentionExecutionProvider::BOOKING_POLICY_ID,'booking:999','review','CASE-REF-2');throw new RuntimeException('Hold für fremde oder fehlende Buchung wurde akzeptiert.');}catch(InvalidArgumentException){}
    if($holds->rows!==$before)throw new RuntimeException('Ungültiger Hold hatte Nebenwirkungen.');
    echo "AD Raumplaner retention hold service tests passed\n";
}
