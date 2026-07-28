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
use Pressing\Models\PressingDelivery;
use Pressing\Models\PressingOrder;

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

        if ($this->moduleEnabled($tenant, 'pressing_orders') && $this->userCan($user, 'pressing_orders.view')) {
            $ready = $this->pressingReadyOrders($tenantCode);
            if ($ready['count'] > 0) {
                $groups[] = $ready;
            }
            $overdue = $this->pressingOverdueOrders($tenantCode);
            if ($overdue['count'] > 0) {
                $groups[] = $overdue;
            }
        }

        if ($this->moduleEnabled($tenant, 'pressing_fin_production')
            && ($this->userCan($user, 'pressing_fin_production.view') || $this->userCan($user, 'pressing_orders.create'))) {
            $fin = $this->pressingFinProduction($tenantCode);
            if ($fin['count'] > 0) {
                $groups[] = $fin;
            }
        }

        if ($this->moduleEnabled($tenant, 'pressing_orders')
            && ($this->userCan($user, 'pressing_orders.validate_credit') || $this->userCan($user, 'debts.validate'))) {
            $credits = $this->pressingPendingCredits($tenantCode);
            if ($credits['count'] > 0) {
                $groups[] = $credits;
            }
        }

        if ($this->moduleEnabled($tenant, 'pressing_deliveries') && $this->userCan($user, 'pressing_deliveries.view')) {
            $deliveries = $this->pressingPendingDeliveries($tenantCode);
            if ($deliveries['count'] > 0) {
                $groups[] = $deliveries;
            }
        }

        if ($this->moduleEnabled($tenant, 'pressing_consumables')
            && ($this->userCan($user, 'pressing_consumables.view') || $this->userCan($user, 'stock.view'))) {
            $low = $this->pressingLowConsumables($tenantCode);
            if ($low['count'] > 0) {
                $groups[] = $low;
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

    private function pressingPendingCredits(string $tenantCode): array
    {
        if (! Schema::connection('tenant')->hasTable('pressing_orders')
            || ! Schema::connection('tenant')->hasColumn('pressing_orders', 'credit_status')) {
            return $this->emptyGroup('pressing_credits', 'Crédits pressing à valider');
        }

        $query = PressingOrder::query()
            ->with('client')
            ->where('credit_status', 'pending')
            ->whereIn('status', ['open', 'ready'])
            ->latest('credit_requested_at');

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(fn (PressingOrder $order) => [
            'id' => 'pressing_credit:'.$order->id,
            'title' => $order->number,
            'subtitle' => ($order->client?->full_name ?? 'Client').' · '.number_format((float) $order->credit_amount, 0, ',', ' ').' FCFA',
            'meta' => 'Crédit à valider',
            'url' => $this->route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'pressing_credits',
            'label' => 'Crédits pressing à valider',
            'count' => $count,
            'list_url' => $this->route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]),
            'items' => $items,
        ];
    }

    private function pressingLowConsumables(string $tenantCode): array
    {
        if (! Schema::connection('tenant')->hasTable('items')
            || ! Schema::connection('tenant')->hasTable('stock_levels')
            || ! class_exists(\Pressing\Services\PressingConsumablesService::class)) {
            return $this->emptyGroup('pressing_consumables_low', 'Consommables bas');
        }

        try {
            $rows = collect(app(\Pressing\Services\PressingConsumablesService::class)->dashboardRows())
                ->where('is_low', true)
                ->values();
        } catch (\Throwable) {
            return $this->emptyGroup('pressing_consumables_low', 'Consommables bas');
        }

        $items = $rows->take(self::ITEM_LIMIT)->map(fn (array $row) => [
            'id' => 'consumable:'.$row['id'],
            'title' => $row['name'],
            'subtitle' => number_format((float) $row['quantity'], 2, ',', ' ').' '.$row['unit']
                .' (seuil '.number_format((float) ($row['reorder_point'] ?? 0), 2, ',', ' ').')',
            'meta' => 'Stock bas',
            'url' => $this->route('tenant.pressing_consumables.index', ['tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'pressing_consumables_low',
            'label' => 'Consommables bas',
            'count' => $rows->count(),
            'list_url' => $this->route('tenant.pressing_consumables.index', ['tenant' => $tenantCode]),
            'items' => $items,
        ];
    }

    private function pressingReadyOrders(string $tenantCode): array
    {
        if (! Schema::connection('tenant')->hasTable('pressing_orders')) {
            return $this->emptyGroup('pressing_ready', 'Commandes prêtes');
        }

        $query = PressingOrder::query()
            ->with('client')
            ->where('status', 'ready')
            ->latest('updated_at');

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(fn (PressingOrder $order) => [
            'id' => 'pressing_ready:' . $order->id,
            'title' => $order->number,
            'subtitle' => $order->client?->full_name ?? 'Client',
            'meta' => 'Prêt',
            'url' => $this->route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'pressing_ready',
            'label' => 'Commandes prêtes à remettre',
            'count' => $count,
            'list_url' => $this->route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]),
            'items' => $items,
        ];
    }

    private function pressingFinProduction(string $tenantCode): array
    {
        if (! Schema::connection('tenant')->hasTable('pressing_orders')
            || ! Schema::connection('tenant')->hasTable('workflow_stages')) {
            return $this->emptyGroup('pressing_fin_production', 'Fin de production');
        }

        $finId = \Pressing\Models\WorkflowStage::query()
            ->whereNull('agence_id')
            ->where('name', 'Fin de production')
            ->where('is_active', true)
            ->value('id');

        if (! $finId) {
            return $this->emptyGroup('pressing_fin_production', 'Fin de production');
        }

        $query = PressingOrder::query()
            ->with('client')
            ->where('status', 'open')
            ->where('current_stage_id', $finId)
            ->latest('updated_at');

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(fn (PressingOrder $order) => [
            'id' => 'pressing_fin:' . $order->id,
            'title' => $order->number,
            'subtitle' => $order->client?->full_name ?? 'Client',
            'meta' => 'Contrôle qualité',
            'url' => $this->route('tenant.pressing_fin_production.index', ['tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'pressing_fin_production',
            'label' => 'Fin de production — CQ',
            'count' => $count,
            'list_url' => $this->route('tenant.pressing_fin_production.index', ['tenant' => $tenantCode]),
            'items' => $items,
        ];
    }

    private function pressingOverdueOrders(string $tenantCode): array
    {
        if (! Schema::connection('tenant')->hasTable('pressing_orders')) {
            return $this->emptyGroup('pressing_overdue', 'Commandes en retard');
        }

        $query = PressingOrder::query()
            ->with('client')
            ->whereIn('status', ['open', 'ready'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at');

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(fn (PressingOrder $order) => [
            'id' => 'pressing_overdue:' . $order->id,
            'title' => $order->number,
            'subtitle' => $order->client?->full_name ?? 'Client',
            'meta' => $order->due_at?->format('d/m H:i'),
            'url' => $this->route('tenant.pressing_orders.index', ['tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'pressing_overdue',
            'label' => 'Commandes en retard',
            'count' => $count,
            'list_url' => $this->route('tenant.pressing_orders.index', ['tenant' => $tenantCode]),
            'items' => $items,
        ];
    }

    private function pressingPendingDeliveries(string $tenantCode): array
    {
        if (! Schema::connection('tenant')->hasTable('pressing_deliveries')) {
            return $this->emptyGroup('pressing_deliveries', 'Livraisons à traiter');
        }

        $query = PressingDelivery::query()
            ->with(['order.client'])
            ->whereIn('status', ['pending', 'in_transit'])
            ->latest();

        $count = (clone $query)->count();
        $items = $query->limit(self::ITEM_LIMIT)->get()->map(fn (PressingDelivery $delivery) => [
            'id' => 'pressing_delivery:' . $delivery->id,
            'title' => $delivery->order?->number ?? ('Livraison #' . $delivery->id),
            'subtitle' => $delivery->order?->client?->full_name ?? 'Client',
            'meta' => PressingDelivery::STATUSES[$delivery->status] ?? $delivery->status,
            'url' => $this->route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]),
        ])->all();

        return [
            'key' => 'pressing_deliveries',
            'label' => 'Livraisons à traiter',
            'count' => $count,
            'list_url' => $this->route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]),
            'items' => $items,
        ];
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
