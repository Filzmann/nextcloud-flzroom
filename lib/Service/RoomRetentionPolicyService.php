<?php

declare(strict_types=1);

namespace OCA\AdRoom\Service;

use InvalidArgumentException;
use OCA\AdRoom\AppInfo\AppId;
use OCP\IAppConfig;
use Throwable;

final class RoomRetentionPolicyService {
    private const KEY = 'retention_policy';
    private const DEFAULT = ['enabled' => false, 'reviewAfterDays' => 0, 'action' => 'REVIEW'];

    public function __construct(private IAppConfig $config) {}

    public function policy(): array {
        $stored = $this->config->getValueString(AppId::VALUE, self::KEY, '');
        if ($stored === '') return self::DEFAULT;
        try {
            $decoded = json_decode($stored, true, flags: JSON_THROW_ON_ERROR);
            return $this->validate($decoded);
        } catch (Throwable) {
            return self::DEFAULT;
        }
    }

    public function save(array $policy): array {
        $validated = $this->validate($policy);
        $this->config->setValueString(AppId::VALUE, self::KEY, json_encode($validated, JSON_THROW_ON_ERROR));
        return $validated;
    }

    public function retentionCriteria(): string {
        $policy = $this->policy();
        if (!$policy['enabled']) return 'Keine aktive Frist; Retention-Vorschau ist administrativ deaktiviert.';
        return sprintf('Administrativer REVIEW %d Tage nach Ende der Buchung; keine automatische Löschung oder Anonymisierung.', $policy['reviewAfterDays']);
    }

    private function validate(mixed $policy): array {
        if (!is_array($policy) || array_diff(array_keys($policy), array_keys(self::DEFAULT)) !== [] || array_diff(array_keys(self::DEFAULT), array_keys($policy)) !== []) {
            throw new InvalidArgumentException('Retention-Regel enthält unbekannte oder fehlende Felder.');
        }
        if (!is_bool($policy['enabled']) || !is_int($policy['reviewAfterDays']) || $policy['reviewAfterDays'] < 0 || $policy['reviewAfterDays'] > 3650) {
            throw new InvalidArgumentException('Retention-Frist ist ungültig.');
        }
        if ($policy['action'] !== 'REVIEW') throw new InvalidArgumentException('Nur die freigegebene Maßnahme REVIEW ist verfügbar.');
        return ['enabled' => $policy['enabled'], 'reviewAfterDays' => $policy['reviewAfterDays'], 'action' => 'REVIEW'];
    }
}
