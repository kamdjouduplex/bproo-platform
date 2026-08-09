<?php

namespace App\Livewire\Admin;

use App\Models\PlatformCurrency;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class PlatformCurrencies extends Component
{
    public string $code = '';

    public string $name = '';

    public string $symbol = '';

    public string $decimals = '2';

    public string $sort_order = '100';

    public bool $is_active = true;

    public ?string $editingCode = null;

    public function save(): void
    {
        if (! Schema::hasTable('platform_currencies')) {
            notify()->error('Table platform_currencies absente. Lancez les migrations Control Center.');

            return;
        }

        $data = $this->validate([
            'code' => 'required|string|size:3|alpha',
            'name' => 'required|string|max:120',
            'symbol' => 'nullable|string|max:16',
            'decimals' => 'required|integer|min:0|max:6',
            'sort_order' => 'required|integer|min:0|max:9999',
            'is_active' => 'boolean',
        ]);

        $code = strtoupper($data['code']);

        PlatformCurrency::updateOrCreate(
            ['code' => $code],
            [
                'name' => $data['name'],
                'symbol' => $data['symbol'] ?: null,
                'decimals' => (int) $data['decimals'],
                'sort_order' => (int) $data['sort_order'],
                'is_active' => (bool) $data['is_active'],
            ]
        );

        $this->resetForm();
        notify()->success('Devise enregistrée.');
    }

    public function edit(string $code): void
    {
        $cur = PlatformCurrency::find($code);
        if (! $cur) {
            return;
        }
        $this->editingCode = $cur->code;
        $this->code = $cur->code;
        $this->name = $cur->name;
        $this->symbol = (string) ($cur->symbol ?? '');
        $this->decimals = (string) $cur->decimals;
        $this->sort_order = (string) $cur->sort_order;
        $this->is_active = (bool) $cur->is_active;
    }

    public function toggleActive(string $code): void
    {
        $cur = PlatformCurrency::find($code);
        if (! $cur) {
            return;
        }
        $cur->is_active = ! $cur->is_active;
        $cur->save();
        notify()->success($cur->is_active ? 'Devise activée.' : 'Devise désactivée.');
    }

    public function resetForm(): void
    {
        $this->editingCode = null;
        $this->code = '';
        $this->name = '';
        $this->symbol = '';
        $this->decimals = '2';
        $this->sort_order = '100';
        $this->is_active = true;
    }

    public function render()
    {
        $currencies = Schema::hasTable('platform_currencies')
            ? PlatformCurrency::query()->ordered()->get()
            : collect();

        return view('livewire.admin.platform-currencies', [
            'currencies' => $currencies,
        ])->layout('layouts.app', [
            'title' => 'Devises plateforme',
            'subtitle' => 'Catalogue partagé pour toutes les applications',
        ]);
    }
}
