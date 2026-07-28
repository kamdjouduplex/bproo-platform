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
    public string $currency = 'XOF';
    public string $billing_interval = 'monthly';
    public bool $is_active = true;
    public string $sort_order = '0';

    public function mount(?Plan $plan = null): void
    {
        if (!$plan?->id) {
            return;
        }
        $this->planId = $plan->id;
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->description = $plan->description ?? '';
        $this->price = (string) $plan->price;
        $this->currency = $plan->currency ?? 'XOF';
        $this->billing_interval = $plan->billing_interval ?? 'monthly';
        $this->is_active = $plan->is_active;
        $this->sort_order = (string) $plan->sort_order;
    }

    public function updatedName(string $value): void
    {
        if (!$this->planId) {
            $this->slug = Str::slug($value);
        }
    }

    public function save(): void
    {
        $intervals = array_keys(Plan::billingIntervals());
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:80',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'billing_interval' => 'required|string|in:' . implode(',', $intervals),
            'is_active' => 'boolean',
            'sort_order' => 'required|integer|min:0',
        ]);

        $uniqueRule = 'unique:plans,slug';
        if ($this->planId) {
            $uniqueRule .= ',' . $this->planId;
        }
        $this->validate(['slug' => $uniqueRule]);

        $plan = $this->planId ? Plan::find($this->planId) : new Plan();
        if (!$plan) {
            return;
        }

        $plan->fill([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?: null,
            'price' => $this->price,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval,
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
