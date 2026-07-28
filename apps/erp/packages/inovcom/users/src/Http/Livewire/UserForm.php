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
        $isMultiStore = Schema::connection('tenant')->hasTable('stores');
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
        }

        $user = $this->userId
            ? User::on('tenant')->findOrFail($this->userId)
            : User::on('tenant')->make();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_active = $data['is_active'];
        $user->assigned_store_id = $data['assigned_store_id'] ?? null;
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
        $stores = Schema::connection('tenant')->hasTable('stores')
            ? DB::connection('tenant')->table('stores')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('inovcom-users::livewire.users.form')
            ->layout('layouts.app', [
                'title' => $this->userId ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur',
                'subtitle' => '',
            ])
            ->with([
                'roles' => Role::on('tenant')->orderBy('name')->get(),
                'stores' => $stores,
            ]);
    }
}
