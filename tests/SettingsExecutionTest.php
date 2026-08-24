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

namespace OCA\AdRoom\AppInfo {
    final class Application { public const APP_ID = 'adroom'; }
}

namespace {
    use OCA\AdRoom\Settings\Admin;
    use OCA\AdRoom\Settings\AdminSection;
    use OCP\IURLGenerator;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    $admin = new Admin();
    $form = $admin->getForm();
    $assert($form->appName === 'adroom' && $form->templateName === 'admin', 'The admin form points to the wrong template.');
    $assert($admin->getSection() === 'adroom', 'The admin form points to the wrong section.');
    $assert($admin->getPriority() === 30, 'The admin form priority changed unexpectedly.');

    $url = new class implements IURLGenerator {
        public function imagePath($app, $file): string { return "{$app}/img/{$file}"; }
    };
    $section = new AdminSection($url);
    $assert($section->getIcon() === 'adroom/img/app.svg', 'The admin section points to the wrong icon.');
    $assert($section->getID() === 'adroom', 'The admin section exposes the wrong id.');
    $assert($section->getName() === 'AD Raumplaner', 'The admin section exposes the wrong name.');
    $assert($section->getPriority() === 65, 'The admin section priority changed unexpectedly.');

    echo "AD Raumplaner settings execution tests passed\n";
}
