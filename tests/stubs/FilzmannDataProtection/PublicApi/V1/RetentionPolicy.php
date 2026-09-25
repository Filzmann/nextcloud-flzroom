<?php
declare(strict_types=1);
namespace OCA\FilzmannDataProtection\PublicApi\V1;
final class RetentionPolicy {
    public function __construct(private string $policyId, private string $dataClass, private string $purpose, private string $trigger, private int|string $durationDays, private string $action, private string $version) {}
    public function policyId(): string { return $this->policyId; }
    public function action(): string { return $this->action; }
    public function durationPeriod(): ?string { return is_string($this->durationDays) ? $this->durationDays : null; }
    public function toArray(): array { return ['policyId'=>$this->policyId,'dataClass'=>$this->dataClass,'purpose'=>$this->purpose,'trigger'=>$this->trigger,'durationPeriod'=>$this->durationDays,'action'=>$this->action,'version'=>$this->version]; }
}
