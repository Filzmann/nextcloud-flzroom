<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Controller;

use OCA\FlzRoom\AppInfo\Application;
use OCA\FlzRoom\Exception\BookingConflictException;
use OCA\FlzRoom\Service\BookingService;
use OCA\FlzRoom\Service\RoomAccessService;
use OCA\FlzRoom\Service\RoomService;
use OCA\FlzRoom\Service\SecretariatBookingInterventionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Zweck: Stellt den abgesicherten JSON-Vertrag für Monatsplan, Buchungen und Raumstammdaten bereit.
 * Zusammenspiel: Controller -> RoomAccessService -> BookingService/RoomService.
 * Vertrag: Nur die Monatsabfrage ist CSRF-frei; jeder Schreibpfad prüft Eigen- oder Adminrechte serverseitig.
 */
final class ApiController extends Controller {
    public function __construct(
        IRequest $request,
        private RoomAccessService $access,
        private BookingService $bookings,
        private SecretariatBookingInterventionService $interventions,
        private RoomService $rooms,
        private LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function month(string $month): JSONResponse {
        if (!$this->access->canView()) return $this->denied();

        try {
            return new JSONResponse($this->bookings->month($month, $this->access));
        } catch (\Throwable $error) {
            $this->logger->error('Raummonat konnte nicht geladen werden.', ['exception' => $error]);
            return $this->error('Der Raummonat konnte nicht geladen werden.', Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoAdminRequired]
    public function createBooking(int $roomId, string $start, string $end, string $purpose, string $title): JSONResponse {
        if (!$this->access->canView()) return $this->denied();

        try {
            return new JSONResponse([
                'id' => $this->bookings->create(
                    $roomId,
                    $start,
                    $end,
                    $purpose,
                    $title,
                    $this->access->currentUid(),
                ),
            ], Http::STATUS_CREATED);
        } catch (BookingConflictException $error) {
            return $this->error($error->getMessage(), Http::STATUS_CONFLICT);
        } catch (\OutOfBoundsException $error) {
            return $this->error($error->getMessage(), Http::STATUS_NOT_FOUND);
        } catch (\Throwable $error) {
            $this->logger->warning('Raumbuchung wurde abgelehnt.', ['exception' => $error]);
            return $this->error('Die Buchung ist ungültig.', Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoAdminRequired]
    public function updateBooking(int $id, int $roomId, string $start, string $end, string $purpose, string $title, string $reason = ''): JSONResponse {
        try {
            $booking = $this->bookings->existing($id);
            if ($this->access->canManageBooking($booking)) {
                return new JSONResponse([
                    'id' => $this->bookings->update($booking, $roomId, $start, $end, $purpose, $title),
                ]);
            }
            return new JSONResponse([
                'id' => $this->interventions->update($id, $roomId, $start, $end, $purpose, $title, $reason),
            ]);
        } catch (\DomainException $error) {
            return $this->denied();
        } catch (BookingConflictException $error) {
            return $this->error($error->getMessage(), Http::STATUS_CONFLICT);
        } catch (\OutOfBoundsException $error) {
            return $this->error($error->getMessage(), Http::STATUS_NOT_FOUND);
        } catch (\Throwable $error) {
            $this->logger->warning('Raumbuchung konnte nicht aktualisiert werden.', ['exception' => $error]);
            return $this->error('Die Buchung ist ungültig.', Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoAdminRequired]
    public function deleteBooking(int $id, string $reason = ''): JSONResponse {
        try {
            $booking = $this->bookings->existing($id);
            if ($this->access->canManageBooking($booking)) {
                $this->bookings->delete($id);
                return new JSONResponse(['deleted' => true]);
            }
            $this->interventions->delete($id, $reason);
            return new JSONResponse(['deleted' => true]);
        } catch (\DomainException $error) {
            return $this->denied();
        } catch (\OutOfBoundsException $error) {
            return $this->error($error->getMessage(), Http::STATUS_NOT_FOUND);
        } catch (\Throwable $error) {
            $this->logger->error('Raumbuchung konnte nicht gelöscht werden.', ['exception' => $error]);
            return $this->error('Die Buchung konnte nicht gelöscht werden.', Http::STATUS_BAD_REQUEST);
        }
    }

    public function createRoom(string $name, string $description = '', int $sortOrder = 0): JSONResponse {
        if (!$this->access->canManageRooms()) return $this->denied();

        try {
            return new JSONResponse([
                'id' => $this->rooms->save(null, $name, $description, $sortOrder),
            ], Http::STATUS_CREATED);
        } catch (\Throwable $error) {
            $this->logger->warning('Raum konnte nicht angelegt werden.', ['exception' => $error]);
            return $this->error('Der Raum konnte nicht gespeichert werden.', Http::STATUS_BAD_REQUEST);
        }
    }

    public function updateRoom(int $id, string $name, string $description = '', int $sortOrder = 0): JSONResponse {
        if (!$this->access->canManageRooms()) return $this->denied();

        try {
            return new JSONResponse([
                'id' => $this->rooms->save($id, $name, $description, $sortOrder),
            ]);
        } catch (\OutOfBoundsException $error) {
            return $this->error($error->getMessage(), Http::STATUS_NOT_FOUND);
        } catch (\Throwable $error) {
            $this->logger->warning('Raum konnte nicht aktualisiert werden.', ['exception' => $error]);
            return $this->error('Der Raum konnte nicht gespeichert werden.', Http::STATUS_BAD_REQUEST);
        }
    }

    public function deleteRoom(int $id): JSONResponse {
        if (!$this->access->canManageRooms()) return $this->denied();

        try {
            $this->rooms->delete($id);
            return new JSONResponse(['deleted' => true]);
        } catch (\OutOfBoundsException $error) {
            return $this->error($error->getMessage(), Http::STATUS_NOT_FOUND);
        } catch (\Throwable $error) {
            $this->logger->error('Raum konnte nicht gelöscht werden.', ['exception' => $error]);
            return $this->error('Der Raum konnte nicht gelöscht werden.', Http::STATUS_BAD_REQUEST);
        }
    }

    private function denied(): JSONResponse {
        return $this->error('Keine Berechtigung.', Http::STATUS_FORBIDDEN);
    }

    private function error(string $message, int $status): JSONResponse {
        return new JSONResponse(['message' => $message], $status);
    }
}
