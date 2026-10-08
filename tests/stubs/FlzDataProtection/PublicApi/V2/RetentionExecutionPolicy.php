<?php
declare(strict_types=1);
namespace OCA\FlzDataProtection\PublicApi\V2;
final class RetentionExecutionPolicy { public function __construct(private string $policyId,private string $dataClass,private string $purpose,private string $trigger,private string $durationPeriod,private string $action,private string $version){} public function policyId():string{return$this->policyId;} public function durationPeriod():string{return$this->durationPeriod;} public function action():string{return$this->action;} public function version():string{return$this->version;} }
