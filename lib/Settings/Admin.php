<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Settings;

use OCA\FlzRoom\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

/** Zweck: Bindet die ausschließlich appbezogenen Raumstammdaten in den eigenen Adminabschnitt ein. */
final class Admin implements ISettings {
    public function getForm(): TemplateResponse {
        return new TemplateResponse(Application::APP_ID, 'admin');
    }

    public function getSection(): string {
        return Application::APP_ID;
    }

    public function getPriority(): int {
        return 30;
    }
}
