<?php

namespace InovCom\Clients\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\ModuleRegistry;
use App\Services\TenantManager;
use InovCom\Clients\Models\Client;
use Livewire\Component;

class ClientAccountView extends Component
{
    use AuthorizesWithTenant;

    public Client $client;

    public function mount(Client $client): void
    {
        $this->tenantAuthorize('clients.view');
        $this->client = $client;
    }

    public function render()
    {
        $tenant   = app(TenantManager::class)->tenant();
        $registry = app(ModuleRegistry::class);

        $on = fn (string $key): bool =>
            $registry->isEnabled($key, $tenant) && class_exists(match ($key) {
                'offres'       => \InovCom\Offres\Models\Offer::class,
                'devis'        => \InovCom\Devis\Models\Quote::class,
                'projets'      => \InovCom\Projets\Models\Project::class,
                'facturation'  => \InovCom\Facturation\Models\Invoice::class,
                'maintenance'  => \InovCom\Maintenance\Models\MaintenanceContract::class,
                'planning'     => \InovCom\Planning\Models\Appointment::class,
                default        => 'stdClass',
            });

        // ── Commercial pipeline ──────────────────────────────────────────
        $offres = $on('offres')
            ? \InovCom\Offres\Models\Offer::where('client_id', $this->client->id)
                ->latest('received_at')->limit(15)->get()
            : collect();

        $devis = $on('devis')
            ? \InovCom\Devis\Models\Quote::where('client_id', $this->client->id)
                ->latest()->limit(15)->get()
            : collect();

        // ── Projects ─────────────────────────────────────────────────────
        $projets = $on('projets')
            ? \InovCom\Projets\Models\Project::where('client_id', $this->client->id)
                ->orderByDesc('start_date')->limit(15)->get()
            : collect();

        // ── Finance ───────────────────────────────────────────────────────
        $factures = $on('facturation')
            ? \InovCom\Facturation\Models\Invoice::where('client_id', $this->client->id)
                ->latest('issue_date')->limit(15)->get()
            : collect();

        // ── Maintenance ───────────────────────────────────────────────────
        $contracts = $on('maintenance') && class_exists(\InovCom\Maintenance\Models\MaintenanceContract::class)
            ? \InovCom\Maintenance\Models\MaintenanceContract::where('client_id', $this->client->id)
                ->orderByDesc('start_date')->limit(10)->get()
            : collect();

        $orders = $on('maintenance') && class_exists(\InovCom\Maintenance\Models\MaintenanceOrder::class)
            ? \InovCom\Maintenance\Models\MaintenanceOrder::where('client_id', $this->client->id)
                ->orderByDesc('reported_at')->limit(10)->get()
            : collect();

        // ── Planning ──────────────────────────────────────────────────────
        $appointments = $on('planning')
            ? \InovCom\Planning\Models\Appointment::where('client_id', $this->client->id)
                ->orderByDesc('start_at')->limit(10)->get()
            : collect();

        // ── Client contacts & activity log ────────────────────────────────
        $contacts   = $this->client->contacts;
        $activities = $this->client->activities()->with('user')->limit(30)->get();

        // ── KPI cards ─────────────────────────────────────────────────────
        $kpis = [
            'offres'            => $offres->count(),
            'devis_count'       => $devis->count(),
            'devis_total_ttc'   => $devis->sum('total_ttc'),
            'devis_acceptes'    => $devis->where('status', 'accepted')->count(),
            'projets_actifs'    => $projets->whereIn('status', ['active', 'in_progress'])->count(),
            'projets_total'     => $projets->count(),
            'factures_total'    => $factures->sum('total_ttc'),
            'factures_dues'     => $factures->whereNotIn('status', ['paid', 'cancelled'])->sum('amount_due'),
            'contrats_actifs'   => $contracts->where('status', 'active')->count(),
        ];

        return view('inovcom-clients::livewire.clients.account-view', compact(
            'offres', 'devis', 'projets', 'factures',
            'contracts', 'orders', 'appointments',
            'contacts', 'activities', 'kpis'
        ))->layout('layouts.app', [
            'title'    => $this->client->name,
            'subtitle' => $this->client->code . ' — ' . __('Vue 360°'),
        ]);
    }
}
