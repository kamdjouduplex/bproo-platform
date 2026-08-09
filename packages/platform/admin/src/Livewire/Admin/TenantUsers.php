<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use App\Services\CompanyIntelligenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class TenantUsers extends Component
{
    public Tenant $tenant;

    public string $error = '';

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function refreshMetrics(): void
    {
        $result = app(CompanyIntelligenceService::class)->refresh($this->tenant, true);
        $this->tenant->refresh();

        if (! empty($result['users_limit_newly_exceeded'])) {
            notify()->warning(
                "Alerte : « {$this->tenant->code} » a dépassé son plafond ({$this->tenant->users_count}/{$this->tenant->max_users})."
            );
        } else {
            notify()->success('Compteur utilisateurs actualisé.');
        }
    }

    public function render()
    {
        $users = collect();
        $this->error = '';

        try {
            config(['database.connections.tenant' => $this->tenant->databaseConfig()]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            if (! Schema::connection('tenant')->hasTable('users')) {
                $this->error = 'Table users absente (provisionnement incomplet).';
            } else {
                $query = DB::connection('tenant')->table('users')->orderBy('id');

                $columns = ['id', 'name', 'email', 'created_at'];
                if (Schema::connection('tenant')->hasColumn('users', 'phone')) {
                    $columns[] = 'phone';
                }
                if (Schema::connection('tenant')->hasColumn('users', 'is_active')) {
                    $columns[] = 'is_active';
                }

                $users = $query->get($columns);
            }
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }

        return view('livewire.admin.tenant-users', [
            'users' => $users,
        ])->layout('layouts.app', [
            'title' => 'Utilisateurs · '.$this->tenant->code,
            'subtitle' => $this->tenant->name,
        ]);
    }
}
