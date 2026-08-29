<?php

namespace InovCom\Quotations\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use InovCom\Clients\Models\Client;
use InovCom\Invoicing\Services\DeliveryNotesService;
use InovCom\Quotations\Models\Quotation;
use InovCom\Quotations\Services\QuotationsService;
use Livewire\Component;
use Livewire\WithPagination;

class QuotationsIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public ?int $clientFilter = null;
    public int $perPage = 20;

    public function mount(): void
    {
        $status = request()->query('status');
        if (is_string($status) && $status !== '') {
            $this->statusFilter = $status;
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->clientFilter = null;
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function duplicate(int $quotationId): void
    {
        if (!$this->can('quotations.create')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $source = Quotation::with('lines')->findOrFail($quotationId);

        try {
            $new = app(QuotationsService::class)->duplicate($source);
            session()->flash('success', 'Devis dupliqué : ' . $new->number);
            $this->redirect(route('tenant.quotations.edit', [
                'tenant' => $this->tenantCode(),
                'quotation' => $new->id,
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $quotations = Quotation::query()
            ->with(['client', 'creator'])
            ->when($this->search !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(function ($q2) use ($term) {
                    $q2->where('number', 'like', $term)
                        ->orWhere('customer_purchase_order', 'like', $term)
                        ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term));
                });
            })
            ->when($this->statusFilter !== 'all', function ($q) {
                if ($this->statusFilter === 'accepted') {
                    $q->whereIn('status', ['accepted', 'validated']);
                } else {
                    $q->where('status', $this->statusFilter);
                }
            })
            ->when($this->clientFilter, fn ($q) => $q->where('client_id', $this->clientFilter))
            ->orderByDesc('quote_date')
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        if (Schema::connection('tenant')->hasTable('delivery_notes')
            && Schema::connection('tenant')->hasColumn('quotations', 'fulfillment_status')) {
            $deliveryService = app(DeliveryNotesService::class);
            foreach ($quotations as $q) {
                if ($q->isAccepted()) {
                    $deliveryService->syncQuotationFulfillment($q);
                    $q->refresh();
                }
            }
        }

        return view('inovcom-quotations::livewire.quotations.index')
            ->layout('layouts.app', [
                'title' => 'Devis',
                'subtitle' => 'Propositions commerciales clients',
            ])
            ->with([
                'quotations' => $quotations,
                'clients' => Client::where('is_active', true)->orderBy('name')->get(),
                'canCreate' => $this->can('quotations.create'),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
