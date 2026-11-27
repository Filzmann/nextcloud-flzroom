<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Controller;

use OCA\FlzRoom\AppInfo\Application;
use OCA\FlzRoom\Service\RoomDemoPackService;
use OCA\FlzRoom\Service\RoomAccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/** Zweck: Startet den Raum-Demo-Pack ausschließlich mit Admin- und CSRF-Schutz. */
final class DemoAdminController extends Controller {
    public function __construct(IRequest $request, private RoomAccessService $access, private RoomDemoPackService $demoPack, private LoggerInterface $logger, private ?IL10N $l10n = null) { parent::__construct(Application::APP_ID, $request); }

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return $this->l10n?->t($text) ?? $text;
    }
    public function install(): JSONResponse {
        if (!$this->access->canManageAllBookings()) return new JSONResponse(['error' => $this->t('Keine Berechtigung.')], Http::STATUS_FORBIDDEN);
        try {
            return new JSONResponse(['result' => $this->demoPack->install()]);
        } catch (\Throwable $error) {
            $this->logger->error('Raum-Demo-Pack konnte nicht installiert werden.', ['exception' => $error]);
            return new JSONResponse(['error' => $this->t($error->getMessage())], Http::STATUS_BAD_REQUEST);
        }
    }
}
