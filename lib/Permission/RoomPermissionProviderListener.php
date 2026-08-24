<?php
declare(strict_types=1);
namespace OCA\AdRoom\Permission;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
final class RoomPermissionProviderListener{public function __construct(private RoomPermissionProvider $provider){}public function handle(object $event):void{if($event instanceof RegisterPermissionProvidersEvent)$event->register($this->provider);}}
