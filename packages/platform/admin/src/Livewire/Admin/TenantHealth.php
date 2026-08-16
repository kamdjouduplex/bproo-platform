<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use App\Services\TenantProvisionDispatcher;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TenantHealth extends Component
{
    public array $statuses = [];

    public ?string $retryCode = null;

    public string $admin_name = '';

    public string $admin_email = '';

    public string $admin_password = '';

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

    public function openRetry(string $code): void
    {
        $this->retryCode = $code;
        $this->admin_name = '';
        $this->admin_email = '';
        $this->admin_password = '';
        $this->resetErrorBag();
    }

    public function cancelRetry(): void
    {
        $this->retryCode = null;
        $this->admin_name = '';
        $this->admin_email = '';
        $this->admin_password = '';
        $this->resetErrorBag();
    }

    public function retryProvisioning(): void
    {
        $this->validate([
            'retryCode' => ['required', 'string'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        $tenant = Tenant::where('code', $this->retryCode)->first();
        if (! $tenant) {
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

        try {
            app(TenantProvisionDispatcher::class)->dispatch(
                $tenant,
                $this->admin_name,
                $this->admin_email,
                $this->admin_password
            );
        } catch (\Throwable $e) {
            notify()->error('Échec relance : '.$e->getMessage());
            $this->refreshStatuses();

            return;
        }

        notify()->success("Provisionnement relancé pour « {$this->retryCode} ». Rafraîchissez dans 1–2 min.");
        $this->cancelRetry();
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
