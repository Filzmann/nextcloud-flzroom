<?php

declare(strict_types=1);

namespace OCP {
    interface IGroupManager { public function isAdmin($uid); }
    interface IRequest {}
    interface IUserSession { public function getUser(); }
}

namespace OCP\AppFramework {
    class Controller {
        public function __construct(string $appName, \OCP\IRequest $request) {}
    }
    final class Http {
        public const STATUS_BAD_REQUEST = 400;
        public const STATUS_FORBIDDEN = 403;
    }
}

namespace OCP\AppFramework\Http {
    final class JSONResponse {
        public function __construct(private array $data = [], private int $status = 200) {}
        public function getData(): array { return $this->data; }
        public function getStatus(): int { return $this->status; }
    }
}

namespace Psr\Log {
    interface LoggerInterface {
        public function error(string $message, array $context = []): void;
    }
}

namespace OCA\AdRoom\AppInfo {
    final class Application { public const APP_ID = 'adroom'; }
}

namespace OCA\AdRoom\Service {
    final class RoomDemoPackService {
        public bool $fail = false;
        public function install(): array {
            if ($this->fail) throw new \RuntimeException('Demo nicht verfügbar.');
            return ['rooms' => 3, 'createdBookings' => 2];
        }
    }
}

namespace {
    use OCA\AdRoom\Controller\DemoAdminController;
    use OCA\AdRoom\Service\RoomDemoPackService;
    use OCP\AppFramework\Http;
    use OCP\IGroupManager;
    use OCP\IRequest;
    use OCP\IUserSession;

    $request = new class implements IRequest {};
    $session = new class implements IUserSession {
        public ?object $user = null;
        public function getUser(): ?object { return $this->user; }
    };
    $groups = new class implements IGroupManager {
        public bool $admin = false;
        public function isAdmin($uid): bool { return $this->admin; }
    };
    $demoPack = new RoomDemoPackService();
    $logger = new class implements \Psr\Log\LoggerInterface {
        public array $errors = [];
        public function error(string $message, array $context = []): void { $this->errors[] = [$message, $context]; }
    };
    $controller = new DemoAdminController($request, $session, $groups, $demoPack, $logger);
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    $assert($controller->install()->getStatus() === Http::STATUS_FORBIDDEN, 'Anonymous users can install demo data.');
    $session->user = new class {
        public function getUID(): string { return 'anna'; }
    };
    $assert($controller->install()->getStatus() === Http::STATUS_FORBIDDEN, 'Non-admin users can install demo data.');

    $groups->admin = true;
    $response = $controller->install();
    $assert($response->getStatus() === 200, 'Admins cannot install demo data.');
    $assert($response->getData()['result']['rooms'] === 3, 'The demo result is not forwarded.');

    $demoPack->fail = true;
    $response = $controller->install();
    $assert($response->getStatus() === Http::STATUS_BAD_REQUEST, 'Demo failures do not return a safe client status.');
    $assert($response->getData()['error'] === 'Demo nicht verfügbar.', 'Demo failures lose their actionable message.');
    $assert($logger->errors[0][0] === 'Raum-Demo-Pack konnte nicht installiert werden.', 'Demo failures are not logged.');

    echo "AD Raumplaner demo admin controller tests passed\n";
}
