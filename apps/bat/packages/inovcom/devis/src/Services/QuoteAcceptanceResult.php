<?php

namespace InovCom\Devis\Services;

use InovCom\Maintenance\Models\MaintenanceContract;
use InovCom\Projets\Models\Project;

class QuoteAcceptanceResult
{
    public function __construct(
        public ?Project $project = null,
        public ?MaintenanceContract $contract = null,
    ) {}

    public function isReadyForInvoicing(): bool
    {
        return $this->project !== null || $this->contract !== null;
    }

    public function executionLabel(): ?string
    {
        if ($this->contract) {
            return $this->contract->code;
        }
        if ($this->project) {
            return $this->project->code;
        }

        return null;
    }
}
