<?php

namespace App\Services;

use App\Support\DashboardMetrics;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardOverviewService
{
    private const POSTED_STATUSES = ['issued', 'partial', 'paid'];

    private const OPEN_STATUSES = ['issued', 'partial'];

    /**
     * Full admin dashboard snapshot for a calendar month.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Carbon $monthStart): array
    {
        $start = $monthStart->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $previousStart = $start->copy()->subMonth()->startOfMonth();
        $previousEnd = $previousStart->copy()->endOfMonth();
        $locale = app()->getLocale() ?: 'fr';

        $current = $this->periodMetrics($start, $end);
        $previous = $this->periodMetrics($previousStart, $previousEnd);
        $outstanding = $this->outstandingHt();
        // Encours début de mois ≈ encours actuel − CA facturé HT + CA encaissé HT du mois.
        $previousOutstanding = DashboardMetrics::round(max(
            0,
            $outstanding - $current['billed_ht'] + $current['collected_ht']
        ));

        $kpis = [
            $this->kpi(
                'billed_ttc',
                'CA facturé (TTC)',
                $current['billed_ttc'],
                $previous['billed_ttc'],
                $previousStart->copy()->locale($locale),
                'blue',
                'receipt'
            ),
            $this->kpi(
                'billed_ht',
                'CA facturé (HT)',
                $current['billed_ht'],
                $previous['billed_ht'],
                $previousStart->copy()->locale($locale),
                'blue',
                'document'
            ),
            $this->kpi(
                'collected_ht',
                'CA encaissé (HT)',
                $current['collected_ht'],
                $previous['collected_ht'],
                $previousStart->copy()->locale($locale),
                'green',
                'credit-card'
            ),
            $this->kpi(
                'outstanding_ht',
                'Solde à encaisser (HT)',
                $outstanding,
                $previousOutstanding,
                $previousStart->copy()->locale($locale),
                'orange',
                'clock'
            ),
            $this->kpi(
                'expenses',
                'Dépenses',
                $current['expenses'],
                $previous['expenses'],
                $previousStart->copy()->locale($locale),
                'rose',
                'shopping-bag'
            ),
        ];

        $sparklines = $this->sparklineSeries($start);
        foreach ($kpis as &$kpi) {
            $kpi['sparkline'] = DashboardMetrics::sparklinePoints($sparklines[$kpi['key']] ?? [0, 0]);
        }
        unset($kpi);

        $vat = [
            'collected' => $this->vatKpi($current['vat_collected'], $previous['vat_collected'], $previousStart->copy()->locale($locale)),
            'received' => $this->vatKpi($current['vat_received'], $previous['vat_received'], $previousStart->copy()->locale($locale)),
            'withheld' => $this->vatKpi($current['vat_withheld'], $previous['vat_withheld'], $previousStart->copy()->locale($locale)),
            'to_declare' => $this->vatKpi($current['vat_to_declare'], $previous['vat_to_declare'], $previousStart->copy()->locale($locale)),
        ];

        $pending = $this->pendingInvoices(5);
        $recent = $this->recentInvoices(5);

        return [
            'month_start' => $start->toDateString(),
            'month_label' => $start->copy()->locale($locale)->translatedFormat('F Y'),
            'invoice_count' => $current['invoice_count'],
            'kpis' => $kpis,
            'vat' => $vat,
            'pending_invoices' => $pending['rows'],
            'pending_total_ttc' => $pending['total_ttc'],
            'pending_all_count' => $pending['all_count'],
            'recent_invoices' => $recent['rows'],
            'recent_total_ttc' => $recent['total_ttc'],
            'alerts' => $this->alerts(),
            'activity' => $this->recentActivity(8),
        ];
    }

    /**
     * @return array{
     *     billed_ht: float,
     *     billed_ttc: float,
     *     collected_ht: float,
     *     expenses: float,
     *     vat_collected: float,
     *     vat_received: float,
     *     vat_withheld: float,
     *     vat_to_declare: float,
     *     invoice_count: int
     * }
     */
    private function periodMetrics(Carbon $start, Carbon $end): array
    {
        $billedHt = $this->billedHtBetween($start, $end);
        $vatCollected = $this->vatCollectedBetween($start, $end);
        $vatWithheld = $this->vatWithheldBetween($start, $end);
        $vatReceived = $this->vatReceivedBetween($start, $end);

        return [
            'billed_ht' => $billedHt,
            'billed_ttc' => DashboardMetrics::billedTtc($billedHt, $vatCollected),
            'collected_ht' => $this->collectedHtBetween($start, $end),
            'expenses' => $this->expensesBetween($start, $end),
            'vat_collected' => $vatCollected,
            'vat_received' => $vatReceived,
            'vat_withheld' => $vatWithheld,
            'vat_to_declare' => DashboardMetrics::vatToDeclare($vatCollected, $vatWithheld),
            'invoice_count' => $this->invoiceCountBetween($start, $end),
        ];
    }

    private function billedHtBetween(Carbon $start, Carbon $end): float
    {
        if (! $this->hasTable('invoices')) {
            return 0.0;
        }

        $value = $this->postedInvoices()
            ->whereBetween('invoices.invoice_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(SUM('.$this->htSql('invoices').'), 0) as revenue')
            ->value('revenue');

        return DashboardMetrics::round((float) $value);
    }

    private function invoiceCountBetween(Carbon $start, Carbon $end): int
    {
        if (! $this->hasTable('invoices')) {
            return 0;
        }

        return (int) $this->postedInvoices()
            ->whereBetween('invoices.invoice_date', [$start->toDateString(), $end->toDateString()])
            ->count();
    }

    private function collectedHtBetween(Carbon $start, Carbon $end): float
    {
        if (! $this->hasTable('invoice_payments') || ! $this->hasTable('invoices')) {
            return 0.0;
        }

        $query = $this->activePayments()
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->whereBetween('invoice_payments.payment_date', [$start->toDateString(), $end->toDateString()]);
        $this->applyStoreFilter($query, 'invoices');

        $value = $query->selectRaw(
            'COALESCE(SUM(CASE WHEN invoices.total > 0 THEN ('.$this->settledSql().') * '.$this->htSql('invoices').' / invoices.total ELSE 0 END), 0) as collected_ht'
        )->value('collected_ht');

        return DashboardMetrics::round((float) $value);
    }

    private function outstandingHt(): float
    {
        if (! $this->hasTable('invoices') || ! Schema::connection('tenant')->hasColumn('invoices', 'balance')) {
            return 0.0;
        }

        $value = $this->openInvoices()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN invoices.total > 0 THEN GREATEST(0, invoices.balance) * '.$this->htSql('invoices').' / invoices.total ELSE 0 END), 0) as outstanding_ht'
            )
            ->value('outstanding_ht');

        return DashboardMetrics::round((float) $value);
    }

    /**
     * Remaining HT on invoices issued in a past period that are still open today.
     * Used only as a comparable "vs previous month" for the outstanding KPI.
     */
    private function outstandingHtIssuedBetween(Carbon $start, Carbon $end): float
    {
        if (! $this->hasTable('invoices') || ! Schema::connection('tenant')->hasColumn('invoices', 'balance')) {
            return 0.0;
        }

        $value = $this->openInvoices()
            ->whereBetween('invoices.invoice_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN invoices.total > 0 THEN GREATEST(0, invoices.balance) * '.$this->htSql('invoices').' / invoices.total ELSE 0 END), 0) as outstanding_ht'
            )
            ->value('outstanding_ht');

        return DashboardMetrics::round((float) $value);
    }

    private function vatCollectedBetween(Carbon $start, Carbon $end): float
    {
        if (! $this->hasTable('invoices')) {
            return 0.0;
        }

        $fromLines = 0.0;
        if ($this->hasTable('invoice_tax_lines')) {
            $query = $this->postedInvoices()
                ->join('invoice_tax_lines', 'invoice_tax_lines.invoice_id', '=', 'invoices.id')
                ->whereBetween('invoices.invoice_date', [$start->toDateString(), $end->toDateString()]);
            $this->constrainVatTaxLines($query);

            $fromLines = (float) $query
                ->selectRaw('COALESCE(SUM(invoice_tax_lines.tax_amount), 0) as vat')
                ->value('vat');
        }

        $fallback = 0.0;
        if (Schema::connection('tenant')->hasColumn('invoices', 'tax_amount')) {
            $query = $this->postedInvoices()
                ->whereBetween('invoices.invoice_date', [$start->toDateString(), $end->toDateString()]);

            if ($this->hasTable('invoice_tax_lines')) {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('invoice_tax_lines')
                        ->whereColumn('invoice_tax_lines.invoice_id', 'invoices.id');
                });
            }

            $fallback = (float) $query
                ->selectRaw('COALESCE(SUM(GREATEST(0, invoices.tax_amount)), 0) as vat')
                ->value('vat');
        }

        return DashboardMetrics::round($fromLines + $fallback);
    }

    private function vatWithheldBetween(Carbon $start, Carbon $end): float
    {
        if (! $this->hasTable('invoice_payment_withholdings') || ! $this->hasTable('invoice_payments')) {
            return 0.0;
        }

        $query = $this->activePayments()
            ->join('invoice_payment_withholdings', 'invoice_payment_withholdings.invoice_payment_id', '=', 'invoice_payments.id')
            ->whereBetween('invoice_payments.payment_date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($q) {
                $q->where('invoice_payment_withholdings.type_code', 'tva_retenue')
                    ->orWhereRaw('LOWER(invoice_payment_withholdings.type_code) LIKE ?', ['%tva%'])
                    ->orWhereRaw('LOWER(invoice_payment_withholdings.type_name) LIKE ?', ['%tva%']);
            });

        if ($this->hasTable('invoices')) {
            $query->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id');
            $this->applyStoreFilter($query, 'invoices');
        }

        return DashboardMetrics::round((float) $query->sum('invoice_payment_withholdings.amount'));
    }

    /**
     * VAT actually received in cash this period: cash × TVA / TTC of the invoice.
     */
    private function vatReceivedBetween(Carbon $start, Carbon $end): float
    {
        if (! $this->hasTable('invoice_payments') || ! $this->hasTable('invoices')) {
            return 0.0;
        }

        $vatSql = $this->invoiceVatSql();

        $query = $this->activePayments()
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->whereBetween('invoice_payments.payment_date', [$start->toDateString(), $end->toDateString()]);
        $this->applyStoreFilter($query, 'invoices');

        $ht = $this->htSql('invoices');
        $value = $query->selectRaw(
            "COALESCE(SUM(
                CASE
                    WHEN ({$ht} + {$vatSql}) > 0
                    THEN invoice_payments.amount * {$vatSql} / ({$ht} + {$vatSql})
                    ELSE 0
                END
            ), 0) as vat_received"
        )->value('vat_received');

        return DashboardMetrics::round((float) $value);
    }

    private function expensesBetween(Carbon $start, Carbon $end): float
    {
        if (! $this->hasTable('expenses')) {
            return 0.0;
        }

        $query = DB::connection('tenant')
            ->table('expenses')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['approved', 'paid']);
        $this->applyStoreFilter($query, 'expenses');

        return DashboardMetrics::round((float) $query->sum('amount'));
    }

    /**
     * @return array<string, array<int, float>>
     */
    private function sparklineSeries(Carbon $monthStart): array
    {
        $keys = ['billed_ht', 'billed_ttc', 'collected_ht', 'outstanding_ht', 'expenses'];
        $series = array_fill_keys($keys, []);

        for ($i = 5; $i >= 0; $i--) {
            $start = $monthStart->copy()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $metrics = $this->periodMetrics($start, $end);
            $series['billed_ht'][] = $metrics['billed_ht'];
            $series['billed_ttc'][] = $metrics['billed_ttc'];
            $series['collected_ht'][] = $metrics['collected_ht'];
            $series['outstanding_ht'][] = $this->outstandingHtIssuedBetween($start, $end);
            $series['expenses'][] = $metrics['expenses'];
        }

        $series['outstanding_ht'][5] = $this->outstandingHt();

        return $series;
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total_ttc: float, all_count: int}
     */
    private function pendingInvoices(int $limit): array
    {
        if (! $this->hasTable('invoices')) {
            return ['rows' => [], 'total_ttc' => 0.0, 'all_count' => 0];
        }

        $hasClients = $this->hasTable('clients');
        $hasDue = Schema::connection('tenant')->hasColumn('invoices', 'due_date');
        $hasBalance = Schema::connection('tenant')->hasColumn('invoices', 'balance');
        $today = Carbon::today();

        $query = $this->openInvoices()
            ->when($hasClients, fn ($q) => $q->leftJoin('clients', 'invoices.client_id', '=', 'clients.id'))
            ->when($hasDue, fn ($q) => $q->orderByRaw('invoices.due_date IS NULL')->orderBy('invoices.due_date'))
            ->orderBy('invoices.invoice_date');

        $allCount = (int) (clone $query)->count('invoices.id');

        $columns = [
            'invoices.id',
            'invoices.invoice_number',
            'invoices.invoice_date',
            'invoices.total',
            'invoices.status',
            'invoices.subtotal',
            'invoices.discount_amount',
        ];
        if ($hasDue) {
            $columns[] = 'invoices.due_date';
        }
        if ($hasBalance) {
            $columns[] = 'invoices.balance';
        }
        if ($hasClients) {
            $columns[] = 'clients.name as client_name';
        }

        $rows = $query->limit($limit)->get($columns);

        $mapped = $rows->map(function ($row) use ($hasDue, $hasBalance, $today) {
            $ht = DashboardMetrics::netHt((float) $row->subtotal, (float) ($row->discount_amount ?? 0));
            $ttc = (float) $row->total;
            $due = $hasDue && $row->due_date ? Carbon::parse($row->due_date) : null;

            return [
                'id' => (int) $row->id,
                'invoice_number' => (string) $row->invoice_number,
                'client_name' => $row->client_name ?? null,
                'invoice_date' => (string) $row->invoice_date,
                'due_date' => $due?->toDateString(),
                'total' => DashboardMetrics::round($ttc),
                'balance' => $hasBalance ? DashboardMetrics::round((float) $row->balance) : DashboardMetrics::round($ttc),
                'status' => (string) $row->status,
                'urgency' => DashboardMetrics::paymentUrgency($due, $today),
                'ht' => $ht,
            ];
        })->all();

        $totalTtc = DashboardMetrics::round(array_sum(array_column($mapped, 'total')));

        return [
            'rows' => $mapped,
            'total_ttc' => $totalTtc,
            'all_count' => $allCount,
        ];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total_ttc: float}
     */
    private function recentInvoices(int $limit): array
    {
        if (! $this->hasTable('invoices')) {
            return ['rows' => [], 'total_ttc' => 0.0];
        }

        $hasClients = $this->hasTable('clients');

        $query = $this->postedInvoices()
            ->when($hasClients, fn ($q) => $q->leftJoin('clients', 'invoices.client_id', '=', 'clients.id'))
            ->orderByDesc('invoices.invoice_date')
            ->orderByDesc('invoices.id')
            ->limit($limit);

        $columns = [
            'invoices.id',
            'invoices.invoice_number',
            'invoices.invoice_date',
            'invoices.total',
            'invoices.status',
        ];
        if ($hasClients) {
            $columns[] = 'clients.name as client_name';
        }

        $rows = $query->get($columns)->map(fn ($row) => [
            'id' => (int) $row->id,
            'invoice_number' => (string) $row->invoice_number,
            'client_name' => $row->client_name ?? null,
            'invoice_date' => (string) $row->invoice_date,
            'total' => DashboardMetrics::round((float) $row->total),
            'status' => (string) $row->status,
        ])->all();

        return [
            'rows' => $rows,
            'total_ttc' => DashboardMetrics::round(array_sum(array_column($rows, 'total'))),
        ];
    }

    /**
     * @return array<int, array{key: string, tone: string, title: string, count: int}>
     */
    private function alerts(): array
    {
        $alerts = [];
        $today = Carbon::today();

        if ($this->hasTable('invoices') && Schema::connection('tenant')->hasColumn('invoices', 'due_date')) {
            $overdue = (int) $this->openInvoices()
                ->whereNotNull('invoices.due_date')
                ->whereDate('invoices.due_date', '<', $today->toDateString())
                ->count();

            if ($overdue > 0) {
                $alerts[] = [
                    'key' => 'overdue',
                    'tone' => 'danger',
                    'title' => $overdue === 1
                        ? '1 facture en retard de paiement'
                        : $overdue.' factures en retard de paiement',
                    'count' => $overdue,
                ];
            }

            $watchUntil = $today->copy()->addDays(7);
            $dueSoon = (int) $this->openInvoices()
                ->whereNotNull('invoices.due_date')
                ->whereDate('invoices.due_date', '>=', $today->toDateString())
                ->whereDate('invoices.due_date', '<=', $watchUntil->toDateString())
                ->count();

            if ($dueSoon > 0) {
                $alerts[] = [
                    'key' => 'due_soon',
                    'tone' => 'warning',
                    'title' => $dueSoon === 1
                        ? '1 facture arrive à échéance dans les 7 jours'
                        : $dueSoon.' factures arrivent à échéance dans les 7 jours',
                    'count' => $dueSoon,
                ];
            }
        }

        if ($this->hasTable('quotations') && Schema::connection('tenant')->hasColumn('quotations', 'valid_until')) {
            $until = $today->copy()->addDays(5);
            $query = DB::connection('tenant')
                ->table('quotations')
                ->whereNotNull('valid_until')
                ->whereDate('valid_until', '>=', $today->toDateString())
                ->whereDate('valid_until', '<=', $until->toDateString())
                ->whereNotIn('status', ['rejected', 'accepted', 'validated']);
            $this->applyStoreFilter($query, 'quotations');
            $expiring = (int) $query->count();

            if ($expiring > 0) {
                $alerts[] = [
                    'key' => 'quotes',
                    'tone' => 'success',
                    'title' => $expiring === 1
                        ? '1 devis expire dans les 5 jours'
                        : $expiring.' devis expirent dans les 5 jours',
                    'count' => $expiring,
                ];
            }
        }

        return $alerts;
    }

    /**
     * @return array<int, array{at: string, title: string, meta: string, tone: string}>
     */
    private function recentActivity(int $limit): array
    {
        $events = [];

        if ($this->hasTable('invoice_payments') && $this->hasTable('invoices')) {
            $hasClients = $this->hasTable('clients');
            $query = $this->activePayments()
                ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
                ->when($hasClients, fn ($q) => $q->leftJoin('clients', 'invoices.client_id', '=', 'clients.id'))
                ->orderByDesc('invoice_payments.payment_date')
                ->orderByDesc('invoice_payments.id')
                ->limit($limit);

            $this->applyStoreFilter($query, 'invoices');

            $columns = [
                'invoice_payments.payment_date',
                'invoice_payments.amount',
                'invoice_payments.created_at',
                'invoices.invoice_number',
            ];
            if ($hasClients) {
                $columns[] = 'clients.name as client_name';
            }

            foreach ($query->get($columns) as $row) {
                $client = $row->client_name ?? 'un client';
                $events[] = [
                    'at' => (string) ($row->created_at ?: $row->payment_date),
                    'sort' => (string) ($row->created_at ?: $row->payment_date.' 23:59:59'),
                    'title' => 'Paiement reçu de '.$client,
                    'meta' => (string) $row->invoice_number,
                    'amount' => DashboardMetrics::round((float) $row->amount),
                    'tone' => 'green',
                ];
            }
        }

        if ($this->hasTable('invoices')) {
            $query = $this->postedInvoices()
                ->where('invoices.status', 'paid')
                ->orderByDesc('invoices.updated_at')
                ->limit($limit);

            foreach ($query->get(['invoices.invoice_number', 'invoices.updated_at', 'invoices.total']) as $row) {
                $events[] = [
                    'at' => (string) $row->updated_at,
                    'sort' => (string) $row->updated_at,
                    'title' => 'Facture '.$row->invoice_number.' marquée comme payée',
                    'meta' => (string) $row->invoice_number,
                    'amount' => DashboardMetrics::round((float) $row->total),
                    'tone' => 'blue',
                ];
            }
        }

        if ($this->hasTable('purchase_orders')) {
            $hasProviders = $this->hasTable('providers');
            $query = DB::connection('tenant')
                ->table('purchase_orders')
                ->when($hasProviders, fn ($q) => $q->leftJoin('providers', 'purchase_orders.provider_id', '=', 'providers.id'))
                ->whereNotIn('purchase_orders.status', ['cancelled', 'draft'])
                ->orderByDesc('purchase_orders.order_date')
                ->orderByDesc('purchase_orders.id')
                ->limit($limit);

            $columns = ['purchase_orders.order_number', 'purchase_orders.order_date', 'purchase_orders.total', 'purchase_orders.created_at'];
            if ($hasProviders) {
                $columns[] = 'providers.name as provider_name';
            }

            foreach ($query->get($columns) as $row) {
                $provider = $row->provider_name ?? 'fournisseur';
                $events[] = [
                    'at' => (string) ($row->created_at ?: $row->order_date),
                    'sort' => (string) ($row->created_at ?: $row->order_date.' 12:00:00'),
                    'title' => 'Achat fournisseur '.$provider,
                    'meta' => (string) $row->order_number,
                    'amount' => DashboardMetrics::round((float) $row->total),
                    'tone' => 'amber',
                ];
            }
        }

        usort($events, fn ($a, $b) => strcmp($b['sort'], $a['sort']));
        $events = array_slice($events, 0, $limit);

        return array_map(function ($event) {
            unset($event['sort']);

            return $event;
        }, $events);
    }

    /**
     * @return array<string, mixed>
     */
    private function kpi(
        string $key,
        string $label,
        float $value,
        float $previous,
        Carbon $previousMonth,
        string $tone,
        string $icon
    ): array {
        $trend = DashboardMetrics::trendPercent($value, $previous);

        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'previous' => $previous,
            'previous_label' => 'vs '.$previousMonth->translatedFormat('M Y'),
            'trend' => $trend,
            'tone' => $tone,
            'icon' => $icon,
            'sparkline' => '',
        ];
    }

    /**
     * @return array{value: float, previous: float, previous_label: string, trend: float|null}
     */
    private function vatKpi(float $value, float $previous, Carbon $previousMonth): array
    {
        return [
            'value' => $value,
            'previous' => $previous,
            'previous_label' => 'vs '.$previousMonth->translatedFormat('M Y'),
            'trend' => DashboardMetrics::trendPercent($value, $previous),
        ];
    }

    private function postedInvoices(): Builder
    {
        $query = DB::connection('tenant')
            ->table('invoices')
            ->whereIn('invoices.status', self::POSTED_STATUSES);

        if (Schema::connection('tenant')->hasColumn('invoices', 'document_type')) {
            $query->where(function ($q) {
                $q->whereNull('invoices.document_type')
                    ->orWhere('invoices.document_type', '!=', 'cancellation');
            });
        }

        $this->applyStoreFilter($query, 'invoices');

        return $query;
    }

    private function openInvoices(): Builder
    {
        $query = DB::connection('tenant')
            ->table('invoices')
            ->whereIn('invoices.status', self::OPEN_STATUSES);

        if (Schema::connection('tenant')->hasColumn('invoices', 'document_type')) {
            $query->where(function ($q) {
                $q->whereNull('invoices.document_type')
                    ->orWhere('invoices.document_type', '!=', 'cancellation');
            });
        }

        if (Schema::connection('tenant')->hasColumn('invoices', 'balance')) {
            $query->where('invoices.balance', '>', 0.01);
        }

        $this->applyStoreFilter($query, 'invoices');

        return $query;
    }

    private function activePayments(): Builder
    {
        $query = DB::connection('tenant')->table('invoice_payments');

        if (Schema::connection('tenant')->hasColumn('invoice_payments', 'status')) {
            $query->where(function ($q) {
                $q->whereNull('invoice_payments.status')
                    ->orWhere('invoice_payments.status', '!=', 'cancelled');
            });
        }

        return $query;
    }

    private function applyStoreFilter(Builder $query, string $table = ''): Builder
    {
        $storeId = app(StoreContextService::class)->currentStoreId();
        $column = $table !== '' ? "{$table}.store_id" : 'store_id';
        $checkTable = $table !== '' ? $table : (string) $query->from;
        $checkTable = str_contains($checkTable, ' ') ? explode(' ', $checkTable)[0] : $checkTable;

        if ($storeId && Schema::connection('tenant')->hasTable($checkTable) && Schema::connection('tenant')->hasColumn($checkTable, 'store_id')) {
            $query->where($column, $storeId);
        }

        return $query;
    }

    private function hasTable(string $table): bool
    {
        return Schema::connection('tenant')->hasTable($table);
    }

    private function htSql(string $alias = 'invoices'): string
    {
        return "GREATEST(0, {$alias}.subtotal - COALESCE({$alias}.discount_amount, 0))";
    }

    private function settledSql(): string
    {
        $hasSettled = Schema::connection('tenant')->hasColumn('invoice_payments', 'settled_amount');
        $hasWithholding = Schema::connection('tenant')->hasColumn('invoice_payments', 'withholding_total');

        if ($hasSettled && $hasWithholding) {
            return 'CASE WHEN invoice_payments.settled_amount IS NULL THEN invoice_payments.amount + COALESCE(invoice_payments.withholding_total, 0) ELSE invoice_payments.settled_amount END';
        }

        if ($hasWithholding) {
            return '(invoice_payments.amount + COALESCE(invoice_payments.withholding_total, 0))';
        }

        return 'invoice_payments.amount';
    }

    private function constrainVatTaxLines(Builder $query): void
    {
        $query->where(function ($q) {
            $q->whereRaw('LOWER(invoice_tax_lines.tax_name) LIKE ?', ['%tva%'])
                ->orWhereRaw('LOWER(invoice_tax_lines.tax_name) LIKE ?', ['%vat%']);
        });

        if (Schema::connection('tenant')->hasColumn('invoice_tax_lines', 'tax_effect')) {
            $query->where(function ($q) {
                $q->whereNull('invoice_tax_lines.tax_effect')
                    ->orWhere('invoice_tax_lines.tax_effect', 'add');
            });
        }
    }

    private function invoiceVatSql(): string
    {
        if (! $this->hasTable('invoice_tax_lines')) {
            return Schema::connection('tenant')->hasColumn('invoices', 'tax_amount')
                ? 'GREATEST(0, COALESCE(invoices.tax_amount, 0))'
                : '0';
        }

        $effect = Schema::connection('tenant')->hasColumn('invoice_tax_lines', 'tax_effect')
            ? "AND (tl.tax_effect IS NULL OR tl.tax_effect = 'add')"
            : '';

        return "(SELECT COALESCE(SUM(tl.tax_amount), 0) FROM invoice_tax_lines tl
            WHERE tl.invoice_id = invoices.id
              AND (LOWER(tl.tax_name) LIKE '%tva%' OR LOWER(tl.tax_name) LIKE '%vat%')
              {$effect})";
    }
}
