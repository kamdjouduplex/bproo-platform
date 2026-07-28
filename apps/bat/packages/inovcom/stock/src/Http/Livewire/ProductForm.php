<?php

namespace InovCom\Stock\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Stock\Models\Product;
use Livewire\Component;

class ProductForm extends Component
{
    use AuthorizesWithTenant;

    public ?int    $productId       = null;
    public string  $code            = '';
    public string  $name            = '';
    public string  $category        = '';
    public string  $unit            = 'pièce';
    public string  $description     = '';
    public string  $min_stock_alert = '0';
    public bool    $is_active       = true;

    public function mount(?Product $stock_product = null): void
    {
        if ($stock_product && $stock_product->exists) {
            $this->tenantAuthorize('stock.edit');
            $this->productId       = $stock_product->id;
            $this->code            = $stock_product->code;
            $this->name            = $stock_product->name;
            $this->category        = $stock_product->category ?? '';
            $this->unit            = $stock_product->unit;
            $this->description     = $stock_product->description ?? '';
            $this->min_stock_alert = (string) $stock_product->min_stock_alert;
            $this->is_active       = $stock_product->is_active;
        } else {
            $this->tenantAuthorize('stock.create');
        }
    }

    protected function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'category'        => ['nullable', 'string', 'max:100'],
            'unit'            => ['required', 'string', 'max:50'],
            'description'     => ['nullable', 'string'],
            'min_stock_alert' => ['nullable', 'numeric', 'min:0'],
            'is_active'       => ['boolean'],
        ];
    }

    public function save(): void
    {
        $this->tenantAuthorize($this->productId ? 'stock.edit' : 'stock.create');
        $this->validate();

        $data = [
            'name'            => $this->name,
            'category'        => $this->category ?: null,
            'unit'            => $this->unit,
            'description'     => $this->description ?: null,
            'min_stock_alert' => (float) $this->min_stock_alert,
            'is_active'       => $this->is_active,
        ];

        if ($this->productId) {
            Product::on('tenant')->findOrFail($this->productId)->update($data);
            notify()->success(__('Produit mis à jour.'));
        } else {
            $data['code'] = Product::generateCode();
            Product::on('tenant')->create($data);
            notify()->success(__('Produit créé.'));
        }

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        $this->redirect(route('tenant.stock.products.index', ['tenant' => $tenantCode]), navigate: true);
    }

    public function cancel(): void
    {
        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        $this->redirect(route('tenant.stock.products.index', ['tenant' => $tenantCode]), navigate: true);
    }

    public function render()
    {
        return view('inovcom-stock::livewire.products.form', [
            'units'      => Product::units(),
            'categories' => Product::categories(),
            'isEdit'     => (bool) $this->productId,
        ])->layout('layouts.app', [
            'title'    => $this->productId ? 'Modifier le produit' : 'Nouveau produit',
            'subtitle' => $this->code ?: '',
        ]);
    }
}
