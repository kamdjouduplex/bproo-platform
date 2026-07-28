<?php

namespace App\Livewire\Tenant;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\ModuleRegistry;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InovCom\Kernel\Support\ServiceCatalog;
use Livewire\Component;

class ServicesHub extends Component
{
    use AuthorizesWithTenant;

    public function mount(): void
    {
        if (!Gate::allows('projets.view') && !Gate::allows('maintenance.view')) {
            abort(403);
        }
    }

    public function render()
    {
        $tenant = app(TenantManager::class)->tenant();
        $db     = DB::connection('tenant');
        $registry = app(ModuleRegistry::class);

        $can = [
            'projets'     => Gate::allows('projets.view') && $registry->isEnabled('projets', $tenant),
            'maintenance' => Gate::allows('maintenance.view') && $registry->isEnabled('maintenance', $tenant),
            'planning'    => Gate::allows('planning.view') && $registry->isEnabled('planning', $tenant),
            'suivi'       => Gate::allows('suivi.view') && $registry->isEnabled('suivi', $tenant),
            'stock'       => Gate::allows('stock.view') && $registry->isEnabled('stock', $tenant),
            'logistique'  => Gate::allows('logistique.view') && $registry->isEnabled('logistique', $tenant),
        ];

        $stats = [
            'construction_active' => 0,
            'service_active'      => 0,
            'maintenance_open'      => 0,
            'interventions_week'    => 0,
        ];

        if ($can['projets']) {
            $base = $db->table('projects')->whereIn('status', ['planned', 'in_progress', 'on_hold']);
            $stats['construction_active'] = (clone $base)
                ->where(function ($q) {
                    $q->where('project_type', ServiceCatalog::EXEC_CONSTRUCTION)
                        ->orWhereNull('project_type');
                })
                ->count();
            $stats['service_active'] = (clone $base)
                ->where('project_type', ServiceCatalog::EXEC_SERVICE)
                ->count();
        }

        if ($can['maintenance'] && $db->getSchemaBuilder()->hasTable('maintenance_orders')) {
            $stats['maintenance_open'] = $db->table('maintenance_orders')
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();

            if ($db->getSchemaBuilder()->hasTable('interventions')) {
                $stats['interventions_week'] = $db->table('interventions')
                    ->where('scheduled_at', '>=', now()->startOfWeek())
                    ->where('scheduled_at', '<=', now()->endOfWeek())
                    ->count();
            }
        }

        $tenantCode = session('tenant_code') ?? $tenant?->code;

        return view('livewire.tenant.services-hub', [
            'can'        => $can,
            'stats'      => $stats,
            'tenantCode' => $tenantCode,
            'catalog'    => ServiceCatalog::class,
        ])->layout('layouts.app', [
            'title'    => __('Centre des services'),
            'subtitle' => __('Exécution chantiers, maintenance et prestations'),
        ]);
    }
}
