<?php

declare(strict_types=1);

namespace OCP\AppFramework\Utility { interface ITimeFactory { public function now(): \DateTimeImmutable; } }
namespace OCP\BackgroundJob {
    abstract class TimedJob {
        public int $interval=0;
        public function __construct(\OCP\AppFramework\Utility\ITimeFactory $time){}
        protected function setInterval(int $interval):void{$this->interval=$interval;}
        abstract protected function run($argument):void;
        public function execute():void{$this->run(null);}
    }
}
namespace OCA\AdRoom\Repository {
    final class InterventionNotificationQueueRepository { public array $deleted=[]; public function due(\DateTimeImmutable $now,int $limit):array{return [['id'=>7],['id'=>8]];} public function deletePermanentlyFailedBefore(\DateTimeImmutable $cutoff):int{$this->deleted[]=$cutoff;return 1;} }
    final class BookingInterventionAuditRepository { public array $deleted=[]; public function deleteOlderThan(\DateTimeImmutable $cutoff):int{$this->deleted[]=$cutoff;return 1;} }
}
namespace OCA\AdRoom\Service { final class InterventionNotificationDeliveryService { public array $ids=[]; public function deliverById(int $id):void{$this->ids[]=$id;} } }
namespace {
    $now=new DateTimeImmutable('2026-09-29T10:00:00+00:00');
    $time=new class($now) implements \OCP\AppFramework\Utility\ITimeFactory{public function __construct(private DateTimeImmutable $now){}public function now():DateTimeImmutable{return $this->now;}};
    $queue=new \OCA\AdRoom\Repository\InterventionNotificationQueueRepository();$audit=new \OCA\AdRoom\Repository\BookingInterventionAuditRepository();$delivery=new \OCA\AdRoom\Service\InterventionNotificationDeliveryService();
    $job=new \OCA\AdRoom\BackgroundJob\InterventionNotificationJob($time,$queue,$audit,$delivery);$job->execute();
    if($job->interval!==60||$delivery->ids!==[7,8])throw new RuntimeException('Queuejob verarbeitet fällige Zustellungen nicht minütlich.');
    if(($audit->deleted[0]??null)!=$now->sub(new DateInterval('P1Y')))throw new RuntimeException('Audit wird nicht exakt nach zwölf Monaten bereinigt.');
    if(($queue->deleted[0]??null)!=$now->sub(new DateInterval('P30D')))throw new RuntimeException('Dauerhaft fehlgeschlagene Queue wird nicht nach dreißig Tagen bereinigt.');
    $application=(string)file_get_contents(dirname(__DIR__,2).'/lib/AppInfo/Application.php');
    if(!str_contains($application,'registerNotifierService(Notifier::class)'))throw new RuntimeException('Nativer Nextcloud-Notifier ist nicht registriert.');
    echo "Intervention notification job tests passed\n";
}
