<?php

namespace InovCom\Achats\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Achats\Models\Supplier;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SupplierForm extends Component
{
    use AuthorizesWithTenant;

    public ?int $supplierId = null;
    public string $code = '';
    public string $name = '';
    public ?string $contact_name = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $address = null;
    public ?string $notes = null;
    public bool $is_active = true;

    public function mount(?Supplier $supplier = null): void
    {
        $this->tenantAuthorize('achats.view');

        if ($supplier && $supplier->exists) {
            $this->supplierId = $supplier->id;
            $this->code = $supplier->code;
            $this->name = $supplier->name;
            $this->contact_name = $supplier->contact_name;
            $this->email = $supplier->email;
            $this->phone = $supplier->phone;
            $this->address = $supplier->address;
            $this->notes = $supplier->notes;
            $this->is_active = $supplier->is_active;
        }
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
        if ($this->supplierId) {
            $rules['code'] = ['required', 'string', 'max:50', Rule::unique(Supplier::class, 'code')->ignore($this->supplierId)];
        }
        return $rules;
    }

    protected function generateNextSupplierCode(): string
    {
        $max = Supplier::where('code', 'like', 'FOU%')
            ->pluck('code')
            ->map(fn (string $code): int => (int) substr($code, 3))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();
        $next = ($max ?? 0) + 1;
        return 'FOU' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function save(): void
    {
        $this->validate();

        $code = $this->supplierId ? $this->code : $this->generateNextSupplierCode();
        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(app(TenantManager::class)->tenant())->code;

        $data = [
            'code' => $code,
            'name' => $this->name,
            'contact_name' => $this->contact_name ?: null,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'address' => $this->address ?: null,
            'notes' => $this->notes ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->supplierId) {
            Supplier::where('id', $this->supplierId)->update($data);
            $supplier = Supplier::find($this->supplierId);
        } else {
            $supplier = Supplier::create($data);
        }

        if (function_exists('notify')) {
            notify()->success($this->supplierId ? __('Fournisseur mis à jour.') : __('Fournisseur créé.'));
        }
        $this->redirect(route('tenant.achats.suppliers.edit', ['tenant' => $tenantCode, 'supplier' => $supplier->id]), navigate: true);
    }

    public function render()
    {
        return view('inovcom-achats::livewire.suppliers.form')
            ->layout('layouts.app', [
                'title' => $this->supplierId ? __('Modifier le fournisseur') : __('Nouveau fournisseur'),
                'subtitle' => '',
            ]);
    }
}
