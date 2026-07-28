<?php

namespace InovCom\Users\Http\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InovCom\Users\Models\Role;
use InovCom\Users\Models\User;
use Livewire\Component;

class UserForm extends Component
{
    public ?int $userId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public array $role_ids = [];
    public bool $is_active = true;
    public ?int $assigned_store_id = null;
    public ?int $assigned_agence_id = null;

    public function mount(?User $user = null): void
    {
        if (!$user) {
            // Reset all fields for new user
            $this->userId = null;
            $this->name = '';
            $this->email = '';
            $this->password = '';
            $this->password_confirmation = '';
            $this->role_ids = [];
            $this->is_active = true;
            $this->assigned_store_id = null;
            $this->assigned_agence_id = null;
            return;
        }
        // Editing existing user - don't prefill password fields
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = ''; // Explicitly clear password
        $this->password_confirmation = ''; // Explicitly clear password confirmation
        $this->role_ids = $user->roles->pluck('id')->all();
        $this->is_active = $user->is_active;
        $this->assigned_store_id = $user->assigned_store_id;
        $this->assigned_agence_id = Schema::connection('tenant')->hasColumn('users', 'assigned_agence_id')
            ? $user->assigned_agence_id
            : null;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique(User::class, 'email')->ignore($this->userId)],
            'role_ids' => 'array',
            'role_ids.*' => Rule::exists(Role::class, 'id'),
            'is_active' => 'boolean',
            'assigned_store_id' => 'nullable|integer',
            'assigned_agence_id' => 'nullable|integer',
        ];
        
        // Password validation: required for new users, optional for updates
        if (!$this->userId) {
            // Creating new user - password is required
            $rules['password'] = 'required|string|min:8|confirmed';
        } else {
            // Updating existing user - password is optional, but if provided, must be confirmed
            if (!empty($this->password)) {
                $rules['password'] = 'string|min:8|confirmed';
            }
        }
        
        $data = $this->validate($rules);
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        $isPressing = $tenant && (($tenant->type ?? null) === 'pressing');
        $isMultiStore = ! $isPressing
            && Schema::connection('tenant')->hasTable('stores')
            && ($tenant?->multi_store_enabled ?? true);

        if ($isMultiStore) {
            $stores = DB::connection('tenant')->table('stores')->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (!empty($data['assigned_store_id']) && !in_array((int) $data['assigned_store_id'], $stores, true)) {
                $this->addError('assigned_store_id', 'Boutique invalide.');
                return;
            }
            if (empty($data['assigned_store_id'])) {
                $this->addError('assigned_store_id', 'La boutique est obligatoire.');
                return;
            }
        } else {
            $data['assigned_store_id'] = null;
        }

        $hasAgences = Schema::connection('tenant')->hasTable('agences');
        if ($hasAgences && Schema::connection('tenant')->hasColumn('users', 'assigned_agence_id')) {
            if (!empty($data['assigned_agence_id'])) {
                $ok = DB::connection('tenant')->table('agences')->where('id', (int) $data['assigned_agence_id'])->exists();
                if (!$ok) {
                    $this->addError('assigned_agence_id', 'Agence invalide.');
                    return;
                }
            } elseif ($isPressing) {
                $this->addError('assigned_agence_id', 'L’agence de travail est obligatoire.');
                return;
            }
        }

        $user = $this->userId
            ? User::on('tenant')->findOrFail($this->userId)
            : User::on('tenant')->make();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_active = $data['is_active'];
        $user->assigned_store_id = $data['assigned_store_id'] ?? null;
        if (Schema::connection('tenant')->hasColumn('users', 'assigned_agence_id')) {
            $user->assigned_agence_id = $data['assigned_agence_id'] ?? null;
        }
        if (!$this->userId) {
            $user->remember_token = Str::random(10);
        }
        if (!empty($data['password'] ?? '')) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->roles()->sync($data['role_ids'] ?? []);

        notify()->success($this->userId ? 'Utilisateur mis à jour avec succès.' : 'Utilisateur créé avec succès.');
        
        return $this->redirect(route('tenant.users.index', ['tenant' => $this->tenantCode()]), navigate: false);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    public function render()
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        $isPressing = $tenant && (($tenant->type ?? null) === 'pressing');
        $showStores = ! $isPressing
            && Schema::connection('tenant')->hasTable('stores')
            && ($tenant?->multi_store_enabled ?? false);

        $stores = $showStores
            ? DB::connection('tenant')->table('stores')->orderBy('name')->get(['id', 'name'])
            : collect();

        $agences = Schema::connection('tenant')->hasTable('agences')
            ? DB::connection('tenant')->table('agences')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return view('inovcom-users::livewire.users.form')
            ->layout('layouts.app', [
                'title' => $this->userId ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur',
                'subtitle' => '',
            ])
            ->with([
                'roles' => Role::on('tenant')->orderBy('name')->get(),
                'stores' => $stores,
                'agences' => $agences,
                'canAssignAgence' => Schema::connection('tenant')->hasColumn('users', 'assigned_agence_id'),
                'isPressingTenant' => $isPressing,
            ]);
    }
}
