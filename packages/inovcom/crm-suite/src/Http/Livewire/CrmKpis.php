<?php

namespace InovCom\Crm\Http\Livewire;

use InovCom\Crm\Concerns\AuthorizesCrmActions;
use InovCom\Crm\Services\CrmDashboardService;
use Livewire\Component;

class CrmKpis extends Component
{
    use AuthorizesCrmActions;

    public string $period = '30';

    public function render()
    {
        $this->authorizeCrm('crm.view');

        $days = max(7, min(365, (int) $this->period));
        $data = app(CrmDashboardService::class)->snapshot($days);

        return view('inovcom-crm::livewire.kpis', [
            'data' => $data,
            'canViewOpp' => $this->canCrm('crm.opportunities.view'),
            'canViewAct' => $this->canCrm('crm.activities.view'),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => '',
            'subtitle' => '',
            'hidePageHeader' => true,
        ]);
    }
}
