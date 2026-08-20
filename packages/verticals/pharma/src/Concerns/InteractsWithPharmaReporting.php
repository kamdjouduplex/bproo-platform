<?php

namespace Pharma\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Pharma\Services\PharmaReportingService;

trait InteractsWithPharmaReporting
{
    #[Url(as: 'period', except: 'this_month')]
    public string $period = 'this_month';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function setPeriod(string $period): void
    {
        $this->applyPeriodPreset($period);
    }

    public function updatedPeriod(): void
    {
        $this->applyPeriodPreset($this->period);
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function applySearch(): void
    {
        if ($this->period === 'custom') {
            $this->applyCustomPeriod();

            return;
        }
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function applyCustomPeriod(): void
    {
        $this->period = 'custom';
        if ($this->dateFrom === '' || $this->dateTo === '') {
            $this->applyPeriodPreset('this_month');
        }
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function resetPeriod(): void
    {
        $this->applyPeriodPreset('this_month');
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    protected function bootReporting(): void
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            abort(403);
        }
        $allowed = (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'hasPermission') && (
                $user->hasPermission('reporting.view')
                || $user->hasPermission('pharma.view')
            ));
        if (! $allowed) {
            abort(403);
        }

        if ($this->period === 'custom') {
            if ($this->dateFrom === '') {
                $this->dateFrom = (string) request()->query('date_from', '');
            }
            if ($this->dateTo === '') {
                $this->dateTo = (string) request()->query('date_to', '');
            }
        }

        if ($this->period !== 'custom' || $this->dateFrom === '' || $this->dateTo === '') {
            $this->applyPeriodPreset($this->period !== '' ? $this->period : 'this_month');
        }
    }

    protected function applyPeriodPreset(string $period): void
    {
        $allowed = ['today', 'last_7_days', 'this_month', 'last_month', 'this_year', 'custom'];
        if (! in_array($period, $allowed, true)) {
            $period = 'this_month';
        }
        $this->period = $period;
        if ($period === 'custom') {
            if ($this->dateFrom === '') {
                $this->dateFrom = now()->startOfMonth()->toDateString();
            }
            if ($this->dateTo === '') {
                $this->dateTo = now()->toDateString();
            }

            return;
        }

        [$from, $to] = app(PharmaReportingService::class)->presetBounds($period);
        $this->dateFrom = $from->toDateString();
        $this->dateTo = $to->toDateString();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function currentRange(): array
    {
        return app(PharmaReportingService::class)->resolveRange($this->period, $this->dateFrom, $this->dateTo);
    }

    protected function canExport(): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return false;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && (
            $user->hasPermission('reporting.export')
            || $user->hasPermission('reporting.view')
        );
    }

    protected function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    protected function currency(): string
    {
        $tenant = app(\App\Services\TenantManager::class)->tenant();

        return $tenant ? (string) $tenant->getSetting('currency', 'XOF') : 'XOF';
    }

    protected function periodQuery(): array
    {
        return array_filter([
            'tenant' => $this->tenantCode(),
            'period' => $this->period,
            'date_from' => $this->period === 'custom' ? $this->dateFrom : null,
            'date_to' => $this->period === 'custom' ? $this->dateTo : null,
        ]);
    }

    protected function reportingLayout(string $title, string $subtitle): array
    {
        return [
            'title' => $title,
            'subtitle' => $subtitle,
        ];
    }

    /**
     * @param  array<int, array{percent: float, color: string}>  $slices
     */
    protected function conicGradient(array $slices): string
    {
        if ($slices === []) {
            return 'conic-gradient(#e2e8f0 0 100%)';
        }
        $parts = [];
        $cursor = 0.0;
        foreach ($slices as $slice) {
            $next = $cursor + (float) $slice['percent'];
            $parts[] = $slice['color'].' '.$cursor.'% '.$next.'%';
            $cursor = $next;
        }
        if ($cursor < 100) {
            $parts[] = '#e2e8f0 '.$cursor.'% 100%';
        }

        return 'conic-gradient('.implode(', ', $parts).')';
    }
}
