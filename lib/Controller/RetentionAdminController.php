<?php

declare(strict_types=1);

namespace OCA\AdRoom\Controller;

use OCA\AdRoom\AppInfo\AppId;
use OCA\AdRoom\Service\RoomAccessService;
use OCA\AdRoom\Service\RoomAdminLayoutService;
use OCA\AdRoom\Service\RoomRetentionPolicyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class RetentionAdminController extends Controller {
    public function __construct(
        IRequest $request,
        private RoomAccessService $access,
        private RoomRetentionPolicyService $policy,
        private RoomAdminLayoutService $layout,
        private LoggerInterface $logger,
    ) { parent::__construct(AppId::VALUE, $request); }

    #[NoCSRFRequired]
    public function settings(): JSONResponse {
        if (!$this->access->canManageRooms()) return $this->denied();
        return new JSONResponse([
            'retentionPolicy' => $this->policy->policy(),
            'dashboardLayout' => $this->layout->layout($this->access->currentUser()->getUID()),
        ]);
    }

    public function savePolicy(bool $enabled, int $reviewAfterDays, string $action): JSONResponse {
        if (!$this->access->canManageRooms()) return $this->denied();
        try {
            return new JSONResponse(['retentionPolicy' => $this->policy->save(compact('enabled','reviewAfterDays','action'))]);
        } catch (\Throwable $error) {
            $this->logger->warning('Retention-Regel des Raumplaners wurde abgelehnt.', ['exception'=>$error]);
            return new JSONResponse(['message'=>'Die Retention-Regel ist ungültig.'], Http::STATUS_BAD_REQUEST);
        }
    }

    public function saveLayout(array $layout): JSONResponse {
        if (!$this->access->canManageRooms()) return $this->denied();
        try {
            return new JSONResponse(['dashboardLayout'=>$this->layout->save($this->access->currentUser()->getUID(),$layout)]);
        } catch (\Throwable $error) {
            $this->logger->warning('Persönliches Raumplaner-Adminlayout wurde abgelehnt.', ['exception'=>$error]);
            return new JSONResponse(['message'=>'Das persönliche Kartenlayout ist ungültig.'], Http::STATUS_BAD_REQUEST);
        }
    }

    private function denied(): JSONResponse { return new JSONResponse(['message'=>'Keine Berechtigung.'],Http::STATUS_FORBIDDEN); }
}
