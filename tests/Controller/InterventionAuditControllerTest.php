<?php

declare(strict_types=1);

namespace OCP { interface IRequest {} }
namespace OCP\AppFramework { class Controller { public function __construct(string $appName, \OCP\IRequest $request) {} } final class Http { public const STATUS_BAD_REQUEST=400; public const STATUS_FORBIDDEN=403; } }
namespace OCP\AppFramework\Http { final class JSONResponse { public function __construct(private array $data=[],private int $status=200){} public function getData():array{return $this->data;} public function getStatus():int{return $this->status;} } }
namespace OCP\AppFramework\Http\Attribute { #[\Attribute(\Attribute::TARGET_METHOD)] final class NoAdminRequired{} #[\Attribute(\Attribute::TARGET_METHOD)] final class NoCSRFRequired{} }
namespace OCA\FlzRoom\AppInfo { final class AppId { public const VALUE='flzroom'; } }
namespace OCA\FlzRoom\Service { final class OrganizationGroupPolicyService { public bool $allowed=false; public function canConfigure():bool{return $this->allowed;} } }
namespace OCA\FlzRoom\Repository { final class BookingInterventionAuditRepository { public int $calls=0; public function recent(int $limit,int $offset=0):array{$this->calls++;return [['id'=>7,'actorUid'=>'secretariat-user','reason'=>'Organisatorisch abgestimmt.']];} } }

namespace {
    use OCA\FlzRoom\Controller\InterventionAuditController;
    use OCA\FlzRoom\Repository\BookingInterventionAuditRepository;
    use OCA\FlzRoom\Service\OrganizationGroupPolicyService;
    use OCP\AppFramework\Http;
    $request=new class implements \OCP\IRequest{};
    $policy=new OrganizationGroupPolicyService();
    $audit=new BookingInterventionAuditRepository();
    $controller=new InterventionAuditController($request,$policy,$audit);
    $denied=$controller->index(50,0);
    if($denied->getStatus()!==Http::STATUS_FORBIDDEN||$audit->calls!==0)throw new RuntimeException('Audit ist außerhalb Datenschutzbeauftragte sichtbar.');
    $policy->allowed=true;
    $allowed=$controller->index(50,0);
    if($allowed->getStatus()!==200||($allowed->getData()['entries'][0]['id']??null)!==7)throw new RuntimeException('Datenschutzbeauftragte können das Audit nicht lesen.');
    foreach([[0,0],[201,0],[50,-1]] as [$limit,$offset])if($controller->index($limit,$offset)->getStatus()!==Http::STATUS_BAD_REQUEST)throw new RuntimeException('Ungültige Audit-Paginierung wurde akzeptiert.');
    echo "Intervention audit controller tests passed\n";
}
