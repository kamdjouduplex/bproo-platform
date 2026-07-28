<?php

namespace InovCom\Providers\Http\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Providers\Models\Provider;
use InovCom\Purchases\Models\PurchaseOrder;
use Livewire\Component;

class ProvidersShow extends Component
{
    public Provider $provider;

    public function mount(Provider $provider): void
    {
        $this->provider = $provider->load(['paymentTerm', 'primaryContact', 'contacts']);
    }

    public function render()
    {
        $purchasesAvailable = Schema::connection('tenant')->hasTable('purchase_orders');

        return view('inovcom-providers::livewire.providers.show')
            ->layout('layouts.app', [
                'title' => 'Fournisseur ' . $this->provider->code,
                'subtitle' => $this->provider->name,
            ])
            ->with([
                'purchasesAvailable' => $purchasesAvailable,
                'purchaseStats' => $this->buildPurchaseStats($purchasesAvailable),
                'purchases' => $this->buildPurchaseHistory($purchasesAvailable),
            ]);
    }

    /**
     * @return array{count:int, total_amount:float, received_amount:float, open_amount:float, received_count:int, open_count:int, cancelled_count:int, last_order_date:?string}
     */
    private function buildPurchaseStats(bool $available): array
    {
        $empty = [
            'count' => 0,
            'total_amount' => 0.0,
            'received_amount' => 0.0,
            'open_amount' => 0.0,
            'received_count' => 0,
            'open_count' => 0,
            'cancelled_count' => 0,
            'last_order_date' => null,
        ];

        if (! $available) {
            return $empty;
        }

        $openStatuses = ['draft', 'confirmed', 'partial', 'sent'];

        $base = DB::connection('tenant')->table('purchase_orders')
            ->where('provider_id', $this->provider->id);

        return [
            'count' => (clone $base)->count(),
            'total_amount' => (float) (clone $base)->where('status', '!=', 'cancelled')->sum('total'),
            'received_amount' => (float) (clone $base)->where('status', 'received')->sum('total'),
            'open_amount' => (float) (clone $base)->whereIn('status', $openStatuses)->sum('total'),
            'received_count' => (clone $base)->where('status', 'received')->count(),
            'open_count' => (clone $base)->whereIn('status', $openStatuses)->count(),
            'cancelled_count' => (clone $base)->where('status', 'cancelled')->count(),
            'last_order_date' => (clone $base)->where('status', '!=', 'cancelled')->max('order_date'),
        ];
    }

    private function buildPurchaseHistory(bool $available)
    {
        if (! $available || ! class_exists(PurchaseOrder::class)) {
            return collect();
        }

        return PurchaseOrder::query()
            ->with('lines')
            ->where('provider_id', $this->provider->id)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();
    }
}
