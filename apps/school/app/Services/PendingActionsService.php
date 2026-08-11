<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InovCom\Debts\Models\Debt;
use InovCom\Expenses\Models\Expense;
use InovCom\Invoicing\Models\DeliveryNote;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Quotations\Models\Quotation;
use InovCom\Tickets\Models\Ticket;
use InovCom\Users\Models\User;

class PendingActionsService
{
    private const ITEM_LIMIT = 8;

    public function summarize(User $user, Tenant $tenant): array
    {
        $tenantCode = $tenant->code;
        $groups = [];

        if ($this->moduleEnabled($tenant, 'debts') && $this->userCan($user, 'debts.validate')) {
            $group = $this->pendingDebts($tenantCode);
            if ($group['count'] > 0) {
                $groups[] = $group;
            }
        }

        if ($this->moduleEnabled($tenant, 'expenses') && $this->userCan($user, 'expenses.approve')) {
            $group = $this->pendingExpenses($tenantCode);
            if ($group['count'] > 0) {
                $groups[] = $group;
            }
        }

        if ($this->moduleEnabled($tenant, 'invoicing')) {
            if ($this->userCan($user, 'invoicing.delivery.confirm')) {
                $group = $this->pendingDeliveryNotes($tenantCode);
                if ($group['count'] > 0) {
                    $groups[] = $group;
                }
            }
            if ($this->userCan($user, 'invoicing.view')) {
                $group = $this->pendingInvoices($tenantCode);
                if ($group['count'] > 0) {
                    $groups[] = $group;
                }
            }
        }

        if ($this->moduleEnabled($tenant, 'quotations') && $this->userCan($user, 'quotations.validate')) {
            $group = $this->pendingQuotations($tenantCode);
            if ($group['count'] > 0) {
                $groups[] = $group;
            }
        }

        if ($this->moduleEnabled($tenant, 'tickets') && $this->userCan($user, 'tickets.view')) {
            $assigned = $this->assignedTickets($user, $tenantCode);
            if ($assigned['count'] > 0) {
                $groups[] = $assigned;
            }
            if ($this->userCan($user, 'tickets.update')) {
                $unassigned = $this->unassignedTickets($tenantCode);
                if ($unassigned['count'] > 0) {
                    $groups[] = $unassigned;
                }
            }
        }

        // Stock & péremption alerts (pharmacy)
        if ($this->moduleEnabled($tenant, 'batches') && $this->userCan($user, 'batches.view')) {
            $expired = $this->expiredBatches($tenantCode);
            if ($expired['count'] > 0) {
                $groups[] = $expired;
            }
            $nearExpiry = $this->nearExpiryBatches($tenantCode);
            if ($nearExpiry['count'] > 0) {
                $groups[] = $nearExpiry;
            }
        }

        if ($this->moduleEnabled($tenant, 'stock') && $this->userCan($user, 'stock.view')) {
            $lowStock = $this->lowStockAlerts($tenantCode);
            if ($lowStock['count'] > 0) {
                $groups[] = $lowStock;
            }
        }

        $total = array_sum(array_column($groups, 'count'));

        return [
            'total' => $total,
            'groups' => $groups,
        ];
    }

