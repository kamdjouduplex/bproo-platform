<?php

namespace App\Livewire\Admin;

use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use Livewire\Component;

class TenantForm extends Component
{
    public ?int $tenantId = null;

    public string $name = '';
    public string $code = '';
    public string $db_name = '';
    public ?string $db_host = null;
    public ?string $db_port = null;
    public ?string $db_username = null;
    public ?string $db_password = null;
    public bool $is_active = true;
    public string $plan = 'free';
    public ?string $plan_expires_at = null;
    public ?string $trial_ends_at = null;
    public string $admin_name = '';
    public string $admin_email = '';
    public string $admin_password = '';
    public ?string $provisioningError = null;

    public function mount(?Tenant $tenant = null): void
    {
        if (!$tenant) {
            return;
        }

        $this->tenantId = $tenant->id;
        $this->name = $tenant->name;
        $this->code = $tenant->code;
        $this->db_name = $tenant->db_name;
        $this->db_host = $tenant->db_host;
        $this->db_port = $tenant->db_port;
        $this->db_username = $tenant->db_username;
        $this->db_password = '';
        $this->is_active       = $tenant->is_active;
        $this->plan            = $tenant->plan ?? 'free';
        $this->plan_expires_at = $tenant->plan_expires_at?->format('Y-m-d');
        $this->trial_ends_at   = $tenant->trial_ends_at?->format('Y-m-d');
    }

    public function save(): void
    {
        $this->provisioningError = null;

        $data = $this->validate([
            'name'           => 'required|string|max:255',
            'code'           => 'required|string|max:50',
            'db_name'        => 'required|string|max:255',
            'db_host'        => 'nullable|string|max:255',
            'db_port'        => 'nullable|string|max:10',
            'db_username'    => 'nullable|string|max:255',
            'db_password'    => 'nullable|string|max:255',
            'is_active'      => 'boolean',
            'plan'           => 'required|in:free,starter,pro,enterprise',
            'plan_expires_at'=> 'nullable|date',
            'trial_ends_at'  => 'nullable|date',
        ]);

        if (!$this->tenantId) {
            $this->validate([
                'admin_name' => 'required|string|max:255',
                'admin_email' => 'required|email|max:255',
                'admin_password' => 'required|string|min:6',
            ]);
        }

        $rules = [
            'code' => 'unique:tenants,code',
            'db_name' => 'unique:tenants,db_name',
        ];

        if ($this->tenantId) {
            $rules['code'] .= ',' . $this->tenantId;
            $rules['db_name'] .= ',' . $this->tenantId;
        }

        $this->validate($rules);

        $tenant = $this->tenantId ? Tenant::find($this->tenantId) : new Tenant();
        if (!$tenant) {
            return;
        }

        if ($data['db_password'] === '') {
            unset($data['db_password']);
        }

        // Ensure db_name is set correctly (no modification)
        $dbName = trim($data['db_name']);
        if (empty($dbName)) {
            notify()->error('Le nom de la base de données est requis.');
            return;
        }

        $tenant->fill($data);
        $tenant->db_name = $dbName; // Explicitly set to ensure no modification
        
        // Set initial provisioning status for new tenants
        if (!$this->tenantId) {
            $tenant->provisioning_status = 'pending';
        }
        
        $tenant->save();
        
        // Log for debugging
        \Illuminate\Support\Facades\Log::info("Tenant saved", [
            'tenant_id' => $tenant->id,
            'code' => $tenant->code,
            'db_name' => $tenant->db_name,
            'db_name_from_form' => $this->db_name,
        ]);

        if (!$this->tenantId) {
            // Dispatch provisioning job instead of running synchronously
            ProvisionTenantJob::dispatch(
                $tenant,
                $this->admin_name,
                $this->admin_email,
                $this->admin_password
            );

            notify()->success(__('Entreprise créée. Le provisionnement est en cours...'));
        } else {
            notify()->success(__('Entreprise mise à jour avec succès.'));
        }

        $this->redirect(route('system.tenants'), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.tenant-form')
            ->layout('layouts.app', [
                'title' => $this->tenantId ? __('Modifier l\'entreprise') : __('Créer une entreprise'),
                'subtitle' => __('Gestion des entreprises'),
            ]);
    }
}
