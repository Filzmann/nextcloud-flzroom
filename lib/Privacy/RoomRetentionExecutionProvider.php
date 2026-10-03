<?php

declare(strict_types=1);

namespace OCA\AdRoom\Privacy;

use DateInterval;
use DateTimeImmutable;
use OCA\AdRoom\AppInfo\AppId;
use OCA\AdRoom\Model\Booking;
use OCA\AdRoom\Repository\BookingRepository;
use OCA\AdRoom\Repository\RetentionHoldRepository;
use OCA\AdRoom\Repository\TemporaryAdminAccessRepository;
use OCA\AdRoom\Service\RoomRetentionPolicyService;
use OCA\FilzmannDataProtection\PublicApi\V2\RetentionExecutionBatch;
use OCA\FilzmannDataProtection\PublicApi\V2\RetentionExecutionCandidate;
use OCA\FilzmannDataProtection\PublicApi\V2\RetentionExecutionPage;
use OCA\FilzmannDataProtection\PublicApi\V2\RetentionExecutionPolicy;
use OCA\FilzmannDataProtection\PublicApi\V2\RetentionExecutionProvider;
use OCA\FilzmannDataProtection\PublicApi\V2\RetentionExecutionProviderDescriptor;
use OCA\FilzmannDataProtection\PublicApi\V2\RetentionExecutionRequest;
use OCA\FilzmannDataProtection\PublicApi\V2\RetentionExecutionResult;
use OCP\IDBConnection;
use Throwable;

