<?php

namespace InovCom\Invoicing\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use InovCom\Clients\Models\Client;
use InovCom\Clients\Models\ClientNote;
use InovCom\Clients\Models\ClientReminder;
use InovCom\Invoicing\Exports\CollectionReminderExcelExporter;
use InovCom\Invoicing\Services\CollectionReminderService;
use InovCom\Users\Models\User;
use Livewire\Component;

class CollectionRemindersIndex extends Component
{
    public string $clientFilter = '';
    public string $clientSearch = '';
    public string $invoiceDateFrom = '';
    public string $invoiceDateTo = '';
    public string $dueDateFrom = '';
    public string $dueDateTo = '';
    public int $minDaysOverdue = 0;
    public string $commercialFilter = '';
    public string $paymentStatusFilter = 'all';

    public string $appliedClientFilter = '';
    public string $appliedClientSearch = '';
    public string $appliedInvoiceDateFrom = '';
    public string $appliedInvoiceDateTo = '';
    public string $appliedDueDateFrom = '';
    public string $appliedDueDateTo = '';
    public int $appliedMinDaysOverdue = 0;
    public string $appliedCommercialFilter = '';
    public string $appliedPaymentStatusFilter = 'all';

    public bool $filtersApplied = false;

    /** @var array<int,int> niveau de relance choisi par client */
    public array $levels = [];

    /** @var array<int,string> canal de relance choisi par client */
    public array $channels = [];

    public function mount(): void
    {
        if (!$this->can('invoicing.collection.view')) {
            abort(403);
        }

        $this->applyFilters();
    }

    public function applyFilters(): void
    {
        $this->appliedClientFilter = $this->clientFilter;
        $this->appliedClientSearch = $this->clientSearch;
        $this->appliedInvoiceDateFrom = $this->invoiceDateFrom;
        $this->appliedInvoiceDateTo = $this->invoiceDateTo;
        $this->appliedDueDateFrom = $this->dueDateFrom;
        $this->appliedDueDateTo = $this->dueDateTo;
        $this->appliedMinDaysOverdue = $this->minDaysOverdue;
        $this->appliedCommercialFilter = $this->commercialFilter;
        $this->appliedPaymentStatusFilter = $this->paymentStatusFilter;
        $this->filtersApplied = true;
    }

    public function resetFilters(): void
    {
        $this->clientFilter = '';
        $this->clientSearch = '';
        $this->invoiceDateFrom = '';
        $this->invoiceDateTo = '';
        $this->dueDateFrom = '';
        $this->dueDateTo = '';
        $this->minDaysOverdue = 0;
        $this->commercialFilter = '';
        $this->paymentStatusFilter = 'all';

        $this->appliedClientFilter = '';
        $this->appliedClientSearch = '';
        $this->appliedInvoiceDateFrom = '';
        $this->appliedInvoiceDateTo = '';
        $this->appliedDueDateFrom = '';
        $this->appliedDueDateTo = '';
        $this->appliedMinDaysOverdue = 0;
        $this->appliedCommercialFilter = '';
        $this->appliedPaymentStatusFilter = 'all';
        $this->filtersApplied = false;
    }

