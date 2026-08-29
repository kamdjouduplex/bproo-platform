<?php

namespace InovCom\Treasury\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use InovCom\Treasury\Models\TreasuryCommitment;
use InovCom\Treasury\Services\TreasuryForecastService;
use InovCom\Treasury\Services\TreasuryService;
use Livewire\Component;

class TreasuryDashboard extends Component
{
    public int $horizonDays = 90;
    public string $direction = 'out';

    public function markPaid(int $commitmentId, string $date): void
    {
        if (!$this->can('treasury.update')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $commitment = TreasuryCommitment::findOrFail($commitmentId);
        app(TreasuryService::class)->markPaid($commitment, $date);
        session()->flash('success', 'Échéance marquée comme payée.');
    }

    public function render()
    {
        $forecast = app(TreasuryForecastService::class)->build(now(), 90);
        $tenantCode = $this->tenantCode();

        $rows = $this->direction === 'in' ? $forecast['inflows'] : $forecast['outflows'];
        $horizonEnd = now()->copy()->startOfDay()->addDays($this->horizonDays);
        $rows = array_values(array_filter($rows, function (array $row) use ($horizonEnd) {
            if ($row['urgency']['key'] === \InovCom\Treasury\Support\TreasuryUrgency::OVERDUE) {
                return true;
            }

            return $row['due_date']->lte($horizonEnd);
        }));

        foreach ($rows as &$row) {
            $row['url'] = $this->resolveUrl($row, $tenantCode);
        }
        unset($row);

        return view('inovcom-treasury::livewire.dashboard')
            ->layout('layouts.app', [
                'title' => 'Prévision de trésorerie',
                'subtitle' => 'Échéancier des dépenses',
            ])
            ->with([
                'kpis' => $forecast['kpis'],
                'thresholds' => $forecast['thresholds'],
                'rows' => $rows,
                'canCreate' => $this->can('treasury.create'),
                'canUpdate' => $this->can('treasury.update'),
                'canSettings' => $this->can('treasury.manage_settings'),
            ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveUrl(array $row, ?string $tenantCode): ?string
    {
        if (empty($row['url_name']) || !Route::has($row['url_name'])) {
            if (($row['source'] ?? '') === 'manual') {
                return route('tenant.treasury.edit', ['commitment' => $row['source_id'], 'tenant' => $tenantCode]);
            }

            return null;
        }

        return route($row['url_name'], array_merge($row['url_params'] ?? [], ['tenant' => $tenantCode]));
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
