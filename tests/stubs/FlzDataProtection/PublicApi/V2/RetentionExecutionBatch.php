<?php
declare(strict_types=1);
namespace OCA\FlzDataProtection\PublicApi\V2;
final class RetentionExecutionBatch { public function __construct(private RetentionExecutionRequest $request,private array $candidates){} public function request():RetentionExecutionRequest{return$this->request;} public function candidates():array{return$this->candidates;} }
