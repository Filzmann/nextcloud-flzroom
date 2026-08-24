<?php

declare(strict_types=1);

namespace OCP\Config {
    interface IUserConfig {
        public function getValueArray(string $userId, string $appId, string $key, array $default = [], bool $lazy = false): array;
        public function setValueArray(string $userId, string $appId, string $key, array $value, bool $lazy = false): void;
    }
}
namespace Psr\Log { interface LoggerInterface { public function warning(string $message, array $context=[]):void; } }

namespace {
    use OCA\AdRoom\Service\RoomAdminLayoutService;

    $config = new class implements OCP\Config\IUserConfig {
        public array $values=[];
        public function getValueArray(string $userId,string $appId,string $key,array $default=[],bool $lazy=false):array{return $this->values[$userId][$appId][$key]??$default;}
        public function setValueArray(string $userId,string $appId,string $key,array $value,bool $lazy=false):void{$this->values[$userId][$appId][$key]=$value;}
    };
    $logger = new class implements Psr\Log\LoggerInterface { public array $warnings=[]; public function warning(string $message,array $context=[]):void{$this->warnings[]=$message;} };
    $service = new RoomAdminLayoutService($config,$logger);
    $default = ['version'=>1,'scopes'=>['main'=>['order'=>['rooms','retention','demo'],'collapsed'=>[]]],'organigram'=>['zoom'=>100]];
    if ($service->layout('admin') !== $default) throw new RuntimeException('Admin-Karten besitzen kein vollständiges Standardlayout.');
    $saved = $service->save('admin',['version'=>1,'scopes'=>['main'=>['order'=>['retention','rooms','demo'],'collapsed'=>['demo']]],'organigram'=>['zoom'=>100]]);
    if ($saved['scopes']['main']['order'][0] !== 'retention' || $saved['scopes']['main']['collapsed'] !== ['demo']) throw new RuntimeException('Persönliche Kartenanordnung wird nicht gespeichert.');
    try {
        $service->save('admin',['version'=>1,'scopes'=>['main'=>['order'=>['unknown'],'collapsed'=>[]]],'organigram'=>['zoom'=>100]]);
        throw new RuntimeException('Unbekannte Admin-Karte wurde akzeptiert.');
    } catch (InvalidArgumentException) {
    }
    if ($service->layout('other') !== $default) throw new RuntimeException('Persönliche Layouts sind nicht getrennt.');
    echo "AD Raumplaner admin layout test passed\n";
}
