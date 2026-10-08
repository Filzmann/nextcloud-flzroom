<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Controller;

use DomainException;
use OCA\FlzRoom\AppInfo\AppId;
use OCA\FlzRoom\Service\OrganizationGroupPolicyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/** Datenschutzrollen-geschützte Verwaltung der organisatorischen Nextcloud-Gruppen. */
final class OrganizationGroupController extends Controller {
    public function __construct(
        IRequest $request,
        private OrganizationGroupPolicyService $policy,
        private LoggerInterface $logger,
    ) {
        parent::__construct(AppId::VALUE, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function configuration(): JSONResponse {
        if (!$this->policy->canConfigure()) return $this->denied();
        return new JSONResponse(['organizationGroupIds' => $this->policy->organizationGroupIds()]);
    }

    #[NoAdminRequired]
    public function save(array $organizationGroupIds): JSONResponse {
        if (!$this->policy->canConfigure()) return $this->denied();
        try {
            return new JSONResponse(['organizationGroupIds' => $this->policy->save($organizationGroupIds)]);
        } catch (DomainException) {
            return $this->denied();
        } catch (\Throwable $error) {
            $this->logger->warning('Organisationsgruppen des Raumplaners wurden abgelehnt.', ['exceptionClass' => $error::class]);
            return new JSONResponse(['message' => 'Die Organisationsgruppen sind ungültig.'], Http::STATUS_BAD_REQUEST);
        }
    }

    private function denied(): JSONResponse {
        return new JSONResponse(['message' => 'Keine Berechtigung.'], Http::STATUS_FORBIDDEN);
    }
}
