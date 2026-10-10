<?php
declare(strict_types=1);
namespace OCA\FlzRoom\Permission;
use OCA\FlzPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
final class RoomPermissionProviderListener implements IEventListener{public function __construct(private RoomPermissionProvider $provider){}public function handle(Event $event):void{if($event instanceof RegisterPermissionProvidersEvent)$event->register($this->provider);}}
