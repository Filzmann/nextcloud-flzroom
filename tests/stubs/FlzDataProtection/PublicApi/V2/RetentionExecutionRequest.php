<?php
declare(strict_types=1);
namespace OCA\FlzDataProtection\PublicApi\V2;
final class RetentionExecutionRequest { public function __construct(private string $policyId,private string $policyVersion,private string $evaluatedAt,private int $limit){} public function policyId():string{return$this->policyId;} public function policyVersion():string{return$this->policyVersion;} public function evaluatedAt():string{return$this->evaluatedAt;} public function limit():int{return$this->limit;} }
