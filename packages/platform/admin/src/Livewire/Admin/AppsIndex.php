<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use Livewire\Component;

/**
 * Catalogue of product applications hosted on the platform.
 */
class AppsIndex extends Component
{
    public function render()
    {
        $types = collect(config('tenant_types.types', []));
        $modules = collect(config('modules', []))
            ->except(['core_migration_tags', 'sidebar_groups'])
            ->filter(fn ($m) => is_array($m) && isset($m['label']));

        $totals = Tenant::query()
            ->select('type')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('type')
            ->get()
            ->groupBy(fn ($row) => Tenant::normalizeType($row->type))
            ->map(fn ($rows) => (int) $rows->sum('total'));

        $actives = Tenant::query()
            ->where('is_active', true)
            ->select('type')
            ->selectRaw('COUNT(*) as active')
            ->groupBy('type')
            ->get()
            ->groupBy(fn ($row) => Tenant::normalizeType($row->type))
            ->map(fn ($rows) => (int) $rows->sum('active'));

        $apps = $types->map(function (array $cfg, string $key) use ($totals, $actives, $modules) {
            $suggested = $modules->filter(function ($m) use ($key) {
                $types = $m['tenant_types'] ?? [];

                return $types === [] || in_array($key, $types, true);
            })->count();

            return [
                'key' => $key,
                'label' => $cfg['label'] ?? $key,
                'description' => $cfg['description'] ?? '',
                'icon' => $cfg['icon'] ?? 'package',
                'app_key' => $cfg['app_key'] ?? $key,
                'base_url' => $cfg['base_url'] ?? null,
                'login_path' => $cfg['login_path'] ?? '/app/login',
                'supports_multi_store' => (bool) ($cfg['supports_multi_store'] ?? false),
                'tenants' => (int) ($totals[$key] ?? 0),
                'active' => (int) ($actives[$key] ?? 0),
                'modules' => $suggested,
            ];
        })->values();

        return view('livewire.admin.apps-index', [
            'apps' => $apps,
            'kpis' => [
                'total' => $apps->count(),
                'tenants' => (int) $apps->sum('tenants'),
                'with_clients' => $apps->filter(fn ($a) => $a['tenants'] > 0)->count(),
            ],
        ])->layout('layouts.app', [
            'title' => 'Apps',
            'subtitle' => 'Applications de la plateforme',
        ]);
    }
}
