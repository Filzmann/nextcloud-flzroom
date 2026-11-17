<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Controller;

use DomainException;
use OCA\FlzRoom\AppInfo\AppId;
use OCA\FlzRoom\Service\RoomPermissionPolicyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/** DPO-protected assignment of app capabilities to semantic organization roles. */
final class PermissionSettingsController extends Controller {
    public function __construct(
        IRequest $request,
        private RoomPermissionPolicyService $policy,
        private LoggerInterface $logger,
    ) {
        parent::__construct(AppId::VALUE, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function configuration(): JSONResponse {
        if (!$this->policy->canConfigure()) return $this->denied();
        return new JSONResponse($this->policy->configuration());
    }

    #[NoAdminRequired]
    public function save(array $assignments): JSONResponse {
        if (!$this->policy->canConfigure()) return $this->denied();
        try {
            return new JSONResponse(['assignments' => $this->policy->save($assignments)]);
        } catch (DomainException) {
            return $this->denied();
        } catch (UnexpectedValueException $error) {
            $this->logger->warning('Organisationssnapshot des Raumplaners war beim Speichern der Rechte nicht verfügbar.', ['exceptionClass' => $error::class]);
            return new JSONResponse(['message' => 'Die Organigrammrollen sind derzeit nicht verfügbar.'], Http::STATUS_SERVICE_UNAVAILABLE);
        } catch (\Throwable $error) {
            $this->logger->warning('Rechtezuordnung des Raumplaners wurde abgelehnt.', ['exceptionClass' => $error::class]);
            return new JSONResponse(['message' => 'Die Rechtezuordnung ist ungültig.'], Http::STATUS_BAD_REQUEST);
        }
    }

    private function denied(): JSONResponse {
        return new JSONResponse(['message' => 'Keine Berechtigung.'], Http::STATUS_FORBIDDEN);
    }
}