final class RoomRetentionExecutionProvider implements RetentionExecutionProvider {
    public const BOOKING_POLICY_ID='room_booking_delete';
    public const ADMIN_HISTORY_POLICY_ID='temporary_admin_access_history_delete';
    public function __construct(private BookingRepository $bookings,private TemporaryAdminAccessRepository $adminHistory,private RetentionHoldRepository $holds,private RoomRetentionPolicyService $policy,private IDBConnection $db){}
    public function descriptor():RetentionExecutionProviderDescriptor{return new RetentionExecutionProviderDescriptor(AppId::VALUE,'AD Raumplaner','2.0',100);}
    public function policies():array{$current=$this->policy->policy();$version='2.'.(int)$current['revision'];return[
        new RetentionExecutionPolicy(self::BOOKING_POLICY_ID,'Raumbuchungen','Vollständige Löschung nach app-lokaler Frist','COMPLETED_AT',$current['durationPeriod'],'DELETE',$version),
        new RetentionExecutionPolicy(self::ADMIN_HISTORY_POLICY_ID,'Adminfreigabehistorie','Vollständige Löschung sechs Monate nach tatsächlichem Ende','COMPLETED_AT','P6M','DELETE',$version),
    ];}
    public function plan(RetentionExecutionRequest $request):RetentionExecutionPage{
        $policy=$this->requestedPolicy($request);$evaluatedAt=new DateTimeImmutable($request->evaluatedAt());
        return$request->policyId()===self::BOOKING_POLICY_ID?$this->planBookings($request,$evaluatedAt):$this->planAdminHistory($request,$evaluatedAt);
    }
    public function execute(RetentionExecutionBatch $batch):RetentionExecutionResult{
        $this->requestedPolicy($batch->request());$deleted=[];$held=[];$stale=[];$failed=[];
        foreach($batch->candidates() as $candidate){$reference=$candidate->reference();$this->db->beginTransaction();try{
            $status=$candidate->policyId()===self::BOOKING_POLICY_ID?$this->deleteBooking($candidate,$batch->request()):$this->deleteAdminHistory($candidate,$batch->request());
            $this->db->commit();${$status}[]=$reference;
        }catch(Throwable){$this->db->rollBack();$failed[]=$reference;}}
        return new RetentionExecutionResult($deleted,$held,$stale,$failed);
    }
    private function planBookings(RetentionExecutionRequest $request,DateTimeImmutable $evaluatedAt):RetentionExecutionPage{
        $eligible=[];$held=[];$offset=0;$scan=500;$broadCutoff=$evaluatedAt->sub(new DateInterval('P30D'));
        do{$rows=$this->bookings->findEndedBefore($broadCutoff,$scan,$offset);foreach($rows as $booking){$policy=$this->policy->policyFor($booking->endsAt());if($booking->endsAt()->add(new DateInterval($policy['durationPeriod']))>$evaluatedAt)continue;$ref='booking:'.$booking->id();if($this->holds->activeFor(self::BOOKING_POLICY_ID,$ref)!==null){$held[]=$ref;continue;}$eligible[]=$this->candidate(self::BOOKING_POLICY_ID,$ref,$booking->endsAt(),$request->policyVersion(),(int)$policy['revision']);if(count($eligible)>=$request->limit())break 2;}$offset+=count($rows);}while(count($rows)===$scan);
        return new RetentionExecutionPage($eligible,$held);
    }
    private function planAdminHistory(RetentionExecutionRequest $request,DateTimeImmutable $evaluatedAt):RetentionExecutionPage{
        $cutoff=$evaluatedAt->sub(new DateInterval('P6M'));$rows=$this->adminHistory->endedBefore($cutoff,$request->limit()*2,0);$eligible=[];$held=[];
        foreach($rows as $grant){$end=$this->actualEnd($grant);$ref='admin-grant:'.$grant['id'];if($this->holds->activeFor(self::ADMIN_HISTORY_POLICY_ID,$ref)!==null){$held[]=$ref;continue;}$eligible[]=$this->candidate(self::ADMIN_HISTORY_POLICY_ID,$ref,$end,$request->policyVersion(),0);if(count($eligible)>=$request->limit())break;}
        return new RetentionExecutionPage($eligible,$held);
    }
    private function deleteBooking(RetentionExecutionCandidate $candidate,RetentionExecutionRequest $request):string{
        if(!preg_match('/^booking:([1-9][0-9]*)$/',$candidate->reference(),$m))return'stale';$booking=$this->bookings->findForUpdate((int)$m[1]);if(!$booking instanceof Booking)return'stale';$policy=$this->policy->policyFor($booking->endsAt());if($booking->endsAt()->add(new DateInterval($policy['durationPeriod']))>new DateTimeImmutable($request->evaluatedAt()))return'stale';if(!$this->tokenMatches($candidate,$booking->endsAt(),(int)$policy['revision']))return'stale';if($this->holds->activeFor(self::BOOKING_POLICY_ID,$candidate->reference())!==null)return'held';return$this->bookings->deleteIfEndedAt((int)$m[1],$booking->endsAt())?'deleted':'stale';
    }
    private function deleteAdminHistory(RetentionExecutionCandidate $candidate,RetentionExecutionRequest $request):string{
        if(!preg_match('/^admin-grant:([1-9][0-9]*)$/',$candidate->reference(),$m))return'stale';$grant=$this->adminHistory->findForUpdate((int)$m[1]);if($grant===null)return'stale';$end=$this->actualEnd($grant);if($end->add(new DateInterval('P6M'))>new DateTimeImmutable($request->evaluatedAt()))return'stale';if(!$this->tokenMatches($candidate,$end,0))return'stale';if($this->holds->activeFor(self::ADMIN_HISTORY_POLICY_ID,$candidate->reference())!==null)return'held';return$this->adminHistory->deleteIfActualEnd((int)$m[1],$end)?'deleted':'stale';
    }
    private function requestedPolicy(RetentionExecutionRequest $request):RetentionExecutionPolicy{foreach($this->policies()as$policy)if($policy->policyId()===$request->policyId()&&$policy->version()===$request->policyVersion())return$policy;throw new \DomainException('Retention policy version is unavailable.');}
    private function candidate(string $policyId,string $reference,DateTimeImmutable $occurredAt,string $version,int $revision):RetentionExecutionCandidate{return new RetentionExecutionCandidate($policyId,$reference,$occurredAt->format(DATE_ATOM),'DELETE',$version,$this->token($policyId,$reference,$occurredAt,$version,$revision));}
    private function tokenMatches(RetentionExecutionCandidate $candidate,DateTimeImmutable $occurredAt,int $revision):bool{return hash_equals($candidate->executionToken(),$this->token($candidate->policyId(),$candidate->reference(),$occurredAt,$candidate->policyVersion(),$revision));}
    private function token(string $policyId,string $reference,DateTimeImmutable $occurredAt,string $version,int $revision):string{return hash('sha256',implode('|',[$policyId,$reference,$occurredAt->format(DATE_ATOM),$version,(string)$revision]));}
    private function actualEnd(array $grant):DateTimeImmutable{return$grant['revokedAt']!==null&&$grant['revokedAt']<$grant['endsAt']?$grant['revokedAt']:$grant['endsAt'];}
}
