<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Controller;

use OCA\FlzRoom\AppInfo\Application;
use OCA\FlzRoom\Service\OrganizationGroupPolicyService;
use OCA\FlzRoom\Service\RoomAccessService;
use OCA\FlzRoom\Service\TemporaryAdminAccessService;
use OCA\FlzRoom\Service\RoomRetentionPolicyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

final class PageController extends Controller {
    public function __construct(
        IRequest $request,
        private TemporaryAdminAccessService $adminAccess,
        private RoomRetentionPolicyService $retentionPolicy,
        private RoomAccessService $access,
        private OrganizationGroupPolicyService $organizationGroups,
    ) { parent::__construct(Application::APP_ID,$request); }
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse|JSONResponse {
        $canViewRoomPlan = $this->access->canView();
        $canManageAdminAccess = $this->adminAccess->canManageGrants();
        $showMissingAdminGrant = $this->adminAccess->currentAdminNeedsGrant();
        $canConfigureRetention = $this->retentionPolicy->canConfigure();
        $canConfigureOrganizationGroups = $this->organizationGroups->canConfigure();
        if (!$canViewRoomPlan && !$canManageAdminAccess && !$showMissingAdminGrant && !$canConfigureRetention && !$canConfigureOrganizationGroups) {
            return new JSONResponse(['message' => 'Keine Berechtigung.'], Http::STATUS_FORBIDDEN);
        }
        return new TemplateResponse(Application::APP_ID, 'index', [
            'canViewRoomPlan' => $canViewRoomPlan,
            'canManageAdminAccess' => $canManageAdminAccess,
            'showMissingAdminGrant' => $showMissingAdminGrant,
            'showAdminAccessLink' => $canManageAdminAccess && $showMissingAdminGrant,
            'canConfigureRetention' => $canConfigureRetention,
            'canConfigureOrganizationGroups' => $canConfigureOrganizationGroups,
        ]);
    }
}
