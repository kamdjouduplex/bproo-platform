<?php

namespace InovCom\Treasury\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\Providers\Models\Provider;
use InovCom\Treasury\Models\TreasuryCommitment;
use InovCom\Treasury\Services\TreasuryService;
use InovCom\Treasury\Support\TreasuryRecurrence;
use Livewire\Component;

class TreasuryCommitmentForm extends Component
{
    public ?int $commitmentId = null;
    public string $label = '';
    public string $category = 'loyer';
    public string $amount = '';
    public string $due_date = '';
    public string $frequency = TreasuryRecurrence::ONCE;
    public string $account_code = '';
    public ?int $provider_id = null;
    public string $beneficiary = '';
    public string $comment = '';
    public string $priority = 'normal';
    public string $alert_days = '';

    public function mount(?TreasuryCommitment $commitment = null): void
    {
        if (!$this->can('treasury.create') && !$this->can('treasury.update')) {
            abort(403);
        }

        if (!$commitment) {
            $this->due_date = now()->format('Y-m-d');
            return;
        }

        $this->commitmentId = $commitment->id;
        $this->label = $commitment->label;
        $this->category = (string) ($commitment->category ?: 'autre');
        $this->amount = (string) $commitment->amount;
        $this->due_date = $commitment->due_date->format('Y-m-d');
        $this->frequency = $commitment->frequency;
        $this->account_code = (string) ($commitment->account_code ?? '');
        $this->provider_id = $commitment->provider_id;
        $this->beneficiary = (string) ($commitment->beneficiary ?? '');
        $this->comment = (string) ($commitment->comment ?? '');
        $this->priority = $commitment->priority ?: 'normal';
        $this->alert_days = $commitment->alert_days !== null ? (string) $commitment->alert_days : '';
    }

    public function save(): void
    {
        if (!$this->can($this->commitmentId ? 'treasury.update' : 'treasury.create')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'label' => 'required|string|max:180',
            'category' => 'nullable|string|max:80',
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'required|date',
            'frequency' => 'required|in:once,weekly,monthly,yearly',
            'account_code' => 'nullable|string|max:50',
            'provider_id' => 'nullable|integer',
            'beneficiary' => 'nullable|string|max:180',
            'comment' => 'nullable|string|max:1000',
            'priority' => 'required|in:low,normal,high',
            'alert_days' => 'nullable|integer|min:1|max:365',
        ]);

        $payload = [
            'label' => $data['label'],
            'category' => $data['category'] ?: 'autre',
            'amount' => (float) $data['amount'],
            'due_date' => $data['due_date'],
            'frequency' => $data['frequency'],
            'account_code' => $data['account_code'] ?: null,
            'provider_id' => $data['provider_id'] ?: null,
            'beneficiary' => $data['beneficiary'] ?: null,
            'comment' => $data['comment'] ?: null,
            'priority' => $data['priority'],
            'alert_days' => $data['alert_days'] !== null && $data['alert_days'] !== '' ? (int) $data['alert_days'] : null,
        ];

        $service = app(TreasuryService::class);
        if ($this->commitmentId) {
            $service->update(TreasuryCommitment::findOrFail($this->commitmentId), $payload);
            session()->flash('success', 'Engagement mis à jour.');
        } else {
            $service->create($payload);
            session()->flash('success', 'Dépense prévisionnelle enregistrée.');
        }

        $this->redirect(route('tenant.treasury.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function cancelCommitment(): void
    {
        if (!$this->commitmentId || !$this->can('treasury.delete')) {
            return;
        }

        app(TreasuryService::class)->cancel(TreasuryCommitment::findOrFail($this->commitmentId));
        session()->flash('success', 'Engagement annulé.');
        $this->redirect(route('tenant.treasury.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function render()
    {
        $providers = class_exists(Provider::class)
            ? Provider::query()->where('is_active', true)->orderBy('name')->limit(200)->get()
            : collect();

        return view('inovcom-treasury::livewire.form')
            ->layout('layouts.app', [
                'title' => $this->commitmentId ? 'Modifier l\'engagement' : 'Nouvelle dépense prévisionnelle',
                'subtitle' => 'Prévision de trésorerie',
            ])
            ->with([
                'providers' => $providers,
                'categories' => TreasuryCommitment::categoryOptions(),
                'canDelete' => $this->commitmentId && $this->can('treasury.delete'),
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
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