    /**
     * Enregistre une relance pour un client. La relance est stockée dans la
     * table partagée `client_reminders`, donc immédiatement visible dans la
     * vue 360° du client (onglet Relances).
     */
    public function recordReminder(int $clientId): void
    {
        if (!$this->can('invoicing.collection.view')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        if (!Schema::connection('tenant')->hasTable('client_reminders')) {
            session()->flash('error', 'Le module de relances clients n\'est pas disponible.');
            return;
        }

        $client = Client::find($clientId);
        if (!$client) {
            session()->flash('error', 'Client introuvable.');
            return;
        }

        // Recalcule le solde échu du client à partir des mêmes filtres.
        $service = app(CollectionReminderService::class);
        $filters = $this->filters();
        $filters['client_id'] = $clientId;
        $groups = $service->groupedOverdueInvoices($filters);
        $balance = $groups->isNotEmpty() ? (float) $groups->first()['total_balance'] : 0.0;

        $level = max(1, min(3, (int) ($this->levels[$clientId] ?? $this->suggestLevel($clientId))));
        $channel = $this->channels[$clientId] ?? 'email';
        if (!array_key_exists($channel, ClientReminder::CHANNELS)) {
            $channel = 'email';
        }

        ClientReminder::create([
            'client_id' => $clientId,
            'level' => $level,
            'channel' => $channel,
            'amount_due' => $balance,
            'due_date' => null,
            'sent_at' => now(),
            'status' => 'sent',
            'notes' => 'Relance facturation — créances échues (' . fmt_money($balance) . ' FCFA)',
            'created_by' => Auth::guard('tenant')->id(),
        ]);

        // Journalise dans la timeline du client pour traçabilité.
        if (Schema::connection('tenant')->hasTable('client_notes')) {
            ClientNote::create([
                'client_id' => $clientId,
                'body' => 'Relance niveau ' . $level . ' (' . ClientReminder::CHANNELS[$channel] . ') — ' . fmt_money($balance) . ' FCFA [Facturation]',
                'type' => 'reminder',
                'author_id' => Auth::guard('tenant')->id(),
            ]);
        }

        $this->levels[$clientId] = min(3, $level + 1);
        session()->flash('success', 'Relance enregistrée pour ' . $client->name . '. Elle est visible dans la fiche client (onglet Relances).');
    }

    private function suggestLevel(int $clientId): int
    {
        if (!Schema::connection('tenant')->hasTable('client_reminders')) {
            return 1;
        }

        $count = ClientReminder::where('client_id', $clientId)->count();

        return max(1, min(3, $count + 1));
    }

    public function exportExcel()
    {
        if (!$this->can('invoicing.collection.export')) {
            session()->flash('error', 'Permission refusée.');
            return null;
        }

        $service = app(CollectionReminderService::class);
        $filters = $this->filters();
        $groups = $service->groupedOverdueInvoices($filters);
        $totals = $service->globalTotals($groups);

        return CollectionReminderExcelExporter::download(
            'relance-factures-' . now()->format('Y-m-d') . '.xls',
            $groups,
            $totals
        );
    }

    public function render()
    {
        $service = app(CollectionReminderService::class);
        $filters = $this->filters();
        $groups = $service->groupedOverdueInvoices($filters);
        $totals = $service->globalTotals($groups);

        // Statistiques de relances déjà enregistrées (visibilité + niveau suggéré).
        $reminderStats = [];
        $remindersAvailable = Schema::connection('tenant')->hasTable('client_reminders');
        if ($remindersAvailable && $groups->isNotEmpty()) {
            $clientIds = $groups->pluck('client.id')->filter()->all();
            if (!empty($clientIds)) {
                $reminderStats = ClientReminder::whereIn('client_id', $clientIds)
                    ->selectRaw('client_id, count(*) as cnt, max(sent_at) as last_sent')
                    ->groupBy('client_id')
                    ->get()
                    ->keyBy('client_id');

                // Pré-remplit niveau/canal pour chaque client affiché.
                foreach ($clientIds as $cid) {
                    $this->levels[$cid] ??= $this->suggestLevel($cid);
                    $this->channels[$cid] ??= 'email';
                }
            }
        }

        return view('inovcom-invoicing::livewire.collection-reminders.index')
            ->layout('layouts.app', [
                'title' => 'Fiches de relance',
                'subtitle' => 'Créances clients échues',
            ])
            ->with([
                'groups' => $groups,
                'totals' => $totals,
                'clients' => Client::orderBy('name')->get(['id', 'name', 'code']),
                'commercials' => User::orderBy('name')->get(['id', 'name']),
                'canExport' => $this->can('invoicing.collection.export'),
                'tenantCode' => $this->tenantCode(),
                'filterQueryParams' => $this->filterQueryParams(),
                'reminderStats' => $reminderStats,
                'remindersAvailable' => $remindersAvailable,
                'reminderLevels' => ClientReminder::LEVELS,
                'reminderChannels' => ClientReminder::CHANNELS,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filterQueryParams(?int $clientId = null): array
    {
        return array_filter([
            'tenant' => $this->tenantCode(),
            'client_id' => $clientId ?: ($this->appliedClientFilter ?: null),
            'client_search' => $this->appliedClientSearch ?: null,
            'invoice_date_from' => $this->appliedInvoiceDateFrom ?: null,
            'invoice_date_to' => $this->appliedInvoiceDateTo ?: null,
            'due_date_from' => $this->appliedDueDateFrom ?: null,
            'due_date_to' => $this->appliedDueDateTo ?: null,
            'min_days_overdue' => $this->appliedMinDaysOverdue > 0 ? $this->appliedMinDaysOverdue : null,
            'commercial_id' => $this->appliedCommercialFilter ?: null,
            'payment_status' => $this->appliedPaymentStatusFilter !== 'all' ? $this->appliedPaymentStatusFilter : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        return app(CollectionReminderService::class)->filtersFromRequest([
            'client_id' => $this->appliedClientFilter,
            'client_search' => $this->appliedClientSearch,
            'invoice_date_from' => $this->appliedInvoiceDateFrom,
            'invoice_date_to' => $this->appliedInvoiceDateTo,
            'due_date_from' => $this->appliedDueDateFrom,
            'due_date_to' => $this->appliedDueDateTo,
            'min_days_overdue' => $this->appliedMinDaysOverdue,
            'commercial_id' => $this->appliedCommercialFilter,
            'payment_status' => $this->appliedPaymentStatusFilter,
        ]);
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
