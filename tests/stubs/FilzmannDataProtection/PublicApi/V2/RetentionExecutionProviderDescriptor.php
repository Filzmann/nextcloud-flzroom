<?php
declare(strict_types=1);
namespace OCA\FilzmannDataProtection\PublicApi\V2;
final class RetentionExecutionProviderDescriptor { public function __construct(private string $appId,private string $displayName,private string $contractVersion,private int $maxBatchSize){} public function appId():string{return$this->appId;} public function displayName():string{return$this->displayName;} public function contractVersion():string{return$this->contractVersion;} public function maxBatchSize():int{return$this->maxBatchSize;} }
