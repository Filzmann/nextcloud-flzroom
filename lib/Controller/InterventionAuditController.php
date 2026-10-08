<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Controller;

use DateTimeInterface;
use OCA\FlzRoom\AppInfo\AppId;
use OCA\FlzRoom\Repository\BookingInterventionAuditRepository;
use OCA\FlzRoom\Service\OrganizationGroupPolicyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/** Read-only DPO audit projection; customer-local BR review stays outside app role mapping. */
final class InterventionAuditController extends Controller {
    public function __construct(
        IRequest $request,
        private OrganizationGroupPolicyService $privacyRole,
        private BookingInterventionAuditRepository $audit,
    ) {
        parent::__construct(AppId::VALUE, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(int $limit = 50, int $offset = 0): JSONResponse {
        if (!$this->privacyRole->canConfigure()) {
            return new JSONResponse(['message' => 'Keine Berechtigung.'], Http::STATUS_FORBIDDEN);
        }
        if ($limit < 1 || $limit > 200 || $offset < 0) {
            return new JSONResponse(['message' => 'Die Paginierung ist ungültig.'], Http::STATUS_BAD_REQUEST);
        }
        $entries = array_map(static function (array $entry): array {
            foreach ($entry as $key => $value) {
                if ($value instanceof DateTimeInterface) $entry[$key] = $value->format(DATE_ATOM);
            }
            return $entry;
        }, $this->audit->recent($limit, $offset));
        return new JSONResponse(['entries' => $entries, 'offset' => $offset, 'limit' => $limit]);
    }
}
