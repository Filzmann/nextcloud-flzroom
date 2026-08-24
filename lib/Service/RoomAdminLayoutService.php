<?php

declare(strict_types=1);

namespace OCA\AdRoom\Service;

use InvalidArgumentException;
use OCA\AdRoom\AppInfo\AppId;
use OCP\Config\IUserConfig;
use Psr\Log\LoggerInterface;

final class RoomAdminLayoutService {
    private const KEY = 'admin_dashboard_layout';
    private const BLOCKS = ['rooms', 'retention', 'demo'];

    public function __construct(private IUserConfig $config, private LoggerInterface $logger) {}

    public function layout(string $uid): array {
        try {
            $stored = $this->config->getValueArray($uid, AppId::VALUE, self::KEY, [], true);
            return $stored === [] ? $this->default() : $this->normalize($stored);
        } catch (\Throwable $error) {
            $this->logger->warning('Persönliches Raumplaner-Adminlayout ist ungültig; Standard wird verwendet.', ['exception' => $error]);
            return $this->default();
        }
    }

    public function save(string $uid, array $layout): array {
        $normalized = $this->normalize($layout);
        $this->config->setValueArray($uid, AppId::VALUE, self::KEY, $normalized, true);
        return $normalized;
    }

    private function default(): array {
        return ['version'=>1,'scopes'=>['main'=>['order'=>self::BLOCKS,'collapsed'=>[]]],'organigram'=>['zoom'=>100]];
    }

    private function normalize(array $layout): array {
        if (($layout['version'] ?? null) !== 1 || array_diff(array_keys($layout), ['version','scopes','organigram']) !== []) throw new InvalidArgumentException('Adminlayout ist ungültig.');
        $main = $layout['scopes']['main'] ?? null;
        if (!is_array($main) || array_diff(array_keys($layout['scopes']), ['main']) !== [] || array_diff(array_keys($main), ['order','collapsed']) !== []) throw new InvalidArgumentException('Adminlayout-Bereich ist ungültig.');
        $order = $this->list($main['order'] ?? []);
        $collapsed = $this->list($main['collapsed'] ?? []);
        $order = array_merge($order, array_values(array_diff(self::BLOCKS, $order)));
        return ['version'=>1,'scopes'=>['main'=>['order'=>$order,'collapsed'=>$collapsed]],'organigram'=>['zoom'=>100]];
    }

    private function list(mixed $value): array {
        if (!is_array($value) || !array_is_list($value)) throw new InvalidArgumentException('Adminlayout-Liste ist ungültig.');
        $result=[];
        foreach ($value as $id) {
            if (!is_string($id) || !in_array($id,self::BLOCKS,true) || in_array($id,$result,true)) throw new InvalidArgumentException('Adminlayout enthält unbekannte oder doppelte Karten.');
            $result[]=$id;
        }
        return $result;
    }
}
