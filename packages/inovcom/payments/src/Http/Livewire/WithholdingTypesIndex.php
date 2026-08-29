<?php

namespace InovCom\InvoicePayments\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InovCom\InvoicePayments\Models\FiscalWithholdingType;
use InovCom\InvoicePayments\Support\WithholdingSchema;
use Livewire\Component;

class WithholdingTypesIndex extends Component
{
    public ?int $editingId = null;
    public string $code = '';
    public string $name = '';
    public string $default_rate = '0';
    public string $default_account = '';
    public string $description = '';
    public bool $is_active = true;

    public function mount(): void
    {
        if (!$this->can('invoice_payments.manage_withholdings') && !$this->can('invoice_payments.view')) {
            abort(403);
        }

        WithholdingSchema::ensure();
    }

    public function startCreate(): void
    {
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $type = FiscalWithholdingType::findOrFail($id);
        $this->editingId = $type->id;
        $this->code = $type->code;
        $this->name = $type->name;
        $this->default_rate = (string) $type->default_rate;
        $this->default_account = (string) ($type->default_account ?? '');
        $this->description = (string) ($type->description ?? '');
        $this->is_active = (bool) $type->is_active;
    }

    public function save(): void
    {
        if (!$this->can('invoice_payments.manage_withholdings')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'name' => 'required|string|max:120',
            'code' => 'nullable|string|max:50',
            'default_rate' => 'nullable|numeric|min:0|max:100',
            'default_account' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = Str::slug($data['name'], '_');
        }

        $payload = [
            'code' => $code,
            'name' => $data['name'],
            'default_rate' => (float) ($data['default_rate'] ?? 0),
            'default_account' => $data['default_account'] ?: null,
            'description' => $data['description'] ?: null,
            'is_active' => (bool) $this->is_active,
        ];

        if ($this->editingId) {
            $type = FiscalWithholdingType::findOrFail($this->editingId);
            $exists = FiscalWithholdingType::query()
                ->where('code', $code)
                ->where('id', '!=', $type->id)
                ->exists();
            if ($exists) {
                session()->flash('error', 'Ce code de retenue existe déjà.');
                return;
            }
            $type->fill($payload)->save();
            session()->flash('success', 'Type de retenue mis à jour.');
        } else {
            if (FiscalWithholdingType::query()->where('code', $code)->exists()) {
                session()->flash('error', 'Ce code de retenue existe déjà.');
                return;
            }
            $payload['sort_order'] = (int) FiscalWithholdingType::query()->max('sort_order') + 10;
            FiscalWithholdingType::create($payload);
            session()->flash('success', 'Type de retenue ajouté.');
        }

        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        if (!$this->can('invoice_payments.manage_withholdings')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $type = FiscalWithholdingType::findOrFail($id);
        $type->is_active = !$type->is_active;
        $type->save();
    }

    public function render()
    {
        return view('inovcom-invoice-payments::livewire.withholding-types')
            ->layout('layouts.app', [
                'title' => 'Types de retenues fiscales',
                'subtitle' => 'Paramètres d\'encaissement',
            ])
            ->with([
                'types' => FiscalWithholdingType::query()->orderBy('sort_order')->orderBy('name')->get(),
                'canManage' => $this->can('invoice_payments.manage_withholdings'),
            ]);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->default_rate = '0';
        $this->default_account = '';
        $this->description = '';
        $this->is_active = true;
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
