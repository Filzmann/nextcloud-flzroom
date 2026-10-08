<?php
declare(strict_types=1);
namespace OCA\FlzDataProtection\PublicApi\V2;
final class RetentionExecutionPage { public function __construct(private array $candidates=[],private array $heldReferences=[],private array $warnings=[]){} public function candidates():array{return$this->candidates;} public function heldReferences():array{return$this->heldReferences;} public function warnings():array{return$this->warnings;} }
