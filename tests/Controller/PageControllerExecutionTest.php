<?php

declare(strict_types=1);

namespace OCP { interface IRequest {} }
namespace OCP\AppFramework { class Controller { public function __construct(string $appName,\OCP\IRequest $request){} } final class Http { public const STATUS_FORBIDDEN=403; } }
namespace OCP\AppFramework\Http {
    class TemplateResponse { public function __construct(public string $appName,public string $templateName,public array $params=[]){} }
    class JSONResponse { public function __construct(private array $data=[],private int $status=200){} public function getStatus():int{return $this->status;} }
}
namespace OCP\AppFramework\Http\Attribute { #[\Attribute(\Attribute::TARGET_METHOD)] final class NoAdminRequired {} #[\Attribute(\Attribute::TARGET_METHOD)] final class NoCSRFRequired {} }
namespace OCA\FlzRoom\AppInfo { final class Application { public const APP_ID='flzroom'; } }
namespace OCA\FlzRoom\Service {
    final class TemporaryAdminAccessService { public function canManageGrants():bool{return false;} public function currentAdminNeedsGrant():bool{return false;} }
    final class RoomRetentionPolicyService { public function canConfigure():bool{return false;} }
    final class RoomAccessService { public bool $allowed=false; public function canView():bool{return $this->allowed;} }
    final class OrganizationGroupPolicyService { public bool $configurator=false; public function canConfigure():bool{return $this->configurator;} }
}
namespace {
    use OCA\FlzRoom\Controller\PageController;
    use OCA\FlzRoom\Service\OrganizationGroupPolicyService;
    use OCA\FlzRoom\Service\RoomAccessService;
    use OCA\FlzRoom\Service\RoomRetentionPolicyService;
    use OCA\FlzRoom\Service\TemporaryAdminAccessService;
    use OCP\AppFramework\Http;

    $access=new RoomAccessService();
    $groups=new OrganizationGroupPolicyService();
    $controller=new PageController(new class implements OCP\IRequest{},new TemporaryAdminAccessService(),new RoomRetentionPolicyService(),$access,$groups);
    $denied=$controller->index();
    if(!$denied instanceof OCP\AppFramework\Http\JSONResponse||$denied->getStatus()!==Http::STATUS_FORBIDDEN)throw new RuntimeException('Direkter Seitenzugriff bleibt für gewöhnliche Konten offen.');
    $groups->configurator=true;
    $configurationPage=$controller->index();
    if(!$configurationPage instanceof OCP\AppFramework\Http\TemplateResponse||($configurationPage->params['canViewRoomPlan']??true)!==false||($configurationPage->params['canConfigureOrganizationGroups']??false)!==true)throw new RuntimeException('Datenschutzbeauftragte erreichen die Gruppensteuerung nicht getrennt von Raumdaten.');
    $groups->configurator=false;
    $access->allowed=true;
    $roomPage=$controller->index();
    if(!$roomPage instanceof OCP\AppFramework\Http\TemplateResponse||($roomPage->params['canViewRoomPlan']??false)!==true)throw new RuntimeException('Konfigurierte Organisationskräfte erreichen den Raumplan nicht.');

    echo "Filzmann Raumplaner page access tests passed\n";
}
