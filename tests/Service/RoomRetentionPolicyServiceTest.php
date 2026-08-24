<?php

declare(strict_types=1);

namespace OCP {
    interface IAppConfig {
        public function getValueString(string $appId, string $key, string $default = ''): string;
        public function setValueString(string $appId, string $key, string $value): void;
    }
}

namespace {
    use OCA\AdRoom\Service\RoomRetentionPolicyService;

    $config = new class implements OCP\IAppConfig {
        public array $values = [];
        public function getValueString(string $appId, string $key, string $default = ''): string { return $this->values[$appId][$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->values[$appId][$key] = $value; }
    };
    $service = new RoomRetentionPolicyService($config);

    if ($service->policy() !== ['enabled' => false, 'reviewAfterDays' => 0, 'action' => 'REVIEW']) throw new RuntimeException('Retention muss ohne Konfiguration sicher deaktiviert sein.');
    $saved = $service->save(['enabled' => true, 'reviewAfterDays' => 365, 'action' => 'REVIEW']);
    if ($saved !== ['enabled' => true, 'reviewAfterDays' => 365, 'action' => 'REVIEW'] || $service->policy() !== $saved) throw new RuntimeException('Gültige Retention-Regel wird nicht kanonisch gespeichert.');

    $before = $config->values;
    foreach ([
        ['enabled' => true, 'reviewAfterDays' => -1, 'action' => 'REVIEW'],
        ['enabled' => true, 'reviewAfterDays' => 30, 'action' => 'DELETE'],
        ['enabled' => true, 'reviewAfterDays' => 30, 'action' => 'REVIEW', 'extra' => true],
    ] as $invalid) {
        try {
            $service->save($invalid);
            throw new RuntimeException('Ungültige Retention-Regel wurde akzeptiert.');
        } catch (InvalidArgumentException) {
        }
        if ($config->values !== $before) throw new RuntimeException('Abgelehnte Retention-Regel verändert AppConfig.');
    }

    $config->values['adroom']['retention_policy'] = '{kaputt';
    if ($service->policy()['enabled'] !== false) throw new RuntimeException('Defekte Bestandskonfiguration fällt nicht deny by default zurück.');

    echo "AD Raumplaner retention policy test passed\n";
}
