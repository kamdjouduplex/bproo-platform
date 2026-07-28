<?php

namespace App\Livewire\Admin;

use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use Illuminate\Support\Str;
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
    public bool $multi_store_enabled = false;
    public string $type = 'retail';
    public string $contact_key_first_name = '';
    public string $contact_key_last_name = '';
    public string $contact_key_phone = '';
    public string $country = '';
    public string $city = '';
    public string $contact_key_address = '';
    public string $admin_name = '';
    public string $admin_email = '';
    public string $admin_password = '';
    public ?string $provisioningError = null;
    public bool $showAdvancedDb = false;

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
        $this->showAdvancedDb = (bool) ($tenant->db_host || $tenant->db_port || $tenant->db_username);
        $this->is_active = $tenant->is_active;
        $this->multi_store_enabled = (bool) $tenant->multi_store_enabled;
        $this->type = $tenant->type;
        $this->contact_key_first_name = $tenant->contact_key_first_name ?? '';
        $this->contact_key_last_name = $tenant->contact_key_last_name ?? '';
        $this->contact_key_phone = $tenant->contact_key_phone ?? '';
        $this->country = $tenant->country ?? '';
        $this->city = $tenant->city ?? '';
        $this->contact_key_address = $tenant->contact_key_address ?? '';
    }

    private function defaultDbName(string $code): string
    {
        $slug = Str::slug(strtolower(trim($code)), '_');
        if ($slug === '') {
            return '';
        }

        do {
            $name = 'erp_' . $slug . '_' . strtolower(Str::random(4));
        } while (Tenant::where('db_name', $name)->exists());

        return $name;
    }

    public function save(): void
    {
        $this->provisioningError = null;

        if (!$this->tenantId && trim($this->db_name) === '' && trim($this->code) !== '') {
            $this->db_name = $this->defaultDbName($this->code);
        }

        $typeKeys = array_keys(config('tenant_types.types', ['retail' => []]));
        $data = $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'db_name' => 'required|string|max:255',
            'db_host' => 'nullable|string|max:255',
            'db_port' => 'nullable|string|max:10',
            'db_username' => 'nullable|string|max:255',
            'db_password' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'multi_store_enabled' => 'boolean',
            'type' => 'required|string|in:' . implode(',', $typeKeys ?: ['retail']),
            'contact_key_first_name' => 'nullable|string|max:255',
            'contact_key_last_name' => 'nullable|string|max:255',
            'contact_key_phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'contact_key_address' => 'nullable|string|max:500',
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

        // Standard deploy: use system DB credentials (erp_app). Ignore accidental autofill in hidden fields.
        if (!$this->tenantId && !$this->showAdvancedDb) {
            $data['db_host'] = null;
            $data['db_port'] = null;
            $data['db_username'] = null;
            unset($data['db_password']);
        }

        if (!empty($data['db_username']) && str_contains((string) $data['db_username'], '@')) {
            notify()->error('DB Username doit être un utilisateur PostgreSQL, pas une adresse e-mail. Laissez vide pour l\'installation standard.');
            return;
        }

        // Ensure db_name is set correctly (no modification)
        $dbName = trim($data['db_name']);
        if (empty($dbName)) {
            notify()->error('Le nom de la base de données est requis.');
            return;
        }

        $tenant->fill($data);
        $tenant->db_name = $dbName; // Explicitly set to ensure no modification
        $tenant->type = $data['type'] ?? config('tenant_types.default', 'retail');
        $tenant->multi_store_setup_status = $tenant->multi_store_enabled ? 'pending' : 'disabled';
        $tenant->multi_store_setup_error = null;
        if (!$tenant->multi_store_enabled) {
            $tenant->multi_store_enabled_at = null;
        }
        
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

            $message = $tenant->multi_store_enabled
                ? 'Vendeur créé. Provisionnement multi-magasins en cours (1–2 min) — consultez Santé vendeurs.'
                : 'Vendeur créé. Provisionnement en cours (1–2 min) — consultez Santé vendeurs.';
            notify()->success($message);
        } else {
            notify()->success('Vendeur mis à jour avec succès.');
        }

        $this->redirect(route('system.tenants'), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.tenant-form')
            ->layout('layouts.app', [
                'title' => $this->tenantId ? 'Modifier vendeur' : 'Créer vendeur',
                'subtitle' => 'Gestion des boutiques',
            ]);
    }
}
