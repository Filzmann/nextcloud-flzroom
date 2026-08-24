<?php
declare(strict_types=1);
namespace OCA\AdRoom\Permission;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\{PermissionCondition,PermissionProvider,PermissionProviderDescriptor,PermissionProviderResult,PermissionRule};
final class RoomPermissionProvider implements PermissionProvider{
 public function descriptor():PermissionProviderDescriptor{return new PermissionProviderDescriptor('adroom','AD Raumplaner','1.0',['permissions']);}
 public function collect():PermissionProviderResult{return new PermissionProviderResult([
  $this->rule('Raum und Buchung','Monatsplan','Alle Räume und Buchungen lesen','room.read','Lesen','app',PermissionCondition::authenticated()),
  $this->rule('Buchung','Eigene Buchung','Anlegen, ändern, verschieben und löschen','booking.manage-own','Eigene Buchung verwalten','own-booking',PermissionCondition::self()),
  $this->rule('Buchung','Alle Buchungen','Fremde Buchungen verwalten','booking.manage-all','Alle Buchungen verwalten','all-bookings',PermissionCondition::nextcloudAdmin()),
  $this->rule('Raum','Raumliste','Räume einschließlich folgenreicher Löschung verwalten','room.manage','Räume verwalten','all-rooms',PermissionCondition::nextcloudAdmin()),
 ]);}
 private function rule(string $t,string $n,string $d,string $k,string $l,string $s,PermissionCondition $c):PermissionRule{return new PermissionRule($t,$n,$d,$k,$l,'allow',$s,$c,'adroom:RoomAccessService','high');}
}
