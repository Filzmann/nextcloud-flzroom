<?php

declare(strict_types=1);

namespace OCP { interface IRequest {} interface IUser { public function getUID():string; } }
namespace OCP\AppFramework { class Controller { public function __construct(string $appId,\OCP\IRequest $request){} } final class Http { public const STATUS_FORBIDDEN=403; public const STATUS_BAD_REQUEST=400; public const STATUS_CONFLICT=409; } }
namespace OCP\AppFramework\Http { final class JSONResponse { public function __construct(private array $data=[],private int $status=200){} public function getData():array{return $this->data;} public function getStatus():int{return $this->status;} } }
namespace OCP\AppFramework\Http\Attribute { #[\Attribute(\Attribute::TARGET_METHOD)] final class NoCSRFRequired {} }
namespace Psr\Log { interface LoggerInterface { public function warning(string $message,array $context=[]):void; } }
namespace OCA\AdRoom\Service {
    class RoomAccessService { public bool $allowed=false; public function canManageRooms():bool{return $this->allowed;} public function currentUser():?\OCP\IUser{return $this->allowed?new class implements \OCP\IUser{public function getUID():string{return 'admin';}}:null;} }
    class RoomRetentionPolicyService { public array $value=['enabled'=>true,'durationPeriod'=>'P1Y','adminHistoryDurationPeriod'=>'P6M','action'=>'REVIEW','revision'=>0]; public int $saves=0; public bool $allowed=false; public function canConfigure():bool{return $this->allowed;} public function policy():array{return $this->value;} public function history():array{return [];} public function save(array $policy):array{$this->saves++;if(($policy['durationPeriod']??'')==='invalid')throw new \InvalidArgumentException('ungültig');return $this->value=['enabled'=>true,'durationPeriod'=>$policy['durationPeriod'],'adminHistoryDurationPeriod'=>$policy['adminHistoryDurationPeriod'],'action'=>'REVIEW','revision'=>$policy['expectedRevision']+1];} public function recordReview(int $revision):array{return ['revision'=>$revision+1];} }
    class RoomAdminLayoutService { public int $saves=0; public function layout(string $uid):array{return ['version'=>1,'scopes'=>['main'=>['order'=>['rooms','demo'],'collapsed'=>[]]],'organigram'=>['zoom'=>100]];} public function save(string $uid,array $layout):array{$this->saves++;return $layout;} }
}

namespace {
    use OCA\AdRoom\Controller\RetentionAdminController;
    use OCA\AdRoom\Service\RoomAccessService;
    use OCA\AdRoom\Service\RoomAdminLayoutService;
    use OCA\AdRoom\Service\RoomRetentionPolicyService;
    use OCP\AppFramework\Http;

    $access=new RoomAccessService();
    $policy=new RoomRetentionPolicyService();
    $layout=new RoomAdminLayoutService();
    $logger=new class implements Psr\Log\LoggerInterface{public array $warnings=[];public function warning(string $message,array $context=[]):void{$this->warnings[]=$message;}};
    $controller=new RetentionAdminController(new class implements OCP\IRequest{},$access,$policy,$layout,$logger);
    if($controller->policy()->getStatus()!==Http::STATUS_FORBIDDEN||$controller->savePolicy('P1Y','P6M',0)->getStatus()!==Http::STATUS_FORBIDDEN||$policy->saves!==0)throw new RuntimeException('Nicht-DPOs können Retention lesen oder ändern.');
    $access->allowed=true;
    if(isset($controller->settings()->getData()['retentionPolicy']))throw new RuntimeException('Technische Admin-Einstellungen legen DPO-Retention offen.');
    $policy->allowed=true;
    $savedPolicy=$controller->savePolicy('P18M','P6M',0)->getData()['retentionPolicy'];
    if($savedPolicy['durationPeriod']!=='P18M'||$savedPolicy['adminHistoryDurationPeriod']!=='P6M'||$policy->saves!==1)throw new RuntimeException('DPO kann gültige Retention nicht speichern.');
    if($controller->savePolicy('invalid','P6M',1)->getStatus()!==Http::STATUS_BAD_REQUEST||$policy->saves!==2)throw new RuntimeException('Ungültige Retention wird nicht sicher abgelehnt.');
    $newLayout=['version'=>1,'scopes'=>['main'=>['order'=>['rooms','demo'],'collapsed'=>[]]],'organigram'=>['zoom'=>100]];
    if($controller->saveLayout($newLayout)->getData()['dashboardLayout']!==$newLayout||$layout->saves!==1)throw new RuntimeException('Persönliches Kartenlayout wird nicht gespeichert.');
    echo "AD Raumplaner retention admin controller test passed\n";
}
