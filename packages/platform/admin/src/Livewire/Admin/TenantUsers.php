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

    public bool $limitExceeded = false;

    public int $activeCount = 0;

    public int $totalCount = 0;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->syncMetrics(notifyOnExceed: true);
    }

    public function refreshMetrics(): void
    {
        $this->syncMetrics(notifyOnExceed: true);
        if ($this->limitExceeded) {
            notify()->warning(
                "Plafond dépassé : {$this->activeCount} actif(s) / {$this->tenant->max_users} autorisé(s) pour « {$this->tenant->code} »."
            );
        } else {
            notify()->success('Compteur utilisateurs actualisé.');
        }
    }

    public function toggleUserActive(int $userId): void
    {
        try {
            $this->connectTenantDb();

            if (! Schema::connection('tenant')->hasColumn('users', 'is_active')) {
                notify()->error('Ce tenant ne gère pas le statut actif/inactif des utilisateurs.');

                return;
            }

            $user = DB::connection('tenant')->table('users')->where('id', $userId)->first();
            if (! $user) {
                notify()->error('Utilisateur introuvable.');

                return;
            }

            $next = ! (bool) $user->is_active;
            DB::connection('tenant')->table('users')->where('id', $userId)->update([
                'is_active' => $next,
                'updated_at' => now(),
            ]);

            $this->syncMetrics(notifyOnExceed: false);

            notify()->success(
                $next
                    ? "Utilisateur « {$user->email} » réactivé."
                    : "Utilisateur « {$user->email} » désactivé (n’occupe plus un siège facturable)."
            );
        } catch (\Throwable $e) {
            notify()->error('Impossible de modifier l’utilisateur : '.$e->getMessage());
        }
    }

    private function syncMetrics(bool $notifyOnExceed): void
    {
        $result = app(CompanyIntelligenceService::class)->refresh($this->tenant, true);
        $this->tenant->refresh();
        $this->limitExceeded = (bool) ($result['users_limit_exceeded'] ?? false);
        $this->activeCount = (int) ($result['users_count'] ?? 0);
        $this->totalCount = (int) ($result['users_total'] ?? $this->activeCount);

        if ($notifyOnExceed && ! empty($result['users_limit_newly_exceeded'])) {
            notify()->warning(
                "Alerte sièges : « {$this->tenant->code} » a dépassé le plafond ({$this->activeCount}/{$this->tenant->max_users})."
            );
        }
    }

    private function connectTenantDb(): void
    {
        config(['database.connections.tenant' => $this->tenant->databaseConfig()]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    public function render()
    {
        $users = collect();
        $this->error = '';
        $canToggleActive = false;

        try {
            $this->connectTenantDb();

            if (! Schema::connection('tenant')->hasTable('users')) {
                $this->error = 'Table users absente (provisionnement incomplet).';
            } else {
                $canToggleActive = Schema::connection('tenant')->hasColumn('users', 'is_active');
                $columns = ['id', 'name', 'email', 'created_at'];
                if (Schema::connection('tenant')->hasColumn('users', 'phone')) {
                    $columns[] = 'phone';
                }
                if ($canToggleActive) {
                    $columns[] = 'is_active';
                }

                $users = DB::connection('tenant')->table('users')->orderBy('id')->get($columns);

                // Live exceedance from listed rows (authoritative for this page)
                if ($canToggleActive && $this->tenant->hasUsersLimit()) {
                    $liveActive = $users->where('is_active', true)->count();
                    $this->activeCount = $liveActive;
                    $this->totalCount = $users->count();
                    $this->limitExceeded = $liveActive > (int) $this->tenant->max_users;
                } elseif ($this->tenant->hasUsersLimit()) {
                    $this->activeCount = $users->count();
                    $this->totalCount = $users->count();
                    $this->limitExceeded = $users->count() > (int) $this->tenant->max_users;
                } else {
                    $this->activeCount = $canToggleActive
                        ? $users->where('is_active', true)->count()
                        : $users->count();
                    $this->totalCount = $users->count();
                    $this->limitExceeded = false;
                }
            }
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }

        return view('livewire.admin.tenant-users', [
            'users' => $users,
            'canToggleActive' => $canToggleActive,
        ])->layout('layouts.app', [
            'title' => 'Utilisateurs · '.$this->tenant->code,
            'subtitle' => $this->tenant->name,
        ]);
    }
}
