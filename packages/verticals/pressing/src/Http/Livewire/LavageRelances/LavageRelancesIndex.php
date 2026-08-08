<?php

namespace Pressing\Http\Livewire\LavageRelances;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use InovCom\Tickets\Services\TicketsService;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\Agence;
use Pressing\Models\PressingClient;
use Pressing\Support\PressingAgenceContext;
use Pressing\Support\PressingWorkflow;

class LavageRelancesIndex extends Component
{
    use AuthorizesPressingActions;
    use WithPagination;

    public string $search = '';
    public ?int $agenceFilter = null;
    public bool $onlyActive = true;
    public string $sinceDate = '';

    public int $perPage = 15;

    public bool $canViewAllAgences = false;

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_lavage_relances.view');

        $this->canViewAllAgences = PressingAgenceContext::canViewAllAgences();
        if (! $this->canViewAllAgences) {
            $this->agenceFilter = PressingAgenceContext::userAgenceId();
        }

        $this->sinceDate = now()->subMonths(1)->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSinceDate(): void
    {
        $this->resetPage();
    }

    public function updatedAgenceFilter($value): void
    {
        if (! $this->canViewAllAgences) {
            $this->agenceFilter = PressingAgenceContext::userAgenceId();
        }
        $this->resetPage();
    }

    public function updatedOnlyActive(): void
    {
        $this->resetPage();
    }

    public function relaunch(int $clientId): void
    {
        $this->authorizePressingAction('pressing_lavage_relances.relaunch');

        if (! Schema::connection('tenant')->hasTable('tickets')) {
            session()->flash('error', __('Le module Tickets n’est pas disponible pour ce tenant.'));
            return;
        }

        $client = PressingClient::query()->findOrFail($clientId);

        if (! $this->canViewAllAgences) {
            $allowedAgenceId = PressingAgenceContext::userAgenceId();
            abort_unless((int) $client->agence_id === (int) $allowedAgenceId, 403, 'Action non autorisée.');
        }

        $stageName = PressingWorkflow::STAGE_LAVAGE;

        $latest = DB::connection('tenant')->table('order_stage_history as osh')
            ->join('pressing_orders as po', 'po.id', '=', 'osh.order_id')
            ->where('po.client_id', $clientId)
            ->where('osh.stage_name', $stageName)
            // "Dépôt lavage" = date de réception (received_at) du dernier ordre ayant atteint "Lavage".
            ->orderByDesc('po.received_at')
            ->select([
                'po.number as last_order_number',
                'po.received_at as last_lavage_received_at',
            ])
            ->first();

        $lastAt = $latest?->last_lavage_received_at
            ? Carbon::parse($latest->last_lavage_received_at)->timezone(config('app.timezone'))
            : null;
        $days = $lastAt ? (int) $lastAt->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null;
        $daysLabel = $days === null
            ? __('Jamais')
            : ($days === 0
                ? __('Aujourd’hui')
                : trans_choice(':count jour|:count jours', $days, ['count' => $days]));
        $lastDate = $lastAt ? $lastAt->format('d/m/Y H:i') : __('Aucun dépôt lavage trouvé');

        $lastOrderNumber = $latest?->last_order_number ?? '—';

        $ticket = app(TicketsService::class)->createTicket([
            'title' => __('Relance dépôt lavage — :client', ['client' => $client->full_name]),
            'description' => implode("\n", [
                'Client : ' . $client->full_name,
                'WhatsApp : ' . ($client->whatsapp ?: '—'),
                'Téléphone : ' . ($client->phone ?: '—'),
                'Dernier dépôt lavage : ' . $daysLabel . ($lastAt ? ' (' . $lastDate . ')' : ''),
                'Commande associée : ' . $lastOrderNumber,
                '',
                'Objectif : contacter le client et noter la raison pour laquelle il ne revient plus.',
            ]),
            'priority' => 'normal',
            'category' => 'pressing',
        ]);

        $tenantCode = request()->query('tenant') ?? session('tenant_code');
        $ticketUrl = route('tenant.tickets.show', [
            'ticket' => $ticket->id,
            'tenant' => $tenantCode,
        ]);

        session()->flash('success', __('Ticket créé : :n — ouvrez le module Tickets (menu Système) pour le suivre.', [
            'n' => $ticket->ticket_number,
        ]));
        session()->flash('ticket_url', $ticketUrl);
        session()->flash('ticket_number', $ticket->ticket_number);
    }

