<?php

declare(strict_types=1);

namespace OCP { interface IRequest {} }
namespace OCP\AppFramework { class Controller { public function __construct(string $appName, \OCP\IRequest $request) {} } final class Http { public const STATUS_BAD_REQUEST=400; public const STATUS_FORBIDDEN=403; public const STATUS_SERVICE_UNAVAILABLE=503; } }
namespace OCP\AppFramework\Http { final class JSONResponse { public function __construct(private array $data=[],private int $status=200){} public function getData():array{return $this->data;} public function getStatus():int{return $this->status;} } }
namespace OCP\AppFramework\Http\Attribute { #[\Attribute(\Attribute::TARGET_METHOD)] final class NoAdminRequired {} #[\Attribute(\Attribute::TARGET_METHOD)] final class NoCSRFRequired {} }
namespace Psr\Log { interface LoggerInterface { public function warning(string $message,array $context=[]):void; } }
namespace OCA\FlzRoom\AppInfo { final class AppId { public const VALUE='flzroom'; } }
namespace OCA\FlzRoom\Service {
    final class RoomPermissionPolicyService {
        public bool $allowed=false;
        public array $assignments=['room.read'=>['pfk']];
        public int $writes=0;
        public string $mode='success';
        public function canConfigure():bool{return $this->allowed;}
        public function configuration():array{return ['status'=>'VALID','message'=>null,'roles'=>[['roleKey'=>'pfk','label'=>'Pflegefachkraft']],'permissions'=>[['permissionKey'=>'room.read','label'=>'Raumplan lesen','description'=>'Lesen']],'assignments'=>$this->assignments];}
        public function save(array $assignments):array{
            if($this->mode==='invalid')throw new \InvalidArgumentException('ungültig');
            if($this->mode==='unavailable')throw new \UnexpectedValueException('nicht verfügbar');
            $this->writes++;
            return $this->assignments=$assignments;
        }
    }
}
namespace {
    use OCA\FlzRoom\Controller\PermissionSettingsController;
    use OCA\FlzRoom\Service\RoomPermissionPolicyService;
    use OCP\AppFramework\Http;

    $request=new class implements OCP\IRequest{};
    $service=new RoomPermissionPolicyService();
    $logger=new class implements Psr\Log\LoggerInterface{public array $warnings=[];public function warning(string $message,array $context=[]):void{$this->warnings[]=$message;}};
    $controller=new PermissionSettingsController($request,$service,$logger);
    $assert=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);};

    $assert($controller->configuration()->getStatus()===Http::STATUS_FORBIDDEN,'Gewöhnliche Konten können die Rechtezuordnung lesen.');
    $assert($controller->save(['room.read'=>['pfk']])->getStatus()===Http::STATUS_FORBIDDEN,'Gewöhnliche Konten können die Rechtezuordnung ändern.');
    $assert($service->writes===0,'Ein verweigerter Controlleraufruf hat die Gruppenpolicy verändert.');

    $service->allowed=true;
    $configuration=$controller->configuration()->getData();
    $assert($configuration['assignments']['room.read']===['pfk']&&$configuration['roles'][0]['roleKey']==='pfk','Semantische Rollenrechte werden nicht ausgegeben.');
    $saved=$controller->save(['room.read'=>[]]);
    $assert($saved->getData()['assignments']['room.read']===[]&&$service->writes===1,'Eine zulässige Rechtezuordnung wird nicht gespeichert.');
    $service->mode='invalid';
    $invalid=$controller->save(['room.read'=>['Manipulation']]);
    $assert($invalid->getStatus()===Http::STATUS_BAD_REQUEST&&$service->writes===1,'Eine ungültige Gruppenpolicy wurde nicht ohne Seiteneffekt abgelehnt.');
    $assert($logger->warnings===['Rechtezuordnung des Raumplaners wurde abgelehnt.'],'Die abgelehnte Konfiguration bleibt nicht diagnostizierbar.');
    $service->mode='unavailable';
    $unavailable=$controller->save([]);
    $assert($unavailable->getStatus()===Http::STATUS_SERVICE_UNAVAILABLE&&$service->writes===1,'Ein fehlender Organisationssnapshot wurde nicht ohne Seiteneffekt abgelehnt.');

    echo "Filzmann Raumplaner permission settings controller tests passed\n";
}
