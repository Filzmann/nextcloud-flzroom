<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Service;

use DateInterval;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use OCA\FlzRoom\Privacy\RoomRetentionExecutionProvider;
use OCA\FlzRoom\Repository\BookingRepository;
use OCA\FlzRoom\Repository\RetentionHoldRepository;
use OCA\FlzRoom\Repository\TemporaryAdminAccessRepository;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserSession;
use Throwable;

final class RetentionHoldService {
    public function __construct(private RetentionHoldRepository $holds,private BookingRepository $bookings,private TemporaryAdminAccessRepository $adminHistory,private IGroupManager $groups,private IUserSession $session,private ITimeFactory $clock,private IDBConnection $db){}
    public function place(string $policyId,string $recordReference,string $reasonCode,string $evidenceReference):array{
        $uid=$this->requireDpo();$this->validatePolicyAndReference($policyId,$recordReference);$reasonCode=$this->technicalId($reasonCode,64);$evidenceReference=$this->reference($evidenceReference);$now=$this->clock->now();
        $this->db->beginTransaction();try{$this->lockTarget($policyId,$recordReference);if($this->holds->activeFor($policyId,$recordReference)!==null)throw new DomainException('Für diesen Datensatz besteht bereits ein Hold.');$id=$this->holds->place($policyId,$recordReference,$reasonCode,$evidenceReference,$uid,$now,$now->add(new DateInterval('P90D')));$this->db->commit();}
        catch(Throwable $error){$this->db->rollBack();throw$error;}
        return['id'=>$id,'policyId'=>$policyId,'recordReference'=>$recordReference,'reasonCode'=>$reasonCode,'evidenceReference'=>$evidenceReference,'placedAt'=>$now->format(DATE_ATOM),'reviewDueAt'=>$now->add(new DateInterval('P90D'))->format(DATE_ATOM)];
    }
    public function release(string $policyId,string $recordReference):void{
        $uid=$this->requireDpo();$this->validatePolicyAndReference($policyId,$recordReference);$now=$this->clock->now();$this->db->beginTransaction();try{$this->lockTarget($policyId,$recordReference,false);if(!$this->holds->releaseActive($policyId,$recordReference,$uid,$now))throw new InvalidArgumentException('Aktiver Hold wurde nicht gefunden.');$this->db->commit();}catch(Throwable$error){$this->db->rollBack();throw$error;}
    }
    private function requireDpo():string{$uid=$this->session->getUser()?->getUID()??'';try{$allowed=$uid!==''&&$this->groups->isInGroup($uid,RoomRetentionPolicyService::CONFIGURATOR_GROUP);}catch(Throwable){$allowed=false;}if(!$allowed)throw new DomainException('Zugriff verweigert.');return$uid;}
    private function validatePolicyAndReference(string $policyId,string $reference):void{if($policyId===RoomRetentionExecutionProvider::BOOKING_POLICY_ID&&preg_match('/^booking:[1-9][0-9]*$/',$reference))return;if($policyId===RoomRetentionExecutionProvider::ADMIN_HISTORY_POLICY_ID&&preg_match('/^admin-grant:[1-9][0-9]*$/',$reference))return;throw new InvalidArgumentException('Unbekannter Hold-Scope.');}
    private function lockTarget(string $policyId,string $reference,bool $required=true):void{$id=(int)substr($reference,strpos($reference,':')+1);$target=$policyId===RoomRetentionExecutionProvider::BOOKING_POLICY_ID?$this->bookings->findForUpdate($id):$this->adminHistory->findForUpdate($id);if($required&&$target===null)throw new InvalidArgumentException('Zieldatensatz wurde nicht gefunden.');}
    private function technicalId(string $value,int $max):string{$value=trim($value);if(strlen($value)>$max||!preg_match('/^[a-z][a-z0-9_-]{1,63}$/',$value))throw new InvalidArgumentException('Grundcode ist ungültig.');return$value;}
    private function reference(string $value):string{$value=trim($value);if($value===''||strlen($value)>255||preg_match('/[\r\n\x00-\x1F]/',$value))throw new InvalidArgumentException('Nachweisreferenz ist ungültig.');return$value;}
}
