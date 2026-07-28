<?php

namespace InovCom\Debts\Http\Livewire;

use InovCom\Debts\Models\Debt;
use InovCom\Debts\Services\DebtsService;
use InovCom\Clients\Models\Client;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class DebtsIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public ?int $clientFilter = null;
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 20;

    public string $validationFilter = 'all';

    public function mount(): void
    {
        $client = request()->query('client');
        if ($client !== null && $client !== '') {
            $this->clientFilter = (int) $client;
        }

        if (request()->query('validation') === 'pending') {
            $this->validationFilter = 'pending';
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->clientFilter = null;
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function delete(int $debtId): void
    {
        if (!$this->can('debts.delete')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $debt = Debt::findOrFail($debtId);

        if ((float) $debt->balance > 0) {
            session()->flash('error', 'Seules les dettes soldées peuvent être supprimées.');
            return;
        }

        $debt->delete();
        $this->resetPage();
    }

    public function validateDebt(int $debtId): void
    {
        if (!$this->can('debts.validate')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        if (!Debt::supportsValidationWorkflow()) {
            session()->flash('error', 'Validation indisponible: exécutez les migrations tenant du module Dettes.');
            return;
        }

        $debt = Debt::findOrFail($debtId);
        if ((bool) $debt->is_validated) {
            session()->flash('success', 'Cette dette est déjà validée.');
            return;
        }

        $debt->is_validated = true;
        $debt->validated_by = auth('tenant')->id();
        $debt->validated_at = now();
        $debt->save();

        session()->flash('success', 'Dette validée avec succès.');
    }

    public function render()
    {
        $debts = Debt::query()
            ->with(['client', 'creator', 'validator'])
            ->when(
                Debt::supportsValidationWorkflow() && $this->validationFilter === 'pending',
                fn ($q) => $q->where('is_validated', false)->whereIn('status', ['open', 'partial'])
            )
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('reference', 'like', '%' . $this->search . '%')
                        ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('code', 'like', '%' . $this->search . '%'));
                });
            }, function ($q) {
                $q->when($this->statusFilter !== 'all', fn ($q2) => $q2->where('status', $this->statusFilter))
                    ->when($this->clientFilter, fn ($q2) => $q2->where('client_id', $this->clientFilter))
                    ->when($this->dateFrom, fn ($q2) => $q2->where('opened_at', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q2) => $q2->where('opened_at', '<=', $this->dateTo));
            })
            ->orderBy('opened_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $clients = Client::where('is_active', true)->orderBy('name')->get();

        $service = app(DebtsService::class);
        $totalOutstanding = $service->getTotalOutstanding(
            $this->clientFilter ?: null
        );

        return view('inovcom-debts::livewire.debts.index')
            ->layout('layouts.app', [
                'title' => 'Dettes',
                'subtitle' => 'Gestion des dettes clients',
            ])
            ->with([
                'debts' => $debts,
                'clients' => $clients,
                'totalOutstanding' => $totalOutstanding,
                'validationWorkflowReady' => Debt::supportsValidationWorkflow(),
                'canValidate' => $this->can('debts.validate'),
                'canDelete' => $this->can('debts.delete'),
                'canReceivePayment' => $this->can('debts.receive_payment'),
                'canCreate' => $this->can('debts.create'),
            ]);
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
