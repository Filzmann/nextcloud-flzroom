<?php

declare(strict_types=1);

namespace OCP\Config {
    interface IUserConfig {
        public function getValueArray(string $userId, string $appId, string $key, array $default = [], bool $lazy = false): array;
        public function setValueArray(string $userId, string $appId, string $key, array $value, bool $lazy = false): void;
        public function deleteUserConfig(string $userId, string $app, string $key): void;
        public function deleteApp(string $app): void;
    }
}
namespace OCP\EventDispatcher {
    class Event {
        public function __construct() {
        }
    }
    interface IEventListener {
        public function handle(Event $event): void;
    }
}
namespace OCP {
    interface IUser {
        public function getUID(): string;
    }
}
namespace OCP\User\Events {
    class UserDeletedEvent extends \OCP\EventDispatcher\Event {
        public function __construct(private \OCP\IUser $user) {
            parent::__construct();
        }
        public function getUser(): \OCP\IUser {
            return $this->user;
        }
    }
}
namespace OCP\Migration {
    interface IOutput {
        public function info(string $message): void;
    }
    interface IRepairStep {
        public function getName(): string;
        public function run(IOutput $output): void;
    }
}
namespace Psr\Log {
    interface LoggerInterface {
        public function warning(string $message, array $context = []): void;
    }
}

namespace {
    use OCA\FlzRoom\Listener\UserDeletedLayoutListener;
    use OCA\FlzRoom\Repair\UninstallLayoutCleanup;
    use OCA\FlzRoom\Service\RoomAdminLayoutService;
    use OCP\Config\IUserConfig;
    use OCP\EventDispatcher\Event;
    use OCP\Migration\IOutput;
    use OCP\User\Events\UserDeletedEvent;

    $config = new class implements IUserConfig {
        public array $values = [];
        public function getValueArray(string $userId, string $appId, string $key, array $default = [], bool $lazy = false): array {
            return $this->values[$userId][$appId][$key] ?? $default;
        }
        public function setValueArray(string $userId, string $appId, string $key, array $value, bool $lazy = false): void {
            $this->values[$userId][$appId][$key] = $value;
        }
        public function deleteUserConfig(string $userId, string $app, string $key): void {
            unset($this->values[$userId][$app][$key]);
        }
        public function deleteApp(string $app): void {
            foreach ($this->values as $uid => &$apps) {
                unset($apps[$app]);
            }
        }
    };
    $logger = new class implements Psr\Log\LoggerInterface {
        public function warning(string $message, array $context = []): void {
        }
    };
    $service = new RoomAdminLayoutService($config, $logger);
    $layout = ['version' => 1, 'scopes' => ['main' => ['order' => ['demo', 'rooms'], 'collapsed' => []]], 'organigram' => ['zoom' => 100]];
    $service->save('admin', $layout);
    $service->save('other', $layout);

    $listener = new UserDeletedLayoutListener($service);
    $listener->handle(new Event());
    if (!isset($config->values['admin']['flzroom']['admin_dashboard_layout'])) {
        throw new RuntimeException('Fremde Events dürfen das Layout nicht löschen.');
    }
    $listener->handle(new UserDeletedEvent(new class implements \OCP\IUser {
        public function getUID(): string {
            return 'admin';
        }
    }));
    if (isset($config->values['admin']['flzroom']['admin_dashboard_layout'])) {
        throw new RuntimeException('Kontolöschung löscht das Layout des Kontoinhabers nicht.');
    }
    if (!isset($config->values['other']['flzroom']['admin_dashboard_layout'])) {
        throw new RuntimeException('Kontolöschung darf fremde Layouts nicht löschen.');
    }

    $output = new class implements IOutput {
        public array $infos = [];
        public function info(string $message): void {
            $this->infos[] = $message;
        }
    };
    $step = new UninstallLayoutCleanup($service);
    $step->run($output);
    if (($config->values['other']['flzroom'] ?? null) !== null || $output->infos === []) {
        throw new RuntimeException('Uninstall-Repair-Step löscht nicht alle App-Layouts.');
    }

    echo "Filzmann Raumplaner layout lifecycle test passed\n";
}
