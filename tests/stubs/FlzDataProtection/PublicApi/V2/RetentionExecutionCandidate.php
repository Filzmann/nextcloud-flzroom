<?php
declare(strict_types=1);
namespace OCA\FlzDataProtection\PublicApi\V2;
final class RetentionExecutionCandidate { public function __construct(private string $policyId,private string $reference,private string $occurredAt,private string $action,private string $policyVersion,private string $executionToken){} public function policyId():string{return$this->policyId;} public function reference():string{return$this->reference;} public function occurredAt():string{return$this->occurredAt;} public function action():string{return$this->action;} public function policyVersion():string{return$this->policyVersion;} public function executionToken():string{return$this->executionToken;} }
