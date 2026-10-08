<?php
declare(strict_types=1);
namespace OCA\FlzDataProtection\PublicApi\V2;
final class RetentionExecutionResult { public function __construct(private array $deletedReferences,private array $heldReferences,private array $staleReferences,private array $failedReferences){} public function deletedReferences():array{return$this->deletedReferences;} public function heldReferences():array{return$this->heldReferences;} public function staleReferences():array{return$this->staleReferences;} public function failedReferences():array{return$this->failedReferences;} }
