<?php

namespace InovCom\Debts\Http\Livewire;

use InovCom\Debts\Models\Debt;
use InovCom\Debts\Services\DebtsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PaymentForm extends Component
{
    public Debt $debt;

    public string $amount = '';
    public string $payment_date = '';
    public string $payment_method = 'cash';
    public ?string $notes = null;
    public ?string $external_reference = null;

    public function mount(Debt $debt): void
    {
        $this->debt = $debt;
        $this->payment_date = now()->format('Y-m-d');
        $this->amount = (string) $debt->balance;

        if (!$this->can('debts.receive_payment')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        if (Debt::supportsValidationWorkflow() && !(bool) $this->debt->is_validated) {
            session()->flash('error', 'Aucun paiement autorisé: cette dette doit être validée.');
        }
    }

    public function save(): void
    {
        if (!$this->can('debts.receive_payment')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        if (Debt::supportsValidationWorkflow() && !(bool) $this->debt->is_validated) {
            session()->flash('error', 'Aucun paiement autorisé: cette dette doit être validée.');
            return;
        }

        $data = $this->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,check,bank_transfer,mobile_money,other',
            'notes' => 'nullable|string|max:500',
            'external_reference' => 'nullable|string|max:100',
        ], [], [
            'amount' => 'montant',
            'payment_date' => 'date',
            'payment_method' => 'méthode',
            'notes' => 'notes',
            'external_reference' => 'référence externe',
        ]);

        try {
            $service = app(DebtsService::class);
            $service->recordPayment(
                $this->debt->id,
                (float) $data['amount'],
                $data['payment_date'],
                $data['payment_method'],
                $data['notes'] ?? null,
                $data['external_reference'] ?? null
            );
            session()->flash('success', 'Paiement enregistré. Solde mis à jour.');
            $this->redirect(route('tenant.debts.edit', ['tenant' => $this->tenantCode(), 'debt' => $this->debt->id]), navigate: true);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $this->debt->load(['client', 'payments.creator']);

        return view('inovcom-debts::livewire.debts.payment-form')
            ->layout('layouts.app', [
                'title' => 'Encaisser un paiement',
                'subtitle' => 'Dette ' . $this->debt->reference,
            ])
            ->with(['debt' => $this->debt]);
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
