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
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
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
            'dashboardLayout' => $this->layout->layout($this->access->currentUser()->getUID()),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function policy(): JSONResponse {
        if (!$this->policy->canConfigure()) return $this->denied();
        return new JSONResponse(['retentionPolicy'=>$this->policy->policy(),'history'=>$this->policy->history()]);
    }

    #[NoAdminRequired]
    public function savePolicy(string $durationPeriod, string $adminHistoryDurationPeriod, int $expectedRevision): JSONResponse {
        if (!$this->policy->canConfigure()) return $this->denied();
        try {
            return new JSONResponse(['retentionPolicy' => $this->policy->save(compact('durationPeriod','adminHistoryDurationPeriod','expectedRevision'))]);
        } catch (\DomainException $error) {
            return new JSONResponse(['message'=>$error->getMessage()], Http::STATUS_CONFLICT);
        } catch (\Throwable $error) {
            $this->logger->warning('Retention-Regel des Raumplaners wurde abgelehnt.', ['exception'=>$error]);
            return new JSONResponse(['message'=>'Die Retention-Regel ist ungültig.'], Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoAdminRequired]
    public function reviewPolicy(int $expectedRevision): JSONResponse {
        if (!$this->policy->canConfigure()) return $this->denied();
        try { return new JSONResponse(['review'=>$this->policy->recordReview($expectedRevision),'retentionPolicy'=>$this->policy->policy()]); }
        catch (\DomainException $error) { return new JSONResponse(['message'=>$error->getMessage()],Http::STATUS_CONFLICT); }
        catch (\Throwable $error) { return new JSONResponse(['message'=>'Die Retention-Prüfung konnte nicht protokolliert werden.'],Http::STATUS_BAD_REQUEST); }
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
