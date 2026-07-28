<?php

namespace App\Livewire\Admin;

use App\Jobs\ProvisionTenantJob;
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

            if ($tenant->provisioning_status === 'pending' || $tenant->provisioning_status === 'provisioning') {
                $status = 'pending';
                $message = 'Provisionnement en cours — patientez ou consultez les logs queue.';
            } elseif ($tenant->provisioning_status === 'failed') {
                $status = 'error';
                $message = $tenant->provisioning_error ?: 'Provisionnement échoué — utilisez « Relancer » ci-dessous.';
            } else {
                try {
                    config(['database.connections.tenant' => $tenant->databaseConfig()]);
                    DB::purge('tenant');
                    DB::reconnect('tenant');
                    DB::connection('tenant')->select('select 1');
                } catch (\Throwable $e) {
                    $status = 'error';
                    $message = $e->getMessage();
                }
            }

            $this->statuses[] = [
                'name' => $tenant->name,
                'code' => $tenant->code,
                'db_name' => $tenant->db_name,
                'status' => $status,
                'message' => $message,
                'provisioning_status' => $tenant->provisioning_status,
            ];
        }
    }

    public function retryProvisioning(string $code): void
    {
        $tenant = Tenant::where('code', $code)->first();
        if (!$tenant) {
            notify()->error('Vendeur introuvable.');
            return;
        }

        $tenant->update([
            'provisioning_status' => 'pending',
            'provisioning_error' => null,
            'db_host' => null,
            'db_port' => null,
            'db_username' => null,
            'db_password' => null,
        ]);

        ProvisionTenantJob::dispatch($tenant, '', '', '');

        notify()->success("Provisionnement relancé pour « {$code} ». Rafraîchissez dans 1–2 min.");
        $this->refreshStatuses();
    }

    public function render()
    {
        return view('livewire.admin.tenant-health')
            ->layout('layouts.app', [
                'title' => 'Santé des vendeurs',
                'subtitle' => 'Vérification des connexions',
            ]);
    }
}
