<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Settings;

use OCA\FlzRoom\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/** Zweck: Registriert den eigenen, nur für Nextcloud-Admins sichtbaren Raumplaner-Abschnitt. */
final class AdminSection implements IIconSection {
    public function __construct(private IURLGenerator $url) {}

    public function getIcon(): string {
        return $this->url->imagePath(Application::APP_ID, 'app.svg');
    }

    public function getID(): string {
        return Application::APP_ID;
    }

    public function getName(): string {
        return 'Filzmann Raumplaner';
    }

    public function getPriority(): int {
        return 65;
    }
}