    private function pendingDebts(string $tenantCode): array
    {
        if (!Debt::supportsValidationWorkflow()) {
            return $this->emptyGroup('debts', 'Créances à valider');
        }

        $query = Debt::query()
            ->with('client')
            ->where('is_validated', false)
            ->whereIn('status', ['open', 'partial'])
            ->orderByDesc('created_at');

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(fn (Debt $debt) => [
            'id' => 'debt:' . $debt->id,
            'title' => $debt->reference ?: ('Dette #' . $debt->id),
            'subtitle' => $debt->client?->name ?? 'Sans client',
            'meta' => fmt_money((float) $debt->balance),
            'url' => $this->route('tenant.debts.edit', ['debt' => $debt->id, 'tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'debts',
            'label' => 'Créances à valider',
            'count' => $count,
            'list_url' => $this->route('tenant.debts.index', ['tenant' => $tenantCode, 'validation' => 'pending']),
            'items' => $items,
        ];
    }

    private function pendingExpenses(string $tenantCode): array
    {
        if (!Schema::connection('tenant')->hasTable('expenses')) {
            return $this->emptyGroup('expenses', 'Dépenses à approuver');
        }

        $query = Expense::query()
            ->with('category')
            ->where('status', 'pending')
            ->orderByDesc('expense_date');

        $this->applyStoreScope($query, 'expenses');

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(fn (Expense $expense) => [
            'id' => 'expense:' . $expense->id,
            'title' => $expense->reference ?: ('Dépense #' . $expense->id),
            'subtitle' => $expense->category?->name ?? 'Sans catégorie',
            'meta' => fmt_money((float) $expense->amount),
            'url' => $this->route('tenant.expenses.edit', ['expense' => $expense->id, 'tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'expenses',
            'label' => 'Dépenses à approuver',
            'count' => $count,
            'list_url' => $this->route('tenant.expenses.index', ['tenant' => $tenantCode, 'status' => 'pending']),
            'items' => $items,
        ];
    }

    private function pendingDeliveryNotes(string $tenantCode): array
    {
        if (!Schema::connection('tenant')->hasTable('delivery_notes')) {
            return $this->emptyGroup('delivery_notes', 'BL à confirmer');
        }

        $query = DeliveryNote::query()
            ->with('invoice')
            ->where('status', DeliveryNote::STATUS_DRAFT)
            ->orderByDesc('created_at');

        $this->applyStoreScope($query, 'delivery_notes');

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(fn (DeliveryNote $note) => [
            'id' => 'delivery:' . $note->id,
            'title' => $note->delivery_number,
            'subtitle' => $note->invoice?->invoice_number
                ? 'Facture ' . $note->invoice->invoice_number
                : 'Bon de livraison',
            'meta' => null,
            'url' => $this->route('tenant.invoicing.deliveries.show', ['deliveryNote' => $note->id, 'tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'delivery_notes',
            'label' => 'Bons de livraison à confirmer',
            'count' => $count,
            'list_url' => $this->route('tenant.invoicing.deliveries.index', ['tenant' => $tenantCode, 'status' => 'draft']),
            'items' => $items,
        ];
    }

    private function pendingInvoices(string $tenantCode): array
    {
        if (!Schema::connection('tenant')->hasTable('invoices')) {
            return $this->emptyGroup('invoices', 'Factures impayées');
        }

        $query = Invoice::query()
            ->with('client')
            ->whereIn('status', ['issued', 'partial'])
            ->orderByDesc('invoice_date');

        $this->applyStoreScope($query, 'invoices');

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(function ($invoice) use ($tenantCode) {
            $balance = Schema::connection('tenant')->hasColumn('invoices', 'balance')
                ? (float) $invoice->balance
                : max(0, (float) $invoice->total - (float) ($invoice->amount_paid ?? 0));

            return [
                'id' => 'invoice:' . $invoice->id,
                'title' => $invoice->invoice_number,
                'subtitle' => $invoice->client?->name ?? 'Client',
                'meta' => fmt_money($balance),
                'url' => $this->route('tenant.invoicing.edit', ['invoice' => $invoice->id, 'tenant' => $tenantCode]),
            ];
        })->all();

        return [
            'key' => 'invoices',
            'label' => 'Factures impayées',
            'count' => $count,
            'list_url' => $this->route('tenant.invoicing.index', ['tenant' => $tenantCode]),
            'items' => $items,
        ];
    }

    private function pendingQuotations(string $tenantCode): array
    {
        if (!Schema::connection('tenant')->hasTable('quotations')) {
            return $this->emptyGroup('quotations', 'Devis à traiter');
        }

        $query = Quotation::query()
            ->with('client')
            ->where('status', 'sent')
            ->orderByDesc('quote_date');

        $this->applyStoreScope($query, 'quotations');

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(fn (Quotation $q) => [
            'id' => 'quotation:' . $q->id,
            'title' => $q->number,
            'subtitle' => $q->client?->name ?? 'Sans client',
            'meta' => fmt_money((float) $q->total),
            'url' => $this->route('tenant.quotations.edit', ['quotation' => $q->id, 'tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'quotations',
            'label' => 'Devis envoyés (à valider)',
            'count' => $count,
            'list_url' => $this->route('tenant.quotations.index', ['tenant' => $tenantCode, 'status' => 'sent']),
            'items' => $items,
        ];
    }

    private function assignedTickets(User $user, string $tenantCode): array
    {
        if (!Schema::connection('tenant')->hasTable('tickets')) {
            return $this->emptyGroup('tickets_mine', 'Mes tickets');
        }

        $query = Ticket::query()
            ->where('assigned_to', $user->id)
            ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_IN_PROGRESS])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByDesc('updated_at');

        $this->applyStoreScope($query, 'tickets');

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(fn (Ticket $ticket) => [
            'id' => 'ticket:' . $ticket->id,
            'title' => $ticket->ticket_number . ' — ' . \Illuminate\Support\Str::limit($ticket->title, 40),
            'subtitle' => $this->ticketStatusLabel($ticket->status),
            'meta' => ucfirst($ticket->priority),
            'url' => $this->route('tenant.tickets.show', ['ticket' => $ticket->id, 'tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'tickets_mine',
            'label' => 'Mes tickets assignés',
            'count' => $count,
            'list_url' => $this->route('tenant.tickets.index', ['tenant' => $tenantCode, 'assigned' => 'mine']),
            'items' => $items,
        ];
    }

    private function unassignedTickets(string $tenantCode): array
    {
        if (!Schema::connection('tenant')->hasTable('tickets')) {
            return $this->emptyGroup('tickets_unassigned', 'Tickets non assignés');
        }

        $query = Ticket::query()
            ->whereNull('assigned_to')
            ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_IN_PROGRESS])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at');

        $this->applyStoreScope($query, 'tickets');

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(fn (Ticket $ticket) => [
            'id' => 'ticket_unassigned:' . $ticket->id,
            'title' => $ticket->ticket_number . ' — ' . \Illuminate\Support\Str::limit($ticket->title, 40),
            'subtitle' => 'Non assigné',
            'meta' => ucfirst($ticket->priority),
            'url' => $this->route('tenant.tickets.show', ['ticket' => $ticket->id, 'tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'tickets_unassigned',
            'label' => 'Tickets non assignés',
            'count' => $count,
            'list_url' => $this->route('tenant.tickets.index', ['tenant' => $tenantCode, 'assigned' => 'unassigned']),
            'items' => $items,
        ];
    }

    private function expiredBatches(string $tenantCode): array
    {
        if (! Schema::connection('tenant')->hasTable('batches')
            || ! Schema::connection('tenant')->hasTable('items')) {
            return $this->emptyGroup('batches_expired', 'Lots périmés');
        }

        $today = now()->toDateString();
        $base = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('batches')
            ->join('items', 'batches.item_id', '=', 'items.id')
            ->where('batches.quantity', '>', 0)
            ->whereDate('batches.expiry_date', '<', $today);

        $count = (clone $base)->count();
        $rows = (clone $base)
            ->orderBy('batches.expiry_date')
            ->limit(self::ITEM_LIMIT)
            ->get([
                'batches.id',
                'batches.batch_number',
                'batches.expiry_date',
                'batches.quantity',
                'items.name as item_name',
                'items.sku',
            ]);

        $listUrl = $this->route('tenant.batches.index', ['tenant' => $tenantCode, 'filter' => 'expired']);
        $items = $rows->map(fn ($row) => [
            'id' => 'batch_expired:'.$row->id,
            'title' => $row->item_name,
            'subtitle' => 'Lot '.$row->batch_number.' — périmé le '.\Carbon\Carbon::parse($row->expiry_date)->format('d/m/Y'),
            'meta' => 'Qté '.fmt_num((float) $row->quantity),
            'url' => $listUrl,
        ])->all();

        return [
            'key' => 'batches_expired',
            'label' => 'Lots périmés à sortir',
            'count' => $count,
            'list_url' => $listUrl,
            'items' => $items,
        ];
    }

    private function nearExpiryBatches(string $tenantCode): array
    {
        if (! Schema::connection('tenant')->hasTable('batches')
            || ! Schema::connection('tenant')->hasTable('items')) {
            return $this->emptyGroup('batches_near', 'Lots bientôt périmés');
        }

        $today = now()->toDateString();
        $until = now()->addDays(30)->toDateString();
        $base = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('batches')
            ->join('items', 'batches.item_id', '=', 'items.id')
            ->where('batches.quantity', '>', 0)
            ->whereDate('batches.expiry_date', '>=', $today)
            ->whereDate('batches.expiry_date', '<=', $until);

        $count = (clone $base)->count();
        $rows = (clone $base)
            ->orderBy('batches.expiry_date')
            ->limit(self::ITEM_LIMIT)
            ->get([
                'batches.id',
                'batches.batch_number',
                'batches.expiry_date',
                'batches.quantity',
                'items.name as item_name',
            ]);

        $listUrl = $this->route('tenant.batches.index', ['tenant' => $tenantCode, 'filter' => 'd30']);
        $items = $rows->map(function ($row) use ($listUrl) {
            $days = (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($row->expiry_date)->startOfDay(), false);

            return [
                'id' => 'batch_near:'.$row->id,
                'title' => $row->item_name,
                'subtitle' => 'Lot '.$row->batch_number.' — exp. '.\Carbon\Carbon::parse($row->expiry_date)->format('d/m/Y'),
                'meta' => $days <= 0 ? 'Dernier jour' : ($days.' j'),
                'url' => $listUrl,
            ];
        })->all();

        return [
            'key' => 'batches_near',
            'label' => 'Péremption ≤ 30 jours',
            'count' => $count,
            'list_url' => $listUrl,
            'items' => $items,
        ];
    }

    private function lowStockAlerts(string $tenantCode): array
    {
        if (! Schema::connection('tenant')->hasTable('stock_levels')
            || ! Schema::connection('tenant')->hasTable('items')) {
            return $this->emptyGroup('low_stock', 'Stock faible');
        }

        $storeId = app(StoreContextService::class)->currentStoreId();
        $hasStoreColumn = Schema::connection('tenant')->hasColumn('stock_levels', 'store_id');

        $base = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('items')
            ->join('stock_levels', 'items.id', '=', 'stock_levels.item_id')
            ->whereNotNull('stock_levels.reorder_point')
            ->whereRaw('COALESCE(stock_levels.available_quantity, 0) <= stock_levels.reorder_point')
            ->when($hasStoreColumn && $storeId, fn ($q) => $q->where('stock_levels.store_id', $storeId));

        $count = (clone $base)->count();
        $rows = (clone $base)
            ->orderBy('stock_levels.available_quantity')
            ->limit(self::ITEM_LIMIT)
            ->get([
                'items.id as item_id',
                'items.name',
                'items.sku',
                'stock_levels.available_quantity',
                'stock_levels.reorder_point',
            ]);

        $listUrl = $this->route('tenant.stock.index', ['tenant' => $tenantCode, 'status' => 'low_stock']);
        $items = $rows->map(fn ($row) => [
            'id' => 'low_stock:'.$row->item_id,
            'title' => $row->name,
            'subtitle' => ($row->sku ? $row->sku.' — ' : '').'Seuil '.fmt_num((float) $row->reorder_point),
            'meta' => 'Dispo '.fmt_num((float) $row->available_quantity),
            'url' => $this->route('tenant.items.show', ['item' => $row->item_id, 'tenant' => $tenantCode])
                ?? $listUrl,
        ])->all();

        return [
            'key' => 'low_stock',
            'label' => 'Stock sous seuil',
            'count' => $count,
            'list_url' => $listUrl,
            'items' => $items,
        ];
    }

    private function ticketStatusLabel(string $status): string
    {
        return match ($status) {
            Ticket::STATUS_OPEN => 'Ouvert',
            Ticket::STATUS_IN_PROGRESS => 'En cours',
            Ticket::STATUS_RESOLVED => 'Résolu',
            Ticket::STATUS_CLOSED => 'Fermé',
            default => $status,
        };
    }

    private function applyStoreScope(Builder $query, string $table): void
    {
        $storeId = app(StoreContextService::class)->currentStoreId();
        if (!$storeId || !Schema::connection('tenant')->hasTable($table) || !Schema::connection('tenant')->hasColumn($table, 'store_id')) {
            return;
        }

        $query->where($table . '.store_id', $storeId);
    }

    private function moduleEnabled(Tenant $tenant, string $moduleKey): bool
    {
        return app(ModuleRegistry::class)->isEnabled($moduleKey, $tenant);
    }

    private function userCan(User $user, string $permission): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermission($permission);
    }

    private function route(string $name, array $params = []): ?string
    {
        if (!Route::has($name)) {
            return null;
        }

        return route($name, $params);
    }

    private function emptyGroup(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => 0,
            'list_url' => null,
            'items' => [],
        ];
    }
}