    public function render()
    {
        $this->authorizePressingAction('pressing_lavage_relances.view');

        $stageName = PressingWorkflow::STAGE_LAVAGE;
        $since = Carbon::parse($this->sinceDate)->startOfDay();

        $likeOp = DB::connection('tenant')->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $term = '%' . trim($this->search) . '%';

        $latestLavageByClient = DB::connection('tenant')->table('pressing_orders as po')
            ->join('order_stage_history as osh', 'osh.order_id', '=', 'po.id')
            ->select([
                'po.client_id as client_id',
                // Date du dépôt (reception) sur les commandes ayant atteint l’étape "Lavage".
                DB::raw('MAX(po.received_at) as last_lavage_at'),
            ])
            ->where('osh.stage_name', $stageName)
            ->groupBy('po.client_id');

        $clientsQuery = DB::connection('tenant')->table('pressing_clients as pc')
            ->leftJoinSub($latestLavageByClient, 'll', 'll.client_id', '=', 'pc.id')
            ->leftJoin('agences as ag', 'ag.id', '=', 'pc.agence_id')
            ->select([
                'pc.id',
                'pc.code',
                'pc.first_name',
                'pc.last_name',
                'pc.whatsapp',
                'pc.phone',
                'pc.email',
                'pc.address',
                'pc.is_active',
                'pc.notes',
                'pc.agence_id',
                DB::raw('ag.name as agence_name'),
                DB::raw('ll.last_lavage_at as last_lavage_at'),
            ])
            ->when($this->onlyActive, fn ($q) => $q->where('pc.is_active', true))
            ->when($this->agenceFilter, fn ($q) => $q->where('pc.agence_id', $this->agenceFilter))
            ->when($this->search !== '', function ($q) use ($likeOp, $term) {
                $q->where(function ($inner) use ($likeOp, $term) {
                    $inner->where('pc.last_name', $likeOp, $term)
                        ->orWhere('pc.first_name', $likeOp, $term)
                        ->orWhere('pc.whatsapp', $likeOp, $term)
                        ->orWhere('pc.phone', $likeOp, $term)
                        ->orWhere('pc.code', $likeOp, $term);
                });
            })
            ->where(function ($q) use ($since) {
                $q->whereNull('ll.last_lavage_at')
                    ->orWhere('ll.last_lavage_at', '<', $since);
            })
            ->orderByRaw('CASE WHEN ll.last_lavage_at IS NULL THEN 0 ELSE 1 END, ll.last_lavage_at ASC');

        $clients = $clientsQuery->paginate($this->perPage);

        $clientIds = collect($clients->items())->pluck('id')->filter()->values()->all();

        $lastOrderByClient = [];
        if (! empty($clientIds)) {
            $latestLavageOrderAtByClient = DB::connection('tenant')->table('order_stage_history as osh')
                ->join('pressing_orders as po', 'po.id', '=', 'osh.order_id')
                ->select([
                    'po.client_id as client_id',
                    DB::raw('MAX(po.received_at) as last_lavage_at'),
                ])
                ->where('osh.stage_name', $stageName)
                ->whereIn('po.client_id', $clientIds)
                ->groupBy('po.client_id');

            $lastOrders = DB::connection('tenant')->table('order_stage_history as osh')
                ->join('pressing_orders as po', 'po.id', '=', 'osh.order_id')
                ->joinSub($latestLavageOrderAtByClient, 'x', function ($join) {
                    $join->on('x.client_id', '=', 'po.client_id')
                        ->on('x.last_lavage_at', '=', 'po.received_at');
                })
                ->select([
                    'po.client_id as client_id',
                    'po.number as last_order_number',
                    DB::raw('po.received_at as last_lavage_received_at'),
                ])
                ->where('osh.stage_name', $stageName)
                ->get();

            $lastOrderByClient = $lastOrders
                ->mapWithKeys(fn ($r) => [
                    (int) $r->client_id => [
                        'order_number' => $r->last_order_number,
                        'moved_at' => $r->last_lavage_received_at,
                    ],
                ])
                ->all();
        }

        $tenantCode = request()->query('tenant') ?? session('tenant_code');

        return view('pressing::livewire.lavage-relances.index', [
            'clients' => $clients,
            'lastOrderByClient' => $lastOrderByClient,
            'agences' => Agence::where('is_active', true)->orderBy('name')->get(),
            'tenantCode' => $tenantCode,
            'canViewAllAgences' => $this->canViewAllAgences,
            'canPrint' => $this->can('pressing_lavage_relances.print'),
            'canRelaunch' => $this->can('pressing_lavage_relances.relaunch'),
            'sinceLabel' => Carbon::parse($this->sinceDate)->format('d/m/Y'),
        ])->layout('layouts.app', [
            'title' => __('Relances dépôt lavage'),
            'subtitle' => __('Clients sans dépôts lavage depuis') . ' ' . Carbon::parse($this->sinceDate)->format('d/m/Y'),
        ]);
    }
}

