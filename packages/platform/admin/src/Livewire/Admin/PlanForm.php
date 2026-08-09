<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use Illuminate\Support\Str;
use Livewire\Component;

class PlanForm extends Component
{
    public ?int $planId = null;
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public string $price = '0';
    public string $price_per_user = '';
    public string $currency = 'XOF';
    public string $billing_interval = 'monthly';
    public string $billing_mode = 'flat';
    public bool $is_active = true;
    public string $sort_order = '0';

    public function mount(?Plan $plan = null): void
    {
        if (! $plan?->id) {
            return;
        }
        $this->planId = $plan->id;
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->description = $plan->description ?? '';
        $this->price = (string) $plan->price;
        $this->price_per_user = $plan->price_per_user !== null ? (string) $plan->price_per_user : '';
        $this->currency = $plan->currency ?? 'XOF';
        $this->billing_interval = $plan->billing_interval ?? 'monthly';
        $this->billing_mode = $plan->billing_mode ?: Plan::MODE_FLAT;
        $this->is_active = $plan->is_active;
        $this->sort_order = (string) $plan->sort_order;
    }

    public function updatedName(string $value): void
    {
        if (! $this->planId) {
            $this->slug = Str::slug($value);
        }
    }

    public function save(): void
    {
        $intervals = array_keys(Plan::billingIntervals());
        $modes = array_keys(Plan::billingModes());

        $rules = [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:80',
            'description' => 'nullable|string|max:2000',
            'currency' => 'required|string|size:3',
            'billing_interval' => 'required|string|in:'.implode(',', $intervals),
            'billing_mode' => 'required|string|in:'.implode(',', $modes),
            'is_active' => 'boolean',
            'sort_order' => 'required|integer|min:0',
        ];

        if ($this->billing_mode === Plan::MODE_PER_SEAT) {
            $rules['price_per_user'] = 'required|numeric|min:0';
            $rules['price'] = 'nullable|numeric|min:0';
        } else {
            $rules['price'] = 'required|numeric|min:0';
            $rules['price_per_user'] = 'nullable|numeric|min:0';
        }

        $this->validate($rules);

        $uniqueRule = 'unique:plans,slug';
        if ($this->planId) {
            $uniqueRule .= ','.$this->planId;
        }
        $this->validate(['slug' => $uniqueRule]);

        $plan = $this->planId ? Plan::find($this->planId) : new Plan();
        if (! $plan) {
            return;
        }

        $pricePerUser = $this->price_per_user === '' ? null : $this->price_per_user;
        $flatPrice = $this->billing_mode === Plan::MODE_PER_SEAT
            ? (float) ($pricePerUser ?? 0)
            : $this->price;

        $plan->fill([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?: null,
            'price' => $flatPrice,
            'price_per_user' => $this->billing_mode === Plan::MODE_PER_SEAT ? $pricePerUser : null,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval,
            'billing_mode' => $this->billing_mode,
            'is_active' => $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ]);
        $plan->save();

        notify()->success($this->planId ? 'Plan mis à jour.' : 'Plan créé.');
        $this->redirect(route('system.plans'), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.plan-form')
            ->layout('layouts.app', [
                'title' => $this->planId ? 'Modifier le plan' : 'Nouveau plan',
                'subtitle' => 'Plans d\'abonnement',
            ]);
    }
}
