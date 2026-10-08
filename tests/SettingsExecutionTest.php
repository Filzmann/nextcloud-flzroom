<?php

declare(strict_types=1);

namespace OCP {
    interface IURLGenerator { public function imagePath($app, $file); }
}

namespace OCP\Settings {
    interface IIconSection {
        public function getIcon();
        public function getID();
        public function getName();
        public function getPriority();
    }
    interface ISettings {
        public function getForm();
        public function getSection();
        public function getPriority();
    }
}

namespace OCP\AppFramework\Http {
    final class TemplateResponse {
        public function __construct(public string $appName, public string $templateName) {}
    }
}

namespace OCA\FlzRoom\AppInfo {
    final class Application { public const APP_ID = 'flzroom'; }
}

namespace {
    use OCA\FlzRoom\Settings\Admin;
    use OCA\FlzRoom\Settings\AdminSection;
    use OCP\IURLGenerator;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    $admin = new Admin();
    $form = $admin->getForm();
    $assert($form->appName === 'flzroom' && $form->templateName === 'admin', 'The admin form points to the wrong template.');
    $assert($admin->getSection() === 'flzroom', 'The admin form points to the wrong section.');
    $assert($admin->getPriority() === 30, 'The admin form priority changed unexpectedly.');

    $url = new class implements IURLGenerator {
        public function imagePath($app, $file): string { return "{$app}/img/{$file}"; }
    };
    $section = new AdminSection($url);
    $assert($section->getIcon() === 'flzroom/img/app.svg', 'The admin section points to the wrong icon.');
    $assert($section->getID() === 'flzroom', 'The admin section exposes the wrong id.');
    $assert($section->getName() === 'Filzmann Raumplaner', 'The admin section exposes the wrong name.');
    $assert($section->getPriority() === 65, 'The admin section priority changed unexpectedly.');

    echo "Filzmann Raumplaner settings execution tests passed\n";
}
