<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TenantHealth extends Component
{
    public array $statuses = [];

    public function mount(): void
    {
        $this->refreshStatuses();
    }

    public function refreshStatuses(): void
    {
        $this->statuses = [];

        $tenants = Tenant::orderBy('name')->get();
        foreach ($tenants as $tenant) {
            $status = 'ok';
            $message = 'Connexion OK';

            try {
                config(['database.connections.tenant' => $tenant->databaseConfig()]);
                DB::purge('tenant');
                DB::reconnect('tenant');
                DB::connection('tenant')->select('select 1');
            } catch (\Throwable $e) {
                $status = 'error';
                $message = $e->getMessage();
            }

            $this->statuses[] = [
                'name' => $tenant->name,
                'code' => $tenant->code,
                'db_name' => $tenant->db_name,
                'status' => $status,
                'message' => $message,
            ];
        }
    }

    public function render()
    {
        return view('livewire.admin.tenant-health')
            ->layout('layouts.app', [
                'title' => __('Santé des entreprises'),
                'subtitle' => __('Vérification des connexions'),
            ]);
    }
}
