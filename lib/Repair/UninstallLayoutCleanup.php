<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Repair;

use OCA\FlzRoom\Service\RoomAdminLayoutService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Uninstall-Repair-Step: entfernt beim App-Uninstall (bzw. Deaktivieren, da
 * Nextcloud app:remove über den Disable-Pfad die Uninstall-Steps ausführt)
 * sämtliche persönlichen Admin-Layouts der App.
 */
final class UninstallLayoutCleanup implements IRepairStep {
    public function __construct(private RoomAdminLayoutService $layout) {
    }

    public function getName(): string {
        return 'Persönliche Raumplaner-Adminlayouts entfernen';
    }

    public function run(IOutput $output): void {
        $this->layout->deleteAll();
        $output->info('Persönliche Admin-Layouts des Raumplaners wurden entfernt.');
    }
}
