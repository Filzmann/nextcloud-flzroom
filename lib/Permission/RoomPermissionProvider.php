<?php
declare(strict_types=1);
namespace OCA\FlzRoom\Permission;
use OCA\FlzRoom\Service\OrganizationGroupPolicyService;
use OCA\FlzRoom\Service\TemporaryAdminAccessService;
use OCA\FlzPermissionMatrix\PublicApi\V1\{PermissionCondition,PermissionProvider,PermissionProviderDescriptor,PermissionProviderResult,PermissionRule};
final class RoomPermissionProvider implements PermissionProvider{
 public function __construct(private ?OrganizationGroupPolicyService $organizationGroups=null){}
 public function descriptor():PermissionProviderDescriptor{return new PermissionProviderDescriptor('flzroom','Filzmann Raumplaner','1.0',['permissions']);}
 public function collect():PermissionProviderResult{
  $temporaryAdmin=PermissionCondition::all([PermissionCondition::nextcloudAdmin(),PermissionCondition::temporaryAppAdminGrant()]);
  $readConditions=array_map(static fn(string $groupId):PermissionCondition=>PermissionCondition::group($groupId),$this->organizationGroups?->organizationGroupIds()??[]);
  $readConditions[]=$temporaryAdmin;
  $read=PermissionCondition::any($readConditions);
  $title=PermissionCondition::all([$read,PermissionCondition::any([PermissionCondition::self(),PermissionCondition::group(OrganizationGroupPolicyService::SECRETARIAT_GROUP)])]);
  return new PermissionProviderResult([
  $this->rule('Raum und Buchung','Monatsplan','Konfigurierte Organisationsgruppen und zeitweise freigegebene Nextcloud-Administration dürfen Räume und Buchungen lesen','room.read','Lesen','app',$read),
  $this->rule('Buchung','Eigene Buchung','Anlegen, ändern, verschieben und löschen','booking.manage-own','Eigene Buchung verwalten','own-booking',PermissionCondition::all([$read,PermissionCondition::self()])),
  $this->rule('Buchung','Freier Titel','Nur die buchende Person oder das Sekretariat darf den freien Buchungstitel lesen','booking.title.read','Freien Titel lesen','booking',$title),
  $this->rule('Buchung','Begründeter Fremdeingriff','Sekretariat darf eine fremde Buchung nur zusätzlich mit aktiver neutraler Risikoscope-Freigabe begründet ändern oder löschen','booking.intervene-foreign','Fremde Buchung begründet verwalten','foreign-booking',PermissionCondition::group(OrganizationGroupPolicyService::SECRETARIAT_GROUP)),
  $this->rule('Buchung','Eingriffsaudit','Nur Datenschutzbeauftragte dürfen das app-lokale Eingriffsaudit im autorisierten Prüfpfad lesen','booking.intervention-audit.read','Eingriffsaudit lesen','intervention-audit',PermissionCondition::group(TemporaryAdminAccessService::GRANT_MANAGER_GROUP)),
  $this->rule('Buchung','Alle Buchungen','Fremde Buchungen nur mit zeitlich begrenzter app-lokaler Adminfreigabe verwalten','booking.manage-all','Alle Buchungen verwalten','all-bookings',$temporaryAdmin),
  $this->rule('Raum','Raumliste','Räume nur mit zeitlich begrenzter app-lokaler Adminfreigabe verwalten','room.manage','Räume verwalten','all-rooms',$temporaryAdmin),
 ]);}
 private function rule(string $t,string $n,string $d,string $k,string $l,string $s,PermissionCondition $c):PermissionRule{return new PermissionRule($t,$n,$d,$k,$l,'allow',$s,$c,'flzroom:RoomAccessService','high');}
}
