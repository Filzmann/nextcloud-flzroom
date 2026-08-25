<?php

declare(strict_types=1);

namespace OCA\AdRoom\Controller;

use OCA\AdRoom\AppInfo\Application;
use OCA\AdRoom\Service\RoomDemoPackService;
use OCA\AdRoom\Service\RoomAccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/** Zweck: Startet den Raum-Demo-Pack ausschließlich mit Admin- und CSRF-Schutz. */
final class DemoAdminController extends Controller {
    public function __construct(IRequest $request, private RoomAccessService $access, private RoomDemoPackService $demoPack, private LoggerInterface $logger) { parent::__construct(Application::APP_ID, $request); }
    public function install(): JSONResponse {
        if (!$this->access->canManageRooms()) return new JSONResponse(['error' => 'Keine Berechtigung.'], Http::STATUS_FORBIDDEN);
        try {
            return new JSONResponse(['result' => $this->demoPack->install()]);
        } catch (\Throwable $error) {
            $this->logger->error('Raum-Demo-Pack konnte nicht installiert werden.', ['exception' => $error]);
            return new JSONResponse(['error' => $error->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }
}
