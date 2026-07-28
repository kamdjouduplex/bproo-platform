<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use Livewire\Component;

class Plans extends Component
{
    public function delete(int $planId): void
    {
        $plan = Plan::find($planId);
        if (!$plan) {
            return;
        }
        if ($plan->subscriptions()->exists()) {
            notify()->error('Impossible de supprimer un plan utilisé par des abonnements.');
            return;
        }
        $plan->delete();
        notify()->success('Plan supprimé.');
    }

    public function render()
    {
        $plans = Plan::ordered()->get();
        return view('livewire.admin.plans', compact('plans'))
            ->layout('layouts.app', [
                'title' => 'Plans d\'abonnement',
                'subtitle' => 'Gestion des offres',
            ]);
    }
}
