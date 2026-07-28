<?php

namespace InovCom\Sales\Http\Livewire;

use InovCom\Sales\Models\SaleReturn;
use Livewire\Component;

class SaleReturnShow extends Component
{
    public int $returnId;

    public function mount(SaleReturn $saleReturn): void
    {
        $this->returnId = $saleReturn->id;
    }

    public function render()
    {
        $saleReturn = SaleReturn::with(['sale.client', 'lines.saleLine', 'refunds', 'creator'])
            ->findOrFail($this->returnId);

        return view('inovcom-sales::livewire.sales.return-show')
            ->layout('layouts.app', [
                'title' => 'Détail retour',
                'subtitle' => $saleReturn->return_number,
            ])
            ->with([
                'saleReturn' => $saleReturn,
                'tenantCode' => request()->query('tenant')
                    ?? session()->get('tenant_code')
                    ?? optional(request()->attributes->get('tenant'))->code,
            ]);
    }
}
