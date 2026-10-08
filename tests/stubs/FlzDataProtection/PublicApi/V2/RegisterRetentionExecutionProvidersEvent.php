<?php
declare(strict_types=1);
namespace OCA\FlzDataProtection\PublicApi\V2;
class RegisterRetentionExecutionProvidersEvent { public array $providers=[]; public function register(RetentionExecutionProvider $provider):void{$this->providers[]=$provider;} }
