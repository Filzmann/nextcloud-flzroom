<?php

declare(strict_types=1);

namespace OCP { interface IRequest {} }
namespace OCP\AppFramework { class Controller { public function __construct(string $appName, \OCP\IRequest $request) {} } final class Http { public const STATUS_BAD_REQUEST=400; public const STATUS_FORBIDDEN=403; } }
namespace OCP\AppFramework\Http { final class JSONResponse { public function __construct(private array $data=[],private int $status=200){} public function getData():array{return $this->data;} public function getStatus():int{return $this->status;} } }
namespace OCP\AppFramework\Http\Attribute { #[\Attribute(\Attribute::TARGET_METHOD)] final class NoAdminRequired {} #[\Attribute(\Attribute::TARGET_METHOD)] final class NoCSRFRequired {} }
namespace Psr\Log { interface LoggerInterface { public function warning(string $message,array $context=[]):void; } }
namespace OCA\AdRoom\AppInfo { final class AppId { public const VALUE='adroom'; } }
namespace OCA\AdRoom\Service {
    final class OrganizationGroupPolicyService {
        public bool $allowed=false;
        /** @var list<string> */ public array $groupIds=['Organisation Nord'];
        public int $writes=0;
        public string $mode='success';
        public function canConfigure():bool{return $this->allowed;}
        public function organizationGroupIds():array{return $this->groupIds;}
        public function save(array $groupIds):array{
            if($this->mode==='invalid')throw new \InvalidArgumentException('ungültig');
            $this->writes++;
            return $this->groupIds=$groupIds;
        }
    }
}
namespace {
    use OCA\AdRoom\Controller\OrganizationGroupController;
    use OCA\AdRoom\Service\OrganizationGroupPolicyService;
    use OCP\AppFramework\Http;

    $request=new class implements OCP\IRequest{};
    $service=new OrganizationGroupPolicyService();
    $logger=new class implements Psr\Log\LoggerInterface{public array $warnings=[];public function warning(string $message,array $context=[]):void{$this->warnings[]=$message;}};
    $controller=new OrganizationGroupController($request,$service,$logger);
    $assert=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);};

    $assert($controller->configuration()->getStatus()===Http::STATUS_FORBIDDEN,'Gewöhnliche Konten können die Organisationsgruppen lesen.');
    $assert($controller->save(['Organisation Süd'])->getStatus()===Http::STATUS_FORBIDDEN,'Gewöhnliche Konten können die Organisationsgruppen ändern.');
    $assert($service->writes===0,'Ein verweigerter Controlleraufruf hat die Gruppenpolicy verändert.');

    $service->allowed=true;
    $assert($controller->configuration()->getData()['organizationGroupIds']===['Organisation Nord'],'Konfigurierte Gruppen werden nicht ausgegeben.');
    $saved=$controller->save(['Organisation Süd']);
    $assert($saved->getData()['organizationGroupIds']===['Organisation Süd']&&$service->writes===1,'Eine zulässige Gruppenpolicy wird nicht gespeichert.');
    $service->mode='invalid';
    $invalid=$controller->save(['Manipulation']);
    $assert($invalid->getStatus()===Http::STATUS_BAD_REQUEST&&$service->writes===1,'Eine ungültige Gruppenpolicy wurde nicht ohne Seiteneffekt abgelehnt.');
    $assert($logger->warnings===['Organisationsgruppen des Raumplaners wurden abgelehnt.'],'Die abgelehnte Konfiguration bleibt nicht diagnostizierbar.');

    echo "AD Raumplaner organization group controller tests passed\n";
}
