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
    use OCA\FlzRoom\Service\RoomAdminLayoutService;

    $config = new class implements OCP\Config\IUserConfig {
        public array $values=[];
        public function getValueArray(string $userId,string $appId,string $key,array $default=[],bool $lazy=false):array{return $this->values[$userId][$appId][$key]??$default;}
        public function setValueArray(string $userId,string $appId,string $key,array $value,bool $lazy=false):void{$this->values[$userId][$appId][$key]=$value;}
    };
    $logger = new class implements Psr\Log\LoggerInterface { public array $warnings=[]; public function warning(string $message,array $context=[]):void{$this->warnings[]=$message;} };
    $service = new RoomAdminLayoutService($config,$logger);
    $default = ['version'=>1,'scopes'=>['main'=>['order'=>['rooms','demo'],'collapsed'=>[]]],'organigram'=>['zoom'=>100]];
    if ($service->layout('admin') !== $default) throw new RuntimeException('Admin-Karten besitzen kein vollständiges Standardlayout.');
    $saved = $service->save('admin',['version'=>1,'scopes'=>['main'=>['order'=>['demo','rooms'],'collapsed'=>['demo']]],'organigram'=>['zoom'=>100]]);
    if ($saved['scopes']['main']['order'][0] !== 'demo' || $saved['scopes']['main']['collapsed'] !== ['demo']) throw new RuntimeException('Persönliche Kartenanordnung wird nicht gespeichert.');
    if ($service->personalDataForUid('admin') !== $saved) throw new RuntimeException('Gespeichertes Adminlayout ist nicht subjectgebunden projizierbar.');
    if ($service->personalDataForUid('other') !== null) throw new RuntimeException('Ein nicht gespeichertes Standardlayout wurde als Personendate projiziert.');
    $config->values['legacy']['flzroom']['admin_dashboard_layout'] = ['version'=>1,'scopes'=>['main'=>['order'=>['retention','demo','rooms'],'collapsed'=>['retention','demo']]],'organigram'=>['zoom'=>100]];
    if ($service->layout('legacy') !== ['version'=>1,'scopes'=>['main'=>['order'=>['demo','rooms'],'collapsed'=>['demo']]],'organigram'=>['zoom'=>100]]) throw new RuntimeException('Alte Retention-Karte wird nicht verlustarm aus dem persönlichen Layout entfernt.');
    try {
        $service->save('admin',['version'=>1,'scopes'=>['main'=>['order'=>['unknown'],'collapsed'=>[]]],'organigram'=>['zoom'=>100]]);
        throw new RuntimeException('Unbekannte Admin-Karte wurde akzeptiert.');
    } catch (InvalidArgumentException) {
    }
    $config->values['broken']['flzroom']['admin_dashboard_layout'] = ['version'=>2];
    try {
        $service->personalDataForUid('broken');
        throw new RuntimeException('Ungültiges persönliches Layout wurde als fehlender Wert behandelt.');
    } catch (InvalidArgumentException) {
    }
    if ($logger->warnings === []) throw new RuntimeException('Ungültiges persönliches Layout wird nicht diagnostizierbar ausgeschlossen.');
    if ($service->layout('other') !== $default) throw new RuntimeException('Persönliche Layouts sind nicht getrennt.');
    echo "Filzmann Raumplaner admin layout test passed\n";
}
